<?php
require_once(_PS_MODULE_DIR_ . '/erply/ErplyFunctions.class.php');
require_once(_PS_MODULE_DIR_ . '/erply/Presta/ErplyMapping.php');
require_once(_PS_MODULE_DIR_ . '/erply/Presta/Utils.php');

require_once(dirname(__FILE__) . '/Sync/Customers.php');
require_once(dirname(__FILE__) . '/Sync/CustomerAddresses.php');
require_once(dirname(__FILE__) . '/Sync/CustomerGroups.php');
require_once(dirname(__FILE__) . '/Sync/Categories.php');
require_once(dirname(__FILE__) . '/Sync/Attributes.php');
require_once(dirname(__FILE__) . '/Sync/Products.php');
require_once(dirname(__FILE__) . '/Sync/OrderHistory.php');
require_once(dirname(__FILE__) . '/Sync/Orders.php');
require_once(dirname(__FILE__) . '/Sync/Warehouses.php');
require_once(dirname(__FILE__) . '/Sync/Inventory.php');


class Erply_Sync
{
    /**
     * Import all ERPLY data
     * 
     * @param bool $ignoreLastTimestamp
     * @return boolean
     */
    public static function importAll($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_CustomerGroups::importAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Customers::importAll($ignoreLastTimestamp);
            $output .= Erply_Sync_CustomerAddresses::importAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Categories::importAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Products::importAll($ignoreLastTimestamp);
            // Erply_Sync_Orders::importAll($ignoreLastTimestamp);
            // Erply_Sync_OrderHistory::importAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Inventory::importAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    /**
     * Export all Presta data
     * 
     * @param bool $ignoreLastTimestamp
     * @return boolean
     */
    public static function exportAll($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_CustomerGroups::exportAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Customers::exportAll($ignoreLastTimestamp);
            $output .= Erply_Sync_CustomerAddresses::exportAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Categories::exportAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Attributes::exportAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Products::exportAll($ignoreLastTimestamp);
            // Erply_Sync_Orders::exportAll($ignoreLastTimestamp);
            // Erply_Sync_OrderHistory::exportAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Inventory::exportAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return $output . Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    /**
     * Sync both ways.
     * 
     * @param bool $ignoreLastTimestamp
     * @return boolean
     */
    public static function syncAll($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_CustomerGroups::syncAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Customers::syncAll($ignoreLastTimestamp);
            $output .= Erply_Sync_CustomerAddresses::syncAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Categories::syncAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Products::syncAll($ignoreLastTimestamp);
            Erply_Sync_Orders::syncAll($ignoreLastTimestamp);
            Erply_Sync_OrderHistory::syncAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Inventory::syncAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return $output . Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function importCustomers($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_CustomerGroups::importAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Customers::importAll($ignoreLastTimestamp);
            $output .= Erply_Sync_CustomerAddresses::importAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return $output . Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function exportCustomers($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_CustomerGroups::exportAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Customers::exportAll($ignoreLastTimestamp);
            $output .= Erply_Sync_CustomerAddresses::exportAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return $output . Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function syncCustomers($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_CustomerGroups::syncAll($ignoreLastTimestamp);
            $output .= Erply_Sync_Customers::syncAll($ignoreLastTimestamp);
            $output .= Erply_Sync_CustomerAddresses::syncAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return $output . Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function importCategories($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_Categories::importAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function exportCategories($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_Categories::exportAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function syncCategories($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_Categories::syncAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function importAttributes($ignoreLastTimestamp = false)
    {
        return Utils::returnNotImplementedHtml('Only attribute export is supported');
    }
    
    public static function exportAttributes($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_Attributes::exportAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function syncAttributes($ignoreLastTimestamp = false)
    {
        return Utils::returnNotImplementedHtml('Only attribute export is supported');
    }
    
    public static function importProducts($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_Products::importAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function exportProducts($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_Products::exportAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function syncProducts($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_Products::syncAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function importInventory($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_Inventory::importAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function exportInventory($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_Inventory::exportAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function syncInventory($ignoreLastTimestamp = false)
    {
        $output = '';
        try {
            $output .= Erply_Sync_Inventory::syncAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return $output . Utils::returnOkHtml();
    }
    
    public static function importSales()
    {
        return Utils::returnOkHtml();
    }
    
    public static function exportSales($ignoreLastTimestamp = false)
    {
        try {
            Erply_Sync_Orders::exportAll($ignoreLastTimestamp);
            Erply_Sync_OrderHistory::exportAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return Utils::returnOkHtml();
    }
    
    public static function syncSales($ignoreLastTimestamp = false)
    {
        try {
            Erply_Sync_Orders::syncAll($ignoreLastTimestamp);
            Erply_Sync_OrderHistory::syncAll($ignoreLastTimestamp);
        }
        catch(Erply_Exception $e) {
            return Utils::getErrorHtml($e);
        }
        
        return Utils::returnOkHtml();
    }
    
    public static function resetLastImportTimestamps()
    {
        ErplyFunctions::setLastSyncTS('ERPLY_CATEGORIES', 0);
        ErplyFunctions::setLastSyncTS('ERPLY_PRODUCTS', 0);
        ErplyFunctions::setLastSyncTS('ERPLY_CUST_GROUPS', 0);
        return Utils::returnOkHtml();
    }
    
    public static function resetLastExportTimestamps()
    {
        ErplyFunctions::setLastSyncTS('PRESTA_CATEGORIES', 0);
        ErplyFunctions::setLastSyncTS('PRESTA_PRODUCTS', 0);
        ErplyFunctions::setLastSyncTS('PRESTA_CUST_GROUPS', 0);
        ErplyFunctions::setLastSyncTS('PRESTA_CUSTOMERS', 0);
        ErplyFunctions::setLastSyncTS('PRESTA_CUST_ADDR', 0);
        ErplyFunctions::setLastSyncTS('PRESTA_ORDER', 0);
        return Utils::returnOkHtml();
    }
}

?>