<?php
require_once(_PS_MODULE_DIR_ . '/erply/EAPI.class.php');
require_once(_PS_MODULE_DIR_ . '/erply/Api/Response.php');
require_once(_PS_MODULE_DIR_ . '/erply/Exception.php');

class ErplyAPI
{
    // private $orderData = array(); // UNUSED
    private $erpUserData = array('url' => "https://www.erply.net/api/");
    private $api;
    
    // getConfParameters response
    protected static $config;
    
    public $errors = array();
    
    /*
     * Function get erply user data
     *
     * return false if no erply data saved
     */
    public function setErplyConnectionData($erpUserData)
    {
        // check if all erply data is present
        if(!empty($erpUserData['clientCode']) && !empty($erpUserData['username']) && !empty($erpUserData['password'])) {
            $this->erpUserData['url']        = 'https://' . $erpUserData['clientCode'] . '.erply.com/api/';
            $this->erpUserData['clientCode'] = $erpUserData['clientCode'];
            $this->erpUserData['username']   = $erpUserData['username'];
            $this->erpUserData['password']   = $erpUserData['password'];
        } else {
            array_push($this->errors, 'ERROR: Erply user data is required');
            $this->throwError(new Erply_Api_Response(), false);
        }
    }
    
    public function setErplyConnection()
    {
        $this->api             = new EAPI();
        // Configuration settings - sessionKey is assigned automatically
        $this->api->url        = $this->erpUserData['url'];
        $this->api->clientCode = $this->erpUserData['clientCode'];
        $this->api->username   = $this->erpUserData['username'];
        $this->api->password   = $this->erpUserData['password'];
        
        $this->verifyUser();
        
        return 'connection set';
    }
    
    ///////////////////////////// ERPLY WRAPPER FUNCTIONS ////////////////////////////////////////////
    
    /**
     * Call ERPLY API function.
     * 
     * @param string $method
     * @param array $params
     * @return Erply_Api_Response
     */
    public function callApiFunction($method, $params = array())
    {
        $responseAry = $this->api->sendRequest($method, $params);
        $responseAry = json_decode($responseAry, true);
        
        $responseObj = new Erply_Api_Response($responseAry, $params);
        if($responseObj->isError()) {
            if($responseObj->getErrorCode() == 1002) {
                array_push($this->errors, 'Erply allows max 500 requests in an hour. The limit is reached. Please try again after one hour');
            } else {
                array_push($this->errors, 'ERROR: ' . $responseObj->getRequestFunction() . ' - ' . $responseObj->getErrorCode());
            }
            $e = new Erply_Exception();
            throw $e->setData(array(
                'message' => $responseObj->getErrorMsg(),
                'code' => $responseObj->getErrorCode(),
                'isApiError' => true,
                'apiResponseObj' => $responseObj
            ));
        }
        
        return $responseObj;
    }
    
    /**
     * @param string $key
     * @return mixed - string if key IS NOT NULL, array otherwize.
     */
    public static function getConfig($key = null)
    {
        if(is_null(self::$config)) {
            self::$config = array();
            
            // Load config from API
            // NOTICE: Use ErplyFunctions::getErplyApi() to call an instance of 
            // this class, bc this method is static 
            $apiResp      = ErplyFunctions::getErplyApi()->callApiFunction('getConfParameters');
            self::$config = $apiResp->getFirstRecord();
        }
        
        if(!is_null($key)) {
            return isset(self::$config[$key]) ? self::$config[$key] : null;
        } else {
            return self::$config;
        }
    }
    
    /**
     * Returns time difference between local server and ERPLY server.
     * 
     * @return int
     */
    public function getTimeDifference()
    {
        return $this->api->getTimeDifference();
    }
    
    /**
     * Get ERPLY server timestamp by Presta time.
     * 
     * @param integer $prestaTime
     * @return integer
     */
    public function getErplyTime($prestaTime = null)
    {
        if(is_null($prestaTime)) {
            $prestaTime = time();
        }
        return time() + self::getTimeDifference();
    }
    
    /**
     * Get Presta timestamp by ERPLY timestamp.
     * 
     * @param integer $erplyTime
     * @return integer
     */
    public function getPrestaTime($erplyTime)
    {
        return $erplyTime - self::getTimeDifference();
    }
    
    /*
     * if webshop user is registered in erply
     * @param $this->inputParams|array - username, password
     * @return $customerId
     */
    public function verifyCustomerUser($username, $password)
    {
        $inputParams = array(
            'username' => $username,
            'password' => $password
        );
        $response    = $this->api->sendRequest('verifyCustomerUser', $inputParams);
        $response    = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response;
        }
    }
    
    public function verifyUser()
    {
        $inputParams = array(
            'username' => $this->erpUserData['username'],
            'password' => $this->erpUserData['password']
        );
        $response    = $this->api->sendRequest('verifyUser', $inputParams);
        $response    = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response;
        }
    }
    
    // Customers
    
    public function getCustomers($inputParams = false)
    {
        if($inputParams) {
            $response = $this->api->sendRequest('getCustomers', $inputParams);
        } else {
            $response = $this->api->sendRequest('getCustomers');
        }
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    public function getCustomerGroups($lang = false)
    {
        if($lang) {
            $inputParams = array(
                'lang' => $lang
            );
            $response    = $this->api->sendRequest('getCustomerGroups', $inputParams);
        } else {
            $response = $this->api->sendRequest('getCustomerGroups');
        }
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response;
        }
    }
    
    /*
     * save new customer
     */
    public function saveCustomer($inputParams)
    {
        $response = $this->api->sendRequest('saveCustomer', $inputParams);
        ErplyFunctions::log('saveCustomer');
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'][0]['customerID'];
        }
    }
    
    /**
     * [deleteCustomer description]
     * @param  [type] $id [description]
     * @return array - returns Customer Address records (use them to delete mappings)
     */
    public function deleteCustomer($id)
    {
        if(!$id) {
            array_push($this->errors, "deleteCustomer: invalid id (false)");
            $this->throwError(new Erply_Api_Response());
        }
        
        $customerRec = $this->getCustomers(array(
            'customerID' => $id,
            'getAddresses' => 1
        ));
        $addresses   = array();
        if(is_array($customerRec[0]) && array_key_exists('addresses', $customerRec[0])) {
            $addresses = $customerRec[0]['addresses'];
        }
        
        $response = $this->api->sendRequest('deleteCustomer', array(
            'customerID' => $id
        ));
        ErplyFunctions::log('deleteCustomer id: ' . $id);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $addresses;
        }
    }
    
    // Addresses
    
    public function getAddresses($inputParams)
    {
        $response = $this->api->sendRequest('getAddresses', $inputParams);
        ErplyFunctions::log('getAddresses');
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    /*
     * get address types
     * @return $response|array
     */
    public function getAddressTypes()
    {
        $inputParams = array(
            'lang' => 'eng'
        );
        $response    = $this->api->sendRequest('getAddressTypes', $inputParams);
        $response    = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    /*
     * save/update client's address
     */
    public function saveAddress($inputParams)
    {
        $response = $this->api->sendRequest('saveAddress', $inputParams);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'][0]['addressID'];
        }
    }
    
    // Product Categories
    
    public function deleteCategory($id)
    {
        if(!$id) {
            array_push($this->errors, "deleteCategory: invalid id (false)");
            $this->throwError(new Erply_Api_Response());
        }
        
        $response = $this->api->sendRequest('deleteProductGroup', array(
            'productGroupID' => $id
        ));
        ErplyFunctions::log('deleteProductGroup id: ' . $id);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return "Delete Category Success. erply id: " . $id;
        }
    }
    
    // Products
    
    public function getProducts($inputParams = false)
    {
        if($inputParams) {
            $response = $this->api->sendRequest('getProducts', $inputParams);
        } else {
            $response = $this->api->sendRequest('getProducts');
        }
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    public function getProductUnits()
    {
        $response = $this->api->sendRequest('getProductUnits');
        $response = json_decode($response, true);
        
        if($this->noErrorOnRequest($response)) {
            return $response;
        }
    }
    
    public function getProductGroups()
    {
        $response = $this->api->sendRequest('getProductGroups');
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    
    public function saveProduct($inputParams)
    {
        $response = $this->api->sendRequest('saveProduct', $inputParams);
        $response = json_decode($response, true);
        
        if($this->noErrorOnRequest($response)) {
            return $response['records'][0]['productID'];
        }
    }
    
    public function deleteProduct($inputParams)
    {
        $response = $this->api->sendRequest('deleteProduct', $inputParams);
        $response = json_decode($response, true);
        
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    // Matrix dimensions 
    
    public function getMatrixDimensions($inputParams = array())
    {
        if(!array_key_exists('recordsOnPage', $inputParams)) {
            $inputParams['recordsOnPage'] = 100;
        }
        
        $response = $this->api->sendRequest('getMatrixDimensions', $inputParams);
        $response = json_decode($response, true);
        
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    /*
     * save/update product group
     */
    public function saveProductGroup($inputParams)
    {
        $response = $this->api->sendRequest('saveProductGroup', $inputParams);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'][0]['productGroupID'];
        }
    }
    
    public function saveProductPicture($inputParams)
    {
        $response = $this->api->sendRequest('saveProductPicture', $inputParams);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'][0]['productPictureID'];
        }
    }
    
    // Sales Documents
    
    public function getSalesDocuments($inputParams = false)
    {
        if($inputParams) {
            $response = $this->api->sendRequest('getSalesDocuments', $inputParams);
        } else {
            $response = $this->api->sendRequest('getSalesDocuments');
        }
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    /*
     * save new order
     * @return bool
     */
    public function saveSalesDocument($inputParams)
    {
        $response = $this->api->sendRequest('saveSalesDocument', $inputParams);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'][0]['invoiceID'];
        }
    }
    
    public function deleteSalesDocument($inputParams)
    {
        $response = $this->api->sendRequest('deleteSalesDocument', $inputParams);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return true;
        }
    }
    
    // Payments
    
    public function getPayments($inputParams = false)
    {
        $response = $this->api->sendRequest('getPayments');
        if($inputParams) {
            $response = $this->api->sendRequest('getPayments', $inputParams);
        }
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    /*
     * save/update payment
     */
    public function savePayment($inputParams)
    {
        $response = $this->api->sendRequest('savePayment', $inputParams);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'][0]['paymentID'];
        }
    }
    
    // Warehouses
    
    public function getWarehouseLocations($inputParams = array())
    {
        $response = $this->api->sendRequest('getWarehouses', $inputParams);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    // Inventory
    
    public function saveInventoryRegistration($inputParams)
    {
        $response = $this->api->sendRequest('saveInventoryRegistration', $inputParams);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    public function saveInventoryWriteOff($inputParams)
    {
        $response = $this->api->sendRequest('saveInventoryWriteOff', $inputParams);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    // Other
    
    public function getReasonCodes($inputParams = array())
    {
        $response = $this->api->sendRequest('getReasonCodes', $inputParams);
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    public function getVatRates()
    {
        $response = $this->api->sendRequest('getVatRates');
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    public function getCurrencies()
    {
        $response = $this->api->sendRequest('getCurrencies');
        $response = json_decode($response, true);
        if($this->noErrorOnRequest($response)) {
            return $response['records'];
        }
    }
    
    private function noErrorOnRequest($response)
    {
        if(empty($response['status']['errorCode'])) {
            return true;
        }
        
        $responseObj = new Erply_Api_Response($response);
        if($response['status']['errorCode'] == 1002) {
            $e = new Erply_Exception();
            throw $e->setData(array(
                'message' => 'Erply allows max 1000 requests in an hour. The limit is reached. Please try again after one hour',
                'code' => '1002',
                'isApiError' => true,
                'apiResponseObj' => $responseObj
            ));
        } else {
            array_push($this->errors, 'ERROR: on request ' . $response['status']['request'] . ' .Code: ' . $response['status']['errorCode'] . (in_array($response['status']['errorCode'], array(
                '1010',
                '1011',
                '1012',
                '1014'
            )) ? ' ErrorField: ' . $response['status']['errorField'] : ''));
        }
        
        $this->throwError($responseObj);
    }
    
    protected function throwError($responseObj, $isApiError = true)
    {
        $message = '';
        foreach($this->errors as $error) {
            $message .= $error . '<br>';
        }
        $e = new Erply_Exception();
        throw $e->setData(array(
            'message' => $message,
            'code' => is_numeric($responseObj->getErrorCode()) ? $responseObj->getErrorCode() : 'unkonwn',
            'isApiError' => $isApiError,
            'apiResponseObj' => $responseObj
        ));
    }
    
}
