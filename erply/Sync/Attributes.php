<?php

include_once(_PS_MODULE_DIR_ . '/erply/Sync/Abstract.php');
include_once(_PS_MODULE_DIR_ . '/erply/ErplyFunctions.class.php');

class Erply_Sync_Attributes extends Erply_Sync_Abstract
{    
    
    public static function syncAll($ignoreLastTimestamp = false)
    {
        return self::logAndReturn('Attrbiute syncAll not implemented');
    }
    
    public static function importAll($ignoreLastTimestamp = false)
    {
        return self::logAndReturn('Attrbiute importAll not implemented');
    }
    
    public static function exportAll($ignoreLastTimestamp = false)
    {
        ErplyFunctions::log('Start Attribute Export');
        
        $output = '';
        // Verify connection with erply
        $api    = ErplyFunctions::getErplyApi();
        $api->VerifyUser();
        // Connection with erply OK
        
        $id_lang = Configuration::get('PS_LANG_DEFAULT');
        
        // get matrix dimensions from erply
        $erplyMatrixDimensions = $api->getMatrixDimensions();
        // get local attribute groups
        $attributeGroups  = AttributeGroup::getAttributesGroups($id_lang);
        
        if(!is_array($attributeGroups) && !count($attributeGroups)) {
            return self::logAndReturn('No Attribute Groups found in Prestashop', 'warn');
        }
        
        // get Matrix Dimension ids from local erply mapping
        $localMatrixIds = ErplyMapping::getMappingErplyIds('ParentAttribute');
        
        if(is_array($localMatrixIds) && count($localMatrixIds)) {
            return self::logAndReturn('Attributes already exported once. No changes', 'warn');
        }
        
        if(is_array($erplyMatrixDimensions) && count($erplyMatrixDimensions) && is_array($attributeGroups) && count($attributeGroups)) {
            foreach($erplyMatrixDimensions as $mdim) {
                foreach($attributeGroups as $agrp) {
                    if($mdim['name'] == $agrp['name'] && !is_numeric(array_search($mdim['dimensionID'], $localMatrixIds))) {
                        return self::logAndReturn('Same name in Erply Matrix Dimension and Prestashop Attribute Group: ' . $mdim['name'] . ', but different ids. No changes', 'warn');
                    }
                }
            }
        }
        
        $apiCallParams = array();
        $attrIdsOrder  = array();
        $requestIt      = 0;
        foreach($attributeGroups as $agrp) {            
            $apiCallParams[$requestIt] = array(
                'name' => $agrp['name']
            );
            if(!array_key_exists($requestIt, $attrIdsOrder)) {
                $attrIdsOrder[$requestIt] = array();
            }
            
            $lineNr         = 1;
            $attributes     = AttributeGroup::getAttributes($id_lang, $agrp['id_attribute_group']);
            foreach($attributes as $attr) {
                $apiCallParams[$requestIt]['valueName' . $lineNr] = $attr['name'];
                $apiCallParams[$requestIt]['valueCode' . $lineNr] = $attr['name'];
                ++$lineNr;
                $attrIdsOrder[$requestIt][] = $attr['id_attribute'];
            }
            ++$requestIt;
        }
        
        for($i = 0; $i < $requestIt; $i++) {
            $res                = $api->callApiFunction('saveMatrixDimension', $apiCallParams[$i])->getRecords();
            $erply_id           = $res[0]['dimensionID'];
            $erplyMatrixRecords = $api->getMatrixDimensions(array('dimensionID' => $erply_id ));
            $erplyVariations    = $erplyMatrixRecords[0]['variations'];
            if(count($erplyVariations) != count($attrIdsOrder[$i])) {
                $output .= self::logAndReturn('Erply Matrix Dimension id: ' . $erply_id . '. recv != sent => ' . count($erplyVariations) . ' != ' . count($attrIdsOrder[$i]), 'ferr');
                $api->callApiFunction('deleteMatrixDimension', array(
                    'dimensionID' => $erply_id
                ));
            } else {
                $attributeGroupMapping              = new ErplyMapping();
                $attributeGroupMapping->erply_id    = $erply_id;
                $attributeGroupMapping->local_id    = $attributeGroups[$i]['id_attribute_group'];
                $attributeGroupMapping->object_type = 'ParentAttribute';
                $attributeGroupMapping->add();
                ErplyFunctions::log('saveMatrixDimension res: ' . $attributeGroupMapping->erply_id . ' presta id: ' . $attributeGroupMapping->local_id . ' name: ' . $attributeGroups[$i]['name']);
                
                $varIt = 0;
                foreach($erplyVariations as $variation) {
                    $attributeMapping              = new ErplyMapping();
                    $attributeMapping->erply_id    = $variation['variationID'];
                    $attributeMapping->local_id    = $attrIdsOrder[$i][$varIt++];
                    $attributeMapping->object_type = 'Attribute';
                    $attributeMapping->setInfo('parent', (int) $attributeGroups[$i]['id_attribute_group']);
                    $attributeMapping->setInfo('code', $variation['code']);
                    $attributeMapping->add();
                    ErplyFunctions::log('Save Erply Variation to local mapping. presta id: ' . $attributeMapping->local_id . ' erply id: ' . $attributeMapping->erply_id);
                }
            }
        }
        
        ErplyFunctions::log('End Attribute Export');
        return $output;
    }
    
    public static function exportSingleAttribute($idAttribute, $api = null)
    {
        $attributeMapping = ErplyMapping::getMapping('Attribute', 'local_id', $idAttribute);
        
        if($attributeMapping) {
            ErplyFunctions::log('Attribute exists in local mapping. Update is not supported');
            return;
        }
        
        if(!$api) {
            // Verify connection with erply
            $api = ErplyFunctions::getErplyApi();
            $api->VerifyUser();
            // Connection with erply OK
        }
        
        $prestaLocaleId = ErplyFunctions::getPrestaLocaleId();
        $attribute = new Attribute($idAttribute, $prestaLocaleId);
        
        if(!$attribute) {
            throw new Erply_Exception(ErplyFunctions::log('Attribute not found in mapping, id: ' . $idAttribute));
        }
        
        $idAttributeGroup = $attribute->id_attribute_group;
        $attributeGroupMapping = ErplyMapping::getMapping('ParentAttribute', 'local_id', $idAttributeGroup);
        
        if(!$attributeGroupMapping) {
            throw new Erply_Exception(ErplyFunctions::log('Attribute Group not found in mapping, id: ' . $idAttributeGroup . ' Attribute id:' . $idAttribute));
        }

        $request = array(
            'dimensionID' => $attributeGroupMapping->erply_id,
            'valueName1' => $attribute->name,
            'valueCode1' => $attribute->name
        );

        $res = $api->callApiFunction('saveMatrixDimension', $request)->getRecords();
        $erply_id           = $res[0]['dimensionID'];
        $erplyMatrixRecords = $api->getMatrixDimensions(array('dimensionID' => $erply_id ));
        $erplyVariations    = $erplyMatrixRecords[0]['variations'];
        
        $variationId = null;
        foreach($erplyVariations as $variation) {
            if($variation['code'] == $attribute->name) {
                $variationId = $variation['variationID'];
                break;
            }
        }
        
        if(!$variationId) {
            throw new Erply_Exception(ErplyFunctions::log('FATAL ERROR: Could not obtain variationId, Attribute Group id: ' . $idAttributeGroup . ' Attribute id:' . $idAttribute));
        }
    
        $newAttributeMapping              = new ErplyMapping();
        $newAttributeMapping->erply_id    = $variationId;
        $newAttributeMapping->local_id    = $idAttribute;
        $newAttributeMapping->object_type = 'Attribute';
        $newAttributeMapping->setInfo('parent', (int) $idAttributeGroup);
        $newAttributeMapping->setInfo('code', (int) $attribute->name);
        $newAttributeMapping->add();
        ErplyFunctions::log('Save exported Erply Variation to local mapping. presta id: ' . $newAttributeMapping->local_id . ' erply id: ' . $newAttributeMapping->erply_id);
        
    }
    
    public static function deleteMatrix($attributeGroupId, $api = false)
    {
        $output = '';
        if(!$api) {
            // Verify connection with erply
            $api = ErplyFunctions::getErplyApi();
            $api->VerifyUser();
            // Connection with erply OK
        }
        
        $id_lang = Configuration::get('PS_LANG_DEFAULT');
        
        $parentAttributeMapping = ErplyMapping::getMapping('ParentAttribute', 'local_id', $attributeGroupId);
        if($parentAttributeMapping) {
            try {
                $api->callApiFunction('deleteMatrixDimension', array('dimensionID' => $parentAttributeMapping->erply_id));
                $output .= self::logAndReturn('Attribute Group with id: ' . $attributeGroupId . ' delete from Erply successful.');
            }
            catch(Erply_Exception $e) {
                $attrGrp = new AttributeGroup($attributeGroupId, $id_lang);
                if($e->getData('code') == 1002) {
                    $output .= self::logAndReturn('Attribute Group ' . $attrGrp->name . ' delete failed. Maximum requests reached. No changes', 'warn');
                    $e-setData('output', $output);
                    throw $e;
                } else if($e->getData('code') == 1011) {
                    $output .= self::logAndReturn('Attribute Group ' . $attrGrp->name . ' was missing from Erply, but present in local mapping.', 'warn');
                } else {
                    $output .= self::logAndReturn('Attribute Group ' . $attrGrp->name . ' erply Error. code: '  . $e->getData('code') . ' message: ' . $e->getData('message') . '. No changes', 'warn');
                    $e-setData('output', $output);
                    throw $e;
                }
            }
            if(!ErplyMapping::deleteParentAndChildrenMappingsByPrestaId($attributeGroupId, 'Attribute')) {
                $output .= self::logAndReturn('Unable to delete Parent and/or Children mappings for Attribute Presta id: ' . $attributeGroupId);
            }
        } else {
            $output .= self::logAndReturn('Could not get ParentAttribute mapping by Prestashop Attribute Group Id: '.$attributeGroupId, 'err');
        }
        return $output;
    }
    
    public static function deleteVariation($idAttribute, $api = null) {
        if(!$api) {
            // Verify connection with erply
            $api = ErplyFunctions::getErplyApi();
            $api->VerifyUser();
            // Connection with erply OK
        }
        
        $attributeMapping = ErplyMapping::getMapping('Attribute', 'local_id', $idAttribute);
        $idAttributeGroup = $attributeMapping->getInfo('parent');
        $attributeGroupMapping = ErplyMapping::getMapping('ParentAttribute', 'local_id', $idAttributeGroup);
        
        if(!$attributeMapping || !$attributeGroupMapping) {
            throw new Erply_Exception(ErplyFunctions::log('FATAL ERROR: mapping not found. Attribute id: ' . $idAttribute));
        }
        
        $erplyMatrixId = $attributeGroupMapping->erply_id;
        $erplyVariationId = $attributeMapping->erply_id;
        
        $request = array(
            'dimensionID' => $erplyMatrixId,
            'variationID1' => $erplyVariationId
        );
        
        $api->callApiFunction('removeItemsFromMatrixDimension', $request);
        
        $attributeMapping->delete();
    }
}