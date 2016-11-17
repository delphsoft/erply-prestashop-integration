<?php
require_once(_PS_MODULE_DIR_ . '/erply/Sync/Abstract.php');
require_once(_PS_MODULE_DIR_ . '/erply/ErplyFunctions.class.php');
require_once(_PS_MODULE_DIR_ . '/erply/Presta/Utils.php');
require_once(_PS_MODULE_DIR_ . '/erply/Sync/CustomerGroups.php');
require_once(_PS_MODULE_DIR_ . '/erply/Sync/CustomerAddresses.php');

class Erply_Sync_Customers extends Erply_Sync_Abstract
{
    private static $_erplyChangedCustomersIds = array();
    private static $_prestaChangedCustomersIds;
    
    
    /**
     * Sync all Customers.
     * 1. Import
     * 2. Export
     * 
     * @return integer - combined nr of customers synced
     */
    public static function syncAll($ignoreLastTimestamp = false)
    {
        $output = '';
        $output .= self::importAll($ignoreLastTimestamp);
        $output .= self::exportAll($ignoreLastTimestamp);
        return $output;
    }
    
    /**
     * We dont import customers to Presta. We only export Presta customers to ERPLY
     * 
     * @return integer - nr of object imported.
     */
    public static function importAll($ignoreLastTimestamp = false)
    {
        return Utils::displayWarning('Customer import not supported');
    }
    
    /**
     * Export all Customers to ERPLY that have chaned or created since last sync.
     * 
     * @return integer - nr of groups exported
     */
    public static function exportAll($ignoreLastTimestamp = false)
    {
        ErplyFunctions::log('Start Customer Export.');
        
        $output = '';
        foreach(self::getPrestaChangedCustomersIds() as $prestaCustomerId) {
            $prestaCustomerObj = new Customer($prestaCustomerId);
            
            if(($mappingObj = self::getCustomerMapping('local_id', $prestaCustomerId))) {
                // Update ERPLY customer. Presta customer data has always higher priority
                // than ERPLYs because customer itself is responsible for keeping it up to date.
                if(self::updateErplyCustomer($prestaCustomerObj, $mappingObj)) {
                    $name = $prestaCustomerObj->firstname . ' ' . $prestaCustomerObj->lastname;
                    $output .= Utils::displayConfirmation('Update erply Customer with Presta Customer data. Presta Customer id: ' . $mappingObj->getPrestaId() . ' name: ' . $name);
                }
            } else {
                // Object not in sync. Create in ERPLY.
                if(($resp = self::createErplyCustomer($prestaCustomerObj))) {
                    $name = $prestaCustomerObj->firstname . ' ' . $prestaCustomerObj->lastname;
                    $output .= Utils::displayConfirmation('Create erply Customer with Presta Customer data. Name: ' . $name);
                }
            }
            
            // Update last sync TS
            ErplyFunctions::setLastSyncTS('PRESTA_CUSTOMERS', strtotime($prestaCustomerObj->date_upd));
        }
        
        ErplyFunctions::log('End Customer Export.');
        
        return $output;
    }
    
    /**
     * @param Customer $prestaCustomerObj
     * @return array - array( array $erplyCustomerAry, ErplyMapping $mappingObj )
     */
    public static function createErplyCustomer($prestaCustomerObj)
    {
        $prestaLocaleId   = ErplyFunctions::getPrestaLocaleId();
        $erplyCustomerAry = array();
        
        ErplyFunctions::log('Creating ERPLY Customer. Name: ' . $prestaCustomerObj->firstname . ' ' . $prestaCustomerObj->lastname);
        
        // Name
        $erplyCustomerAry['firstName'] = $prestaCustomerObj->firstname;
        $erplyCustomerAry['lastName']  = $prestaCustomerObj->lastname;
        
        // Customer group
        if(($groupMappingObj = self::getCustomerGroupMapping('local_id', $prestaCustomerObj->id_default_group))) {
            $erplyCustomerAry['groupID'] = $groupMappingObj->getErplyId();
        }
        
        // E-mail
        if(!empty($prestaCustomerObj->email)) {
            $erplyCustomerAry['email'] = $prestaCustomerObj->email;
        }
        
        // Birthday
        if(!empty($prestaCustomerObj->birthday)) {
            $erplyCustomerAry['birthday'] = date('Y-m-d', strtotime($prestaCustomerObj->birthday));
        }
        
        // Save
        $apiResp                        = ErplyFunctions::getErplyApi()->callApiFunction('saveCustomer', $erplyCustomerAry);
        $firstRecAry                    = $apiResp->getFirstRecord();
        $erplyCustomerAry['customerID'] = $firstRecAry['customerID'];
        
        // Create mapping
        $mappingObj              = new ErplyMapping();
        $mappingObj->object_type = 'Customer';
        $mappingObj->local_id    = $prestaCustomerObj->id;
        $mappingObj->erply_id    = $erplyCustomerAry['customerID'];
        $mappingObj->add();
        
        return array(
            $erplyCustomerAry,
            $mappingObj
        );
    }
    
    /**
     * @param Customer $prestaCustomerObj
     * @param ErplyMapping $mappingObj
     * @return boolean
     */
    public static function updateErplyCustomer($prestaCustomerObj, $mappingObj)
    {
        ErplyFunctions::log('Updating ERPLY Customer. Name: ' . $prestaCustomerObj->firstname . ' ' . $prestaCustomerObj->lastname);
        
        $prestaLocaleId   = ErplyFunctions::getPrestaLocaleId();
        $erplyCustomerAry = array();
        
        // ID
        $erplyCustomerAry['customerID'] = $mappingObj->getErplyId();
        
        // Name
        $erplyCustomerAry['firstName'] = $prestaCustomerObj->firstname;
        $erplyCustomerAry['lastName']  = $prestaCustomerObj->lastname;
        
        // Customer group
        if(($groupMappingObj = self::getCustomerGroupMapping('local_id', $prestaCustomerObj->id_default_group))) {
            $erplyCustomerAry['groupID'] = $groupMappingObj->getErplyId();
        }
        
        // E-mail
        if(!empty($prestaCustomerObj->email)) {
            $erplyCustomerAry['email'] = $prestaCustomerObj->email;
        }
        
        // Birthday
        if(!empty($prestaCustomerObj->birthday)) {
            $erplyCustomerAry['birthday'] = date('Y-m-d', strtotime($prestaCustomerObj->birthday));
        }
        
        // Save
        ErplyFunctions::getErplyApi()->callApiFunction('saveCustomer', $erplyCustomerAry);
        return true;
    }
    
    /**
     * @param integer $lastModifiedTS - unix_timestamp (DEPRECATED)
     * @return array
     */
    private static function getPrestaChangedCustomersIds($lastModifiedTS = null)
    {
        if(is_null(self::$_prestaChangedCustomersIds)) {
            // Init
            self::$_prestaChangedCustomersIds = array();
            
            // Load groups that have changed.
            $lastSyncTS = ErplyFunctions::getLastSyncTS('PRESTA_CUSTOMERS');
            $sql        = '
SELECT `id_customer`
FROM `' . _DB_PREFIX_ . 'customer`
WHERE UNIX_TIMESTAMP(`date_upd`) > ' . $lastSyncTS . ' 
ORDER BY `date_upd` ASC';
            
            $dbResp = Db::getInstance()->ExecuteS($sql);
            if($dbResp) {
                foreach($dbResp as $item) {
                    self::$_prestaChangedCustomersIds[] = $item['id_customer'];
                }
            }
        }
        
        return self::$_prestaChangedCustomersIds;
    }
}

?>