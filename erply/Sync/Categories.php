<?php
require_once(_PS_MODULE_DIR_ . '/erply/Sync/Abstract.php');
require_once(_PS_MODULE_DIR_ . '/erply/ErplyFunctions.class.php');

class Erply_Sync_Categories extends Erply_Sync_Abstract
{
    protected static $_erplyChangedCategories;
    protected static $_erplyChangedCategoriesIds;
    protected static $_prestaChangedCategories;
    protected static $_prestaChangedCategoriesIds;
    
    
    /**
     * Sync all Categories both ways.
     * 
     * @return integer - total categories synchronized
     */
    public static function syncAll($ignoreLastTimestamp = false)
    {
        $output = '';
        $output .= self::importAll($ignoreLastTimestamp);
        $output .= self::exportAll($ignoreLastTimestamp);
        
        // Set now as last sync time.
        return $output;
    }
    
    /**
     * Import all ERPLY categories.
     * 
     * @return integer - Nr of categories imported.
     */
    public static function importAll($ignoreLastTimestamp = false)
    {
        ErplyFunctions::log('Start Category Import');
        $output = '';
        
        // Get object priority
        $objectPriority = self::getObjectPriority();
        
        // Get all ERPLY Categories chaned since last sync.
        $erplyChangedCategoriesAry = self::getErplyChangedCategories();
        
        // Get all Presta Categories changed since last sync.
        $prestaChangedCategoriesAry = self::getPrestaChangedCategories();
        
        if(!count($prestaChangedCategoriesAry)) {
            $output .= self::logAndReturn('No category changes since last sync', 'warn');
        }
        
        foreach($erplyChangedCategoriesAry as $erplyCategory) {
            // Find mapping
            $mappingObj = self::getCategoryMapping('erply_id', $erplyCategory['productGroupID']);
            
            // Mapping exists (Category IS in sync), update Category.
            if(!is_null($mappingObj)) {
                // Check if same Category has also changed in Presta
                if(in_array($mappingObj->getPrestaId(), self::$_prestaChangedCategoriesIds)) {
                    // Category has changed both in ERPLY and in Presta.
                    // Check priority.
                    if($objectPriority == 'ERPLY') {
                        // Override Presta changes with ERPLY
                        if(self::updatePrestaCategory($mappingObj->getPrestaId(), $erplyCategory)) {
                            $output .= self::logAndReturn('Override Presta Category with erply data. Presta Category id: ' . $mappingObj->getPrestaId() . ' name: ' . $erplyCategory['name']);
                        } else {
                            $output .= self::logAndReturn('Override Presta Category with erply data FAILED. Presta Category id: ' . $mappingObj->getPrestaId() . ' name: ' . $erplyCategory['name']);
                        }
                    }
                    // else do nothing
                } else {
                    // Category has not changed in Presta so update with ERPLY data.
                    if(self::updatePrestaCategory($mappingObj->getPrestaId(), $erplyCategory)) {
                        $output .= self::logAndReturn('Update Presta Category with erply data. Presta Category id: ' . $mappingObj->getPrestaId() . ' name: ' . $erplyCategory['name']);
                    } else {
                        $output .= self::logAndReturn('Update Presta Category with erply data FAILED. Presta Category id: ' . $mappingObj->getPrestaId() . ' name: ' . $erplyCategory['name']);
                    }
                }
            } else {
                // Mapping not found (Category NOT in sync), create new Category.
                
                // Create category
                if(self::createPrestaCategory($erplyCategory)) {
                    $output .= self::logAndReturn('Create Presta Category with erply data. Name: ' . $erplyCategory['name']);
                } else {
                    $output .= self::logAndReturn('Create Presta Category with erply data FAILED. Name: ' . $erplyCategory['name']);
                }
            }
        }
        
        ErplyFunctions::log('End Category Import');
        return $output;
    }
    
    /**
     * Export all Presta categories.
     * 
     * @return integer - Nr of categories exported.
     */
    public static function exportAll($ignoreLastTimestamp = false)
    {
        ErplyFunctions::log('Start Category Export.');
        $output = '';
        
        // Get object priority
        $objectPriority = self::getObjectPriority();
        
        // Get all Presta Categories changed since last sync.
        $prestaChangedCategoriesAry = self::getPrestaChangedCategories();
        
        // Get all ERPLY Categories chaned since last sync.
        $erplyChangedCategoriesAry = self::getErplyChangedCategories();
        
        foreach($prestaChangedCategoriesAry as $prestaCategory) {
            // Find mapping
            $mappingObj = self::getCategoryMapping('local_id', $prestaCategory['id_category']);
            
            // Mapping found, Category IS in sync
            if(!is_null($mappingObj)) {
                // Check if same Category has also changed in ERPLY
                if(in_array($mappingObj->getErplyId(), self::$_erplyChangedCategoriesIds)) {
                    // If object priority is Presta then export
                    if($objectPriority == 'Presta') {
                        // Update ERPLY category
                        if(self::updateErplyCategory($prestaCategory, $mappingObj)) {
                            $output .= self::logAndReturn('Override erply Category with Presta data. Presta Category id: ' . $mappingObj->getPrestaId() . ' name: ' . $prestaCategory['name']);
                        }
                    } // else do nothing
                } else {
                    // Category only changed in Presta, export.
                    
                    // Update ERPLY category
                    if(self::updateErplyCategory($prestaCategory, $mappingObj)) {
                        $output .= self::logAndReturn('Update erply Category with Presta Category data. Presta Category id: ' . $mappingObj->getPrestaId() . ' name: ' . $prestaCategory['name']);
                    }
                }
            } else {
                // Mapping not found, Category NOT in sync.
                
                // Create new ERPLY Category
                if(self::createErplyCategory($prestaCategory)) {
                    $output .= self::logAndReturn('Create erply Category with Presta Category data. Presta Category id: ' . $prestaCategory['id_category'] . ' name: ' . $prestaCategory['name']);
                }
            }
            
            // Update last sync TS
            ErplyFunctions::setLastSyncTS('PRESTA_CATEGORIES', strtotime($prestaCategory['date_upd']));
        }
        
        ErplyFunctions::log('End Category Export.');
        return $output;
    }
    
    /**
     * Update Presta Category with ERPLY category data.
     * 
     * @param integer | Category $prestaCategory
     * @param array $erplyCategory
     * @return Category - Presta Category
     */
    private static function updatePrestaCategory($prestaCategory, $erplyCategory)
    {
        $localeId = ErplyFunctions::getPrestaLocaleId();
        
        // Load Presta Category if ID presented.
        if(!is_object($prestaCategory)) {
            $prestaCategory = new Category($prestaCategory);
        }
        
        ErplyFunctions::log('Updating Presta Category. Name: ' . $prestaCategory->name[$localeId]);
        
        // Update Presta parent id. If not found then leave unchanged.
        if((int) $erplyCategory['parentGroupID'] > 0) {
            $parentMappingObj = self::getCategoryMapping('erply_id', $erplyCategory['parentGroupID']);
            if(!is_null($parentMappingObj)) {
                $prestaCategory->id_parent = $parentMappingObj->getPrestaId();
            }
        } else {
            // In root category.
            $prestaCategory->id_parent = self::getPrestaRootCategoryId();
        }
        
        $prestaCategory->name[$localeId]         = $erplyCategory['name'];
        $prestaCategory->active                  = (int) $erplyCategory['showInWebshop'];
        $prestaCategory->link_rewrite[$localeId] = Tools::link_rewrite(self::hideCategoryPosition($prestaCategory->name[$localeId]));
        $prestaCategory->update();
        
        return $prestaCategory;
    }
    
    /**
     * Create Presta Category based on ERPLY data.
     * 
     * @param array $erplyCategory
     * @return array
     */
    private static function createPrestaCategory($erplyCategory)
    {
        ErplyFunctions::log('Creating Presta Category. Name: ' . $erplyCategory['name']);
        
        $localeId = ErplyFunctions::getPrestaLocaleId();
        
        // Create new Presta Category.
        $prestaCategory = new Category(null, $localeId);
        
        // Find Presta parent id.
        $prestaParentId = self::getPrestaRootCategoryId();
        if((int) $erplyCategory['parentGroupID'] > 0) {
            $parentMappingObj = self::getCategoryMapping('erply_id', $erplyCategory['parentGroupID']);
            if(!is_null($parentMappingObj)) {
                $prestaParentId = $parentMappingObj->local_id;
            }
        }
        $prestaCategory->id_parent = $prestaParentId;
        
        $name                   = self::prestaSafeName($erplyCategory['name']);
        $prestaCategory->name   = self::createMultiLangField($name);
        $prestaCategory->active = (int) $erplyCategory['showInWebshop'];
        
        $linkRewrite                  = $prestaCategory->name[$localeId];
        $linkRewrite                  = self::hideCategoryPosition($linkRewrite);
        $linkRewrite                  = Tools::link_rewrite($linkRewrite);
        $prestaCategory->link_rewrite = self::createMultiLangField($linkRewrite);
        
        //ErplyFunctions::debug('$prestaCategory', $prestaCategory); exit;
        
        if($prestaCategory->add()) {
            // Create mapping
            $mappingObj = self::createCategoryMapping($prestaCategory->id, $erplyCategory['productGroupID']);
            return array(
                $prestaCategory,
                $mappingObj
            );
        }
        
        return false;
    }
    
    /**
     * Update ERPLY product group.
     * 
     * @param array $prestaCategory
     * @param ErplyMapping $mapping
     * @return array - ERPLY product group
     */
    private static function updateErplyCategory($prestaCategory, $mapping)
    {
        ErplyFunctions::log('Updating ERPLY Category. Name: ' . $prestaCategory['name']);
        
        $erplyCategory = array();
        
        // ID
        $erplyCategory['productGroupID'] = $mapping->getErplyId();
        
        // Get ERPLY id for parent group.
        // Not in root
        if((int) $prestaCategory['id_parent'] !== self::getPrestaRootCategoryId()) {
            $categoryMapping = self::getCategoryMapping('local_id', $prestaCategory['id_parent']);
            if(!is_null($categoryMapping)) {
                // Parent category IN in sync.
                $erplyCategory['parentGroupID'] = $categoryMapping->getErplyId();
            } else {
                // Parent category IS NOT in sync.
                return false;
            }
        } else {
            // Category in root.
            $erplyCategory['parentGroupID'] = 0;
        }
        
        // Name
        // $nameField = 'name'.strtoupper(ErplyFunctions::getErplyLocaleCode());
        $nameField                 = 'name';
        $erplyCategory[$nameField] = $prestaCategory['name'];
        
        // Save
        try {
            ErplyFunctions::getErplyApi()->saveProductGroup($erplyCategory);
        }
        catch(Erply_Exception $e) {
            $output .= Utils::getErrorHtml($e);
        }
        
        return $erplyCategory;
    }
    
    /**
     * @param array $prestaCategory
     * @return array - array( array $erplyCategory, ErplyMapping $mappingObj ) 
     */
    private static function createErplyCategory($prestaCategory)
    {
        //we do not import the root category
        if((int) $prestaCategory['id_category'] === self::getPrestaRootCategoryId()) {
            ErplyFunctions::log('Create ERPLY Category skipped. Root category: ' . $prestaCategory['name']);
            return false;
        }
        
        ErplyFunctions::log('Creating ERPLY Category. Name: ' . $prestaCategory['name']);
        
        $erplyCategory = array();
        
        // Get ERPLY id for parent group.
        // Not in root
        if((int) $prestaCategory['id_parent'] !== self::getPrestaRootCategoryId()) {
            $categoryMapping = self::getCategoryMapping('local_id', $prestaCategory['id_parent']);
            if(!is_null($categoryMapping)) {
                // Parent category IN in sync.
                $erplyCategory['parentGroupID'] = $categoryMapping->getErplyId();
            } else {
                // Parent category IS NOT in sync.
                ErplyFunctions::log('Parent category not in sync. Create ERPLY Category canceled');
                return false;
            }
        } else {
            // Category in root.
            $erplyCategory['parentGroupID'] = 0;
        }
        
        // Name
        // $nameField = 'name'.strtoupper(ErplyFunctions::getErplyLocaleCode());
        $erplyCategory['name'] = $prestaCategory['name'];
        
        // Create
        $erplyCategory['productGroupID'] = ErplyFunctions::getErplyApi()->saveProductGroup($erplyCategory);
        
        // Create mapping
        if((int) $prestaCategory['id_parent'] !== self::getPrestaRootCategoryId()) {
            $mappingObj = self::createCategoryMapping($prestaCategory['id_category'], $erplyCategory['productGroupID'], $prestaCategory['id_parent']);
        } else {
            $mappingObj = self::createCategoryMapping($prestaCategory['id_category'], $erplyCategory['productGroupID']);
        }
        
        return true;
    }
    
    public static function deleteCategory($categoryId, $api = false) 
    {
        $output = '';
        if(!$api) {
            // Verify connection with erply
            $api = ErplyFunctions::getErplyApi();
            $api->VerifyUser();
            // Connection with erply OK
        }
        
        $id_lang = Configuration::get('PS_LANG_DEFAULT');
        
        $categoryMapping = ErplyMapping::getMapping('Category', 'local_id', $categoryId);
        if($categoryMapping) {
            try {
                $output .= self::logAndReturn($api->deleteCategory($categoryMapping->erply_id));
                $output .= self::logAndReturn('If Category has sub-categories, they were removed from Erply, but not from local mapping', 'warn');
            }
            catch(Erply_Exception $e) {
                $category = new Category($categoryId, $id_lang);
                if($e->getData('code') == 1002) {
                    $output .= self::logAndReturn('Category ' . $category->name . ' delete failed. Maximum requests reached. No changes', 'warn');
                    $e-setData('output', $output);
                    throw $e;
                } else if($e->getData('code') == 1011) {
                    $output .= self::logAndReturn('Category ' . $category->name . ' was missing from Erply, but present in local mapping.', 'warn');
                } else {
                    $output .= self::logAndReturn('Category ' . $category->name . ' erply Error. code: '  . $e->getData('code') . ' message: ' . $e->getData('message'), 'warn');
                    $e-setData('output', $output);
                    throw $e;
                }
            }
            
            if(!$categoryMapping->delete()) {
                $output .= self::logAndReturn('Delete Category mapping failed. Id: ' . $categoryId, 'warn');
            }
        } else {
            $output .= self::logAndReturn('Could not get Category mapping by Prestashop Category Id: ' . $categoryId, 'err');
        }
        
        
        return $output;
    }
    
    /**
     * Get all ERPLY products chaned since last sync.
     * 
     * @return array
     */
    public static function getErplyChangedCategories()
    {
        // Load changed categories.
        if(is_null(self::$_erplyChangedCategories)) {
            // Init changed categories
            self::$_erplyChangedCategories    = array();
            self::$_erplyChangedCategoriesIds = array();
            
            $apiResp = ErplyFunctions::getErplyApi()->callApiFunction('getProductGroups');
            foreach($apiResp->getRecords() as $erplyCategory) {
                self::_makeErplyChangedCategoriesRecursive($erplyCategory);
            }
            
            // Update last sync TS
            ErplyFunctions::setLastSyncTS('ERPLY_CATEGORIES', $apiResp->getStatus('requestUnixTime'));
        }
        
        return self::$_erplyChangedCategories;
    }
    
    /**
     * @param array $erplyCategory
     * @param integer $parentCategoryId
     * @return void
     */
    private static function _makeErplyChangedCategoriesRecursive($erplyCategory, $parentCategoryId = null)
    {
        // Inport only changed categories.
        // We cannot check lastModified because when moving category
        // from one parent to another, lastModified does not get updated.
        //		$lastSyncTS = ErplyFunctions::getLastSyncTS('ERPLY_CATEGORIES');
        //		if($erplyCategory['added'] > $lastSyncTS || $erplyCategory['lastModified'] > $lastSyncTS)
        //		{
        $category = $erplyCategory;
        unset($category['subGroups']);
        $category['parentGroupID']          = $parentCategoryId;
        self::$_erplyChangedCategories[]    = $category;
        self::$_erplyChangedCategoriesIds[] = $category['productGroupID'];
        //		}
        
        // Handle subcategories.
        if(!empty($erplyCategory['subGroups']) && is_array($erplyCategory['subGroups'])) {
            foreach($erplyCategory['subGroups'] as $subCategory) {
                self::_makeErplyChangedCategoriesRecursive($subCategory, $erplyCategory['productGroupID']);
            }
        }
    }
    
    /**
     * Get array of Presta Categories that have changed since last sync.
     * 
     * @return array
     */
    public static function getPrestaChangedCategories()
    {
        if(is_null(self::$_prestaChangedCategories)) {
            // Init changed categories
            self::$_prestaChangedCategories    = array();
            self::$_prestaChangedCategoriesIds = array();
            
            $lastSyncTS    = ErplyFunctions::getLastSyncTS('PRESTA_CATEGORIES');
            $categoriesAry = self::_getPrestaCategoryChildren(self::getPrestaRootCategoryId(), true);
            foreach($categoriesAry as $category) {
                if(strtotime($category['date_upd']) > $lastSyncTS) {
                    self::$_prestaChangedCategories[]    = $category;
                    self::$_prestaChangedCategoriesIds[] = $category['id_category'];
                }
            }
        }
        return self::$_prestaChangedCategories;
    }
    
    /**
     * Get Presta Category subcategoryes.
     * 
     * @param integer $parentId
     * @param boolean $recursive
     * @return array
     */
    private static function _getPrestaCategoryChildren($parentId, $recursive = true)
    {
        $returnAry = array();
        $sql       = '
SELECT *
FROM `' . _DB_PREFIX_ . 'category` c
LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON c.`id_category` = cl.`id_category`
WHERE 
	cl.`id_lang` = ' . intval(ErplyFunctions::getPrestaLocaleId()) . '
	AND c.`id_parent` = ' . intval($parentId) . '
ORDER BY cl.`name` ASC';
        
        $childrenAry = Db::getInstance()->ExecuteS($sql);
        foreach($childrenAry as $childAry) {
            $returnAry[] = $childAry;
            
            // Get subchildren recursively
            if($recursive === true) {
                $subchildrenAry = self::_getPrestaCategoryChildren($childAry['id_category'], true);
                foreach($subchildrenAry as $subchildAry) {
                    $returnAry[] = $subchildAry;
                }
            }
        }
        
        return $returnAry;
    }
    
    static private function hideCategoryPosition($name)
    {
        return preg_replace('/^[0-9]+\./', '', $name);
    }
    
    static private function getPrestaRootCategoryId()
    {
        $val = Configuration::get('ERPLY_PRESTA_ROOT_CATEGORY_ID');
        return !empty($val) ? (int) $val : 1;
    }
    
}

?>