<?php

include_once(_PS_MODULE_DIR_ . '/erply/Sync/Abstract.php');
include_once(_PS_MODULE_DIR_ . '/erply/ErplyFunctions.class.php');

class Erply_Sync_Warehouses extends Erply_Sync_Abstract
{
    public static function syncAll($ignoreLastTimestamp = false)
    {
        return self::logAndReturn('Warehouses syncAll not implemented');
    }
    
    public static function importAll($ignoreLastTimestamp = false)
    {
        return self::updateWarehouseLocations();
    }
    
    public static function exportAll($ignoreLastTimestamp = false)
    {
        return self::logAndReturn('Warehouses exportAll not implemented');
    }
    
    public static function updateWarehouseLocations()
    {
        $output = '';
        
        try {
            $api        = ErplyFunctions::getErplyApi();
            $warehouses = $api->getWarehouseLocations();
        }
        catch(Erply_Exception $e) {
            $output .= self::logAndReturn(Utils::getErrorText($e));
        }
        
        foreach($warehouses as $warehouse) {
            if(!ErplyMapping::getMapping('Warehouse', 'erply_id', $warehouse['warehouseID'])) {
                $output .= self::logAndReturn('Added new warehouse location: ' . $warehouse['name']);
                $mappingObj              = new ErplyMapping();
                $mappingObj->object_type = 'Warehouse';
                $mappingObj->local_id    = $warehouse['warehouseID'];
                $mappingObj->erply_id    = $warehouse['warehouseID'];
                $mappingObj->setInfo('name', $warehouse['name']);
                $mappingObj->add();
            }
        }
        
        return $output;
    }
}