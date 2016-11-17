<?php
include_once(dirname(__FILE__) . '/Abstract.php');

class Erply_Sync_OrderHistory extends Erply_Sync_Abstract
{
    public static $prestaStatesAry = array(
                                        'paid' => array(
                                            2,
                                            15,
                                            16,
                                            19
                                        ),
                                        'deleted' => array(
                                            6
                                        ),
                                        'pending' => array(
                                            1,
                                            3,
                                            4,
                                            5,
                                            7,
                                            8,
                                            9,
                                            10,
                                            11,
                                            12,
                                            13,
                                            14,
                                            17,
                                            18,
                                            20
                                        )
                                        );
    
    /**
     * Sync. We only export orders.
     * 
     * @return integer - total objects synchronized
     */
    public static function syncAll()
    {
        $total = 0;
        $total += self::exportAll();
        
        return $total;
    }
    
    /**
     * Import ERPLY objects.
     * 
     * @return integer - Nr of objects imported.
     */
    public static function importAll()
    {
        return 0;
    }
    
    /**
     * Export Presta objects.
     * 
     * @return integer - Nr of objects exported.
     */
    public static function exportAll()
    {
        // Verify connection with erply
        $api = ErplyFunctions::getErplyApi();
        $api->VerifyUser();
        // Connection with erply OK
        
        $return          = 0;
        $prestaLocaleId  = ErplyFunctions::getPrestaLocaleId();

        foreach(self::getPrestaNewOrderHistory() as $prestaOrderHistoryAry) {
                if(self::doExport($prestaOrderHistoryAry, $prestaLocaleId, $api)) {
                    $return++;
                }
        }
        
        return $return;
    }
    
    public static function exportSingle($idOrder, $api = null)
    {
        if(!$api) {
            // Verify connection with erply
            $api = ErplyFunctions::getErplyApi();
            $api->VerifyUser();
            // Connection with erply OK
        }

        $prestaLocaleId  = ErplyFunctions::getPrestaLocaleId();
        
        foreach(self::getPrestaOrderHistory($idOrder) as $prestaOrderHistoryAry) {
            self::doExport($prestaOrderHistoryAry, $prestaLocaleId, $api);
        }
    }
    
    public static function doExport($prestaOrderHistoryAry, $prestaLocaleId, $api) {
        
        // Find order mapping
        $orderMappingObj = self::getSalesInvoiceMapping('local_id', $prestaOrderHistoryAry['id_order']);
        
        // Mapping found, Order IS in sync
        if(!is_null($orderMappingObj)) {
            $history = $orderMappingObj->getInfo('history');
            if(in_array($history, $prestaOrderHistoryAry['id_order_history'])) {
                ErplyFunctions::log('Presta Order History Id: ' . $prestaOrderHistoryAry['id_order_history'] . ' already in sync');
                return false;
            }
            
            // Delete ERPLY order if state 'deleted'
            if(in_array($prestaOrderHistoryAry['id_order_state'], self::$prestaStatesAry['deleted'])) {
                ErplyFunctions::log('Deleting ERPLY order. Presta Order ID: ' . $prestaOrderHistoryAry['id_order']);
                $apiResp = ErplyFunctions::getErplyApi()->callApiFunction('deleteSalesDocument', array(
                    'documentID' => $orderMappingObj->getErplyId()
                ));
            }
            
            // Get ERPLY document.
            $apiResp       = ErplyFunctions::getErplyApi()->callApiFunction('getSalesDocuments', array(
                'id' => $orderMappingObj->getErplyId()
            ));
            $erplyOrderAry = $apiResp->getFirstRecord();
            if(!is_array($erplyOrderAry) || !isset($erplyOrderAry['clientID'])) {
                // Sales document deleted in ERPLY.
                return false;
            }
            
            // Make payment
            if(in_array($prestaOrderHistoryAry['id_order_state'], self::$prestaStatesAry['paid'])) {
                $erplyOrderAry['invoiceState'] = 'READY';
                
                // Check if payments exist in ERPLY
                $apiResp = ErplyFunctions::getErplyApi()->callApiFunction('getPayments', array(
                    'documentID' => $orderMappingObj->getErplyId()
                ));
                if($apiResp->getRecordsCount() == 0) {
                    ErplyFunctions::log('Creating ERPLY payment. Presta Order ID: ' . $prestaOrderHistoryAry['id_order']);
                    $erplyPaymentAry = array(
                        'customerID' => $erplyOrderAry['clientID'],
                        'documentID' => $erplyOrderAry['id'],
                        'date' => date('Y-m-d', strtotime($prestaOrderHistoryAry['date_add'])),
                        'sum' => $erplyOrderAry['total'],
                        'currencyCode' => $erplyOrderAry['currencyCode']
                    );
                    $apiResp         = ErplyFunctions::getErplyApi()->callApiFunction('savePayment', $erplyPaymentAry);
                }
            }
            
            // Set as pending
            if(in_array($prestaOrderHistoryAry['id_order_state'], self::$prestaStatesAry['pending'])) {
                $erplyOrderAry['invoiceState'] = 'PENDING';
            }
            
            // Remove unwanted properties from erply document.
            // Most cannot be saved and some must be converted from
            // multi-dimensional arrays to flats.
            unset($erplyOrderAry['baseDocuments'], $erplyOrderAry['netTotalsByRate'], $erplyOrderAry['vatTotalsByRate'], $erplyOrderAry['invoiceLink'], $erplyOrderAry['attributes'], $erplyOrderAry['otherCommissionReceivers'], $erplyOrderAry['rows']);
            
            // Set ERPLY document status.
            ErplyFunctions::getErplyApi()->callApiFunction('saveSalesDocument', $erplyOrderAry);
            
            // Save now as last sync time for order history
            ErplyFunctions::setLastSyncTS('PRESTA_ORDER_HISTORY', strtotime($prestaOrderHistoryAry['date_add']));
            
            $history[] = $prestaOrderHistoryAry['id_order_history'];
            $orderMappingObj->setInfo('history', $history);
            $orderMappingObj->update();
            return true;
        }
    }
    
    public static function getPrestaOrderHistory($orderId)
    {

        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'order_history`' .
                'WHERE id_order=' . (int)$orderId;
        
        $historyAry = Db::getInstance()->ExecuteS($sql);
        return is_array($historyAry) ? $historyAry : array();
    }
    
    /**
     * Get array of Presta OrderHistory object that have been added since last sync.
     * 
     * @return array
     */
    public static function getPrestaNewOrderHistory()
    {
        $historyLastSyncTS = ErplyFunctions::getLastSyncTS('PRESTA_ORDER_HISTORY');
        $orderLastSyncTS   = ErplyFunctions::getLastSyncTS('PRESTA_ORDER');
        $sql               = '
SELECT * 
FROM `' . _DB_PREFIX_ . 'order_history` 
WHERE
	UNIX_TIMESTAMP(`date_add`) >= ' . intval($historyLastSyncTS) . ' 
	AND UNIX_TIMESTAMP(`date_add`) <= ' . intval($orderLastSyncTS) . '
ORDER BY `date_add` ASC';
        
        $historyAry = Db::getInstance()->ExecuteS($sql);
        return is_array($historyAry) ? $historyAry : array();
    }
}

?>