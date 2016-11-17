<?php 

require_once(_PS_MODULE_DIR_ . '/erply/Sync.php');

class erplyOrderSyncModuleFrontController extends ModuleFrontController
{
    public function displayAjaxSync()
    {
        $orderId = Tools::getValue('order');
        $order = new Erply_Presta_Order($orderId);
        if(!$order) {
            die(json_encode(array('error' => '1', 'errorMessage' => 'unable to get order by id: ' . $orderId)));
        }
        
        $salesInvoiceMapping = ErplyMapping::getMapping('SalesInvoice', 'local_id', $orderId);
        
        try {
            Erply_Sync_Orders::updateErplyOrder($order, $salesInvoiceMapping);
            Erply_Sync_OrderHistory::exportSingle($orderId);
            $ret = array();
            
            if(!$salesInvoiceMapping) {
                $salesInvoiceMapping = ErplyMapping::getMapping('SalesInvoice', 'local_id', $orderId);
            }
            
            $orderHistoryAry = Erply_Sync_OrderHistory::getPrestaOrderHistory($orderId);
            foreach($orderHistoryAry as $key => $value) {
                $elem = $orderHistoryAry[$key];
                $orderState = new OrderState($elem['id_order_state'], Configuration::get('PS_LANG_DEFAULT'));
                $orderHistoryAry[$key]['order_state_name'] = $orderState->name;
            }
            
            $ret['history'] = $salesInvoiceMapping->getInfo('history');
            $ret['erply_presta_order_history'] = $orderHistoryAry;
            die(json_encode(array($ret)));
        }
        catch(Erply_Exception $e) {
            die(json_encode(array('error' => '1', 'errorMessage' => Utils::getErrorText($e))));
        }
    }
}