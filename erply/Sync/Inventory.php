<?php
include_once(_PS_MODULE_DIR_ . '/erply/Sync/Abstract.php');
include_once(_PS_MODULE_DIR_ . '/erply/ErplyFunctions.class.php');

class Erply_Sync_Inventory extends Erply_Sync_Abstract
{
    protected static $prestaChangedProductsIds;
    
    public static function syncAll($ignoreLastTimestamp = false)
    {
        return self::logAndReturn('Inventory syncAll not implemented');
    }
    
    public static function importAll($ignoreLastTimestamp = false)
    {
        return self::logAndReturn('Inventory importAll not implemented');
    }
    
    public static function exportAll($ignoreLastTimestamp = false)
    {
        ErplyFunctions::log('Start Inventory Export');
        $output = '';
        
        // Get warehouse location
        $warehouseId = Configuration::get('ERPLY_WAREHOUSE');
        if(!$warehouseId) {
            return Utils::logAndReturn('Warehouse location not set', 'err');
        }
        $requestParams = array(
            'warehouseID' => $warehouseId,
            'confirmed' => 0,
            'cause' => 'Inventory Export from Prestashop'
        );
        
        // Get Product Mappings
        $productIdsPair = ErplyMapping::getMappingIdsPair('Product');
        if(!count($productIdsPair)) {
            return Utils::logAndReturn('No Products in local mapping', 'warn');
        }
        
        $changedProductIds = self::getPrestaChangedProductsIds();
        
        // Generate Document lines
        $prestaLocaleId = ErplyFunctions::getPrestaLocaleId();
        $it             = 0;
        $syncTs         = 0;
        foreach($productIdsPair as $pair) {
            $productMapping = ErplyMapping::getMapping('Product', 'local_id', $pair['local_id']);
            $parentId       = $productMapping->getInfo('parent');
            
            if(FALSE !== array_search($parentId, $changedProductIds) && (int) $productMapping->getInfo('quantity') > 0) {
                $output .= self::logAndReturn('Adding to Inventory Registration. Parent product id:' . $parentId . ' id_attribute_group: ' . $pair['local_id']);
                $requestParams['productID' . $it] = $pair['erply_id'];
                $requestParams['amount' . $it]    = $productMapping->getInfo('quantity');
                $it++;
                if($syncTs < $productMapping->getInfo('date_upd')) {
                    $syncTs = $productMapping->getInfo('date_upd');
                }
            }
        }
        
        if(!$it) {
            return self::logAndReturn('No changes since last Inventory Registration');
        }
        
        try {
            $api = ErplyFunctions::getErplyApi();
            $api->saveInventoryRegistration($requestParams);
            
            if($syncTs) {
                ErplyFunctions::setLastSyncTS('PRESTA_INVENTORY', $syncTs);
            }
        }
        catch(Erply_Exception $e) {
            $output .= self::logAndReturn(Utils::getErrorText($e));
        }
        
        return $output;
    }
    
    public static function exportSingle($local_id, $quantity) 
    {
        ErplyFunctions::log('Inventory Update for local_id: ' . $local_id);
        $output = '';
        
        if(!is_int($quantity) || $quantity < 0) {
            throw new Erply_Exception(ErplyFunctions::log('Invalid quantity for local_id: ' . $local_id . ' qunatity: ' . $quantity, 'err'));
        }
        
        // Get warehouse location
        $warehouseId = Configuration::get('ERPLY_WAREHOUSE');
        if(!$warehouseId) {
            throw new Erply_Exception(ErplyFunctions::log('Warehouse location not set'));
        }
        
        // Get Product Mappings
        $productIdsPair = ErplyMapping::getMappingIdsPair('Product', $local_id);
        if(!count($productIdsPair)) {
            throw new Erply_Exception(ErplyFunctions::log('No Products in local mapping'));
        }
        
        $pair = $productIdsPair[0];
        $productMapping = ErplyMapping::getMapping('Product', 'local_id', $pair['local_id']);
        $parentId       = $productMapping->getInfo('parent');
        $parentProduct  = new Product($parentId);
        $previousQuantity = $productMapping->getInfo('quantity');

        if($previousQuantity < $quantity) {
            $output .= self::logAndReturn('Adding to Inventory Registration. Parent Product id:' . $parentId . ' id_attribute_group: ' . $pair['local_id'] . ' prev q: ' . $previousQuantity . ' q: ' . $quantity);
            $requestParams = array(
                'warehouseID' => $warehouseId,
                'confirmed' => 1,
                'cause' => 'Inventory Update from Prestashop',
                'productID1' => $pair['erply_id'],
                'amount1' => ($quantity - $previousQuantity)
            );
            
            try {
                $api = ErplyFunctions::getErplyApi();
                $api->saveInventoryRegistration($requestParams);
                // ErplyFunctions::setLastSyncTS('PRESTA_INVENTORY', $syncTs); // Q: SYNC TS
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(Utils::getErrorText($e));
                throw $e;
            }
        } else if ($previousQuantity > $quantity) {
            $output .= self::logAndReturn('Adding to Inventory Write-Off. Parent Product id:' . $parentId . ' id_attribute_group: ' . $pair['local_id'] . ' prev q: ' . $previousQuantity . ' q: ' . $quantity);
            $requestParams = array(
                'warehouseID' => $warehouseId,
                'confirmed' => 1,
                'reasonID' => self::reason(),
                'productID1' => $pair['erply_id'],
                'amount1' => ($previousQuantity - $quantity)
            );
            
            try {
                $api = ErplyFunctions::getErplyApi();
                $api->saveInventoryWriteOff($requestParams);
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(Utils::getErrorText($e));
                throw $e;
            }
        } else {
            $output .= self::logAndReturn('Inventory Update No changes', 'warn');
            return $output;
        }
        
        // update quantity in product mapping (for no reason atm)
        $info = $productMapping->getInfo();
        $info['quantity'] = $quantity;
        $info['date_upd'] = strtotime($parentProduct->date_upd); // Q: DATE UPD
        $productMapping->setInfo($info);
        $productMapping->update();
        
        return $output;
    }
    
    protected static function reason() 
    {
        $api = ErplyFunctions::getErplyApi();
        $resp = $api->getReasonCodes(array('purpose' => 'WRITEOFF'));
        
        foreach($resp as $r) {
            if($r['name'] == 'prestashop') {
                return $r['reasonID'];
            }
        }
        
        if(!count($resp)) {
            ErplyFunctions::log('Reasons request returned empty');
            throw new Erply_Exception('Reasons request returned empty');
        }
        
        return $resp[0]['reasonID'];
    }
    
    /**
     * Get array of Presta Products that have changed since last sync.
     * 
     * @return array
     */
    private static function getPrestaChangedProductsIds()
    {
        if(is_null(self::$prestaChangedProductsIds)) {
            // Init
            self::$prestaChangedProductsIds = array();
            
            $lastSyncTS = ErplyFunctions::getLastSyncTS('PRESTA_INVENTORY');
            $sql        = '
SELECT p.`id_product`  
FROM `' . _DB_PREFIX_ . 'product` p 
WHERE 
    UNIX_TIMESTAMP(p.`date_add`) > ' . intval($lastSyncTS) . ' 
    OR UNIX_TIMESTAMP(p.`date_upd`) > ' . intval($lastSyncTS) . '
ORDER BY p.`date_upd` ASC';
            
            $productsAry = Db::getInstance()->ExecuteS($sql);
            if(is_array($productsAry)) {
                foreach($productsAry as $productAry) {
                    self::$prestaChangedProductsIds[] = $productAry['id_product'];
                }
            }
        }
        return self::$prestaChangedProductsIds;
    }
}