<?php
require_once(_PS_MODULE_DIR_ . '/erply/ErplyFunctions.class.php');
require_once(_PS_MODULE_DIR_ . '/erply/Sync.php');
require_once(_PS_MODULE_DIR_ . '/erply/Presta/ErplyMapping.php');
require_once(_PS_MODULE_DIR_ . '/erply/Presta/ErplyError.php');

class Erply extends Module
{    
    protected $activeHelper;
    protected static $combinationsGenIt = 0;
    
    public function __construct()
    {
        $this->name       = 'erply';
        $this->tab        = 'migration_tools';
        $this->version    = '2.0.0';
        $this->author     = 'Inventory.com';
        $this->module_key = '1e68ef1547edaf6abeb6bc9b5d98ea5f';
        $this->bootstrap  = true;
        
        parent::__construct();
        
        $this->displayName      = $this->l('Erply Order and Intentory management');
        $this->description      = $this->l('Erply Order and inventory management backend for PrestaShop');
        $this->confirmUninstall = $this->l('Are you sure you want to delete this module');
    }
    
    public function install()
    {
        if(!parent::install() || !$this->registerHooks()){
            return false;
        }
        
        Configuration::updateValue('ERPLY_CLIENTCODE', null);
        Configuration::updateValue('ERPLY_USERNAME', null);
        Configuration::updateValue('ERPLY_PASSWORD', null);
        Configuration::updateValue('ERPLY_LAST_ALL_SYNC_DIRECTION', 'export');
        Configuration::updateValue('ERPLY_LAST_ADVANCED_SYNC_DIRECTION', 'export');
        Configuration::updateValue('ERPLY_LAST_ADVANCED_SYNC_ITEM', 'customers');
        
        Configuration::updateValue('ERPLY_OBJECT_PRIORITY', 'ERPLY');
        Configuration::updateValue('ERPLY_EXPORT_ORDERS_FROM', mktime(0, 0, 0, date('n'), date('j'), date('Y')));
        Configuration::updateValue('ERPLY_PRESTA_ROOT_CATEGORY_ID', 2);
        Configuration::updateValue('ERPLY_EXPORT_PRODUCTS_LAST_RUN', 0);
        
        Configuration::updateValue('ERPLY_WAREHOUSE', null);
        Configuration::updateValue('ERPLY_HOOK_ACTION_PRODUCT_ADD', null);
        
        if(!$this->installDb()) {
            $this->uninstall();
            return false;
        }
        
        Configuration::updateValue('ERPLY_VERSION', $this->version);
        
        return true;
    }
    
    public function registerHooks()
    {
        return 
                $this->registerHook('actionCustomerAccountAdd') && 
                // $this->registerHook('actionObjectCustomerAddAfter') && 
                
                $this->registerHook('actionAttributeSave') && 
                $this->registerHook('actionAttributeDelete') && 
                $this->registerHook('actionCategoryAdd') && 
                $this->registerHook('actionCategoryDelete') && 
                // $this->registerHook('actionCategoryUpdate') && 
                // $this->registerHook('actionAttributeGroupSave') && 
                // $this->registerHook('actionAttributeGroupDelete') &&
                
                // $this->registerHook('actionProductAdd')  && 
                // $this->registerHook('actionProductSave')  && 
                $this->registerHook('actionProductUpdate')  && 
                $this->registerHook('actionProductDelete')  && 
                
                // $this->registerHook('actionProductAttributeUpdate') &&
                // $this->registerHook('actionProductAttributeDelete') &&
                
                
                $this->registerHook('actionUpdateQuantity') &&
                $this->registerHook('actionObjectCombinationAddAfter') &&
                $this->registerHook('actionObjectCombinationUpdateAfter') &&
                $this->registerHook('actionObjectCombinationDeleteAfter') &&
                
                // $this->registerHook('actionAttributePostProcess') &&
                // $this->registerHook('actionObjectProductUpdateAfter') &&
                // $this->registerHook('actionObjectProductDeleteAfter') &&
                // $this->registerHook('actionObjectProductAddAfter') &&

                $this->registerHook('actionOrderStatusUpdate') &&
                $this->registerHook('actionOrderReturn') && // as ationOrderReturn in db
                $this->registerHook('displayAdminOrder');
    }
    
    public function installDb()
    {
        $sql = '
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . ErplyMapping::TABLE_NAME . '` (
          `' . ErplyMapping::ID_NAME . '` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `erply_code` VARCHAR(50) NOT NULL,
          `object_type` VARCHAR(50) NOT NULL,
          `local_id` INT(10) UNSIGNED NOT NULL,
          `erply_id` INT(10) UNSIGNED NOT NULL,
          `info` TEXT,
          PRIMARY KEY (`' . ErplyMapping::ID_NAME . '`),
          KEY `IKEY1` (`erply_code`,`object_type`,`local_id`),
          KEY `IKEY2` (`erply_code`,`object_type`,`erply_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8;';
        
        if(!Db::getInstance()->Execute(trim($sql))) {
            ErplyFunctions::log('Create '  . _DB_PREFIX_ . ErplyMapping::TABLE_NAME . ' failed');
            return false;
        }
        
        $sql = '
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . ErplyError::TABLE_NAME . '` (
          `' . ErplyError::ID_NAME . '` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `msg` TEXT NOT NULL,
          `hook` VARCHAR(255) NOT NULL,
          `presta_id` INT(10) UNSIGNED NOT NULL,
          `date_add` DATETIME NOT NULL,
          `active` BOOLEAN NOT NULL,
          PRIMARY KEY (`' . ErplyError::ID_NAME . '`)
        ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8;';
        
        if(!Db::getInstance()->Execute(trim($sql))) {
            ErplyFunctions::log('Create '  . _DB_PREFIX_ . ErplyError::TABLE_NAME . ' failed');
            return false;
        }
        
        return true;
    }
    
    public function uninstall()
    {
        Configuration::deleteByName('ERPLY_CLIENTCODE');
        Configuration::deleteByName('ERPLY_USERNAME');
        Configuration::deleteByName('ERPLY_PASSWORD');
        Configuration::deleteByName('ERPLY_LAST_ALL_SYNC_DIRECTION');
        Configuration::deleteByName('ERPLY_LAST_ADVANCED_SYNC_DIRECTION');
        Configuration::deleteByName('ERPLY_LAST_ADVANCED_SYNC_ITEM');
        
        Configuration::deleteByName('ERPLY_OBJECT_PRIORITY');
        Configuration::deleteByName('ERPLY_EXPORT_ORDERS_FROM');
        Configuration::deleteByName('ERPLY_PRESTA_ROOT_CATEGORY_ID');
        
        Configuration::deleteByName('ERPLY_ERPLY_CUST_GROUPS_LS_TS');
        Configuration::deleteByName('ERPLY_PRESTA_CUST_GROUPS_LS_TS');
        Configuration::deleteByName('ERPLY_PRESTA_CUSTOMERS_LS_TS');
        Configuration::deleteByName('ERPLY_PRESTA_CUST_ADDR_LS_TS');
        Configuration::deleteByName('ERPLY_ERPLY_CATEGORIES_LS_TS');
        Configuration::deleteByName('ERPLY_PRESTA_CATEGORIES_LS_TS');
        Configuration::deleteByName('ERPLY_ERPLY_PRODUCTS_LS_TS');
        Configuration::deleteByName('ERPLY_PRESTA_PRODUCTS_LS_TS');
        Configuration::deleteByName('ERPLY_PRESTA_ORDER_LS_TS');
        Configuration::deleteByName('ERPLY_PRESTA_ORDER_HISTORY_LS_TS');
        Configuration::deleteByName('ERPLY_PRESTA_INVENTORY_LS_TS');
        
        Configuration::deleteByName('ERPLY_WAREHOUSE');
        
        Configuration::deleteByName('ERPLY_HOOK_ACTION_PRODUCT_ADD');
        
        // Clear mappings from db.
        Db::getInstance()->Execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . ErplyMapping::TABLE_NAME . '`');
        Db::getInstance()->Execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . ErplyError::TABLE_NAME . '`');
        
        return parent::uninstall();
    }
    
    protected function preHook($name, $params = null)
    {
        ErplyFunctions::log('EXEC HOOK: ' . $name . ' ' . ($params ? print_r($params, true) : ''));
        // ErplyFunctions::log($name."\n".print_r($params, true)."\n");
    }
    
    public function hookActionCustomerAccountAdd($params) 
    {
        $this->preHook(__FUNCTION__);
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD')) {
            $customer = $params['newCustomer'];
            try {
                Erply_Sync_Customers::createErplyCustomer($customer);
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(__FUNCTION__ . ' actionCustomerAccountAdd caught exception Attribute id: ' . $customer->id);
                $error = new ErplyError();
                $error->msg = ErplyError::genericMessage($e);
                $error->hook = 'actionCustomerAccountAdd';
                $error->presta_id = $customer->id;
                $error->active = true;
                $error->add();
            }
        }
    }
    
    public function hookActionAttributeSave($params)
    {
        $this->preHook(__FUNCTION__);
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD')) {
            try {
                Erply_Sync_Attributes::exportSingleAttribute($params['id_attribute']);
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(__FUNCTION__ . ' actionAttributeSave caught exception Attribute id: ' . $params['id_attribute']);
                $error = new ErplyError();
                $error->msg = ErplyError::genericMessage($e);
                $error->hook = 'actionAttributeSave';
                $error->presta_id = $params['id_attribute'];
                $error->active = true;
                $error->add();
            }
        }
    }
    public function hookActionAttributeDelete($params)
    {
        // Sync only if export done
        $this->preHook(__FUNCTION__, $params);
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD')) {
            try {
                Erply_Sync_Attributes::deleteVariation($params['id_attribute']);
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(__FUNCTION__ . ' actionAttributeDelete caught exception Attribute id: ' . $params['id_attribute']);
                $error = new ErplyError();
                $error->msg = ErplyError::genericMessage($e);
                $error->hook = 'actionAttributeDelete';
                $error->presta_id = $params['id_attribute'];
                $error->active = true;
                $error->add();
            }
        }
    }
    
    public function hookActionCategoryAdd($params)
    {
        $this->preHook(__FUNCTION__, $params);
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD')) {
            try {
                Erply_Sync_Categories::exportAll();
            }
            catch(Erply_Exception $e) {
                $category = $params['category'];
                ErplyFunctions::log(__FUNCTION__ . ' actionCategoryAdd caught exception Category id: ' . $category->id);
                $error = new ErplyError();
                $error->msg = ErplyError::genericMessage($e);
                $error->hook = 'actionCategoryAdd';
                $error->presta_id = $category->id;
                $error->active = true;
                $error->add();
            }
        }
    }
    // public function hookActionCategoryUpdate($params)
    // {
    //     $this->preHook(__FUNCTION__, $params['altern']);
    //     if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD')) {
    //         try {
    //             Erply_Sync_Categories::exportAll();
    //         }
    //         catch(Erply_Exception $e) {
    //             $category = $params['category'];
    //             ErplyFunctions::log(__FUNCTION__ . ' actionCategoryUpdate caught exception Category id: ' . $category->id);
    //             $error = new ErplyError();
    //             $error->msg = ErplyError::genericMessage($e);
    //             $error->hook = 'actionCategoryUpdate';
    //             $error->presta_id = $category->id;
    //             $error->active = true;
    //             $error->add();
    //         }
    //     }
    // }
    public function hookActionCategoryDelete($params)
    {
        $this->preHook(__FUNCTION__, $params);
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD')) {
            $category = $params['category'];
            try {
                Erply_Sync_Categories::deleteCategory($category->id);
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(__FUNCTION__ . ' actionCategoryDelete caught exception Category id: ' . $category->id);
                $error = new ErplyError();
                $error->msg = ErplyError::genericMessage($e);
                $error->hook = 'actionCategoryDelete';
                $error->presta_id = $category->id;
                $error->active = true;
                $error->add();
            }
        }
    }

    public function hookActionProductUpdate($params)
    {
        $this->preHook(__FUNCTION__, $params['altern']);
        
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD') && 
            !Tools::isSubmit('attribute_combination_list') &&
            !Tools::isSubmit('actionQty')) 
        {
            $output = '';
            $hook = ErplyError::HOOK_ADD_PRODUCT;
            try {
                ErplyFunctions::log(__FUNCTION__ . ' ' . $hook . ' update Product id: ' . $params['id_product']);
                $output .= Erply_Sync_Products::exportSingle($params['id_product']);
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(__FUNCTION__ . ' ' . $hook . ' caught exception Product id: ' . $params['id_product']);
                $error = new ErplyError();
                $error->msg = ErplyError::productMessage($params['id_product'], $e, $hook);
                $error->hook = $hook;
                $error->presta_id = $params['id_product'];
                $error->active = true;
                $error->add();
            }
            // ErplyFunctions::log(__FUNCTION__ . $output);
        }
    }
    public function hookActionProductDelete($params)
    {
        $this->preHook(__FUNCTION__, $params['altern']);
        
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD')) {         
            $output = '';
            $hook = ErplyError::HOOK_DEL_PRODUCT;
            try {
                ErplyFunctions::log(__FUNCTION__ . ' ' . $hook . ' delete Product id: ' . $params['id_product']);
                $output .= Erply_Sync_Products::deleteParentProduct($params['id_product']); 
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(__FUNCTION__ . ' ' . $hook . ' caught exception Product id: ' . $params['id_product']);
                $error = new ErplyError();
                $error->msg = ErplyError::productMessage($params['id_product'], $e, $hook);
                $error->hook = $hook;
                $error->presta_id = $params['id_product'];
                $error->active = true;
                $error->add();
            }
            // ErplyFunctions::log(__FUNCTION__ . $output);
        }
    }
    
    // public function hookActionOrderStatusUpdate($params)
    // {
    //     $this->preHook(__FUNCTION__, $params['altern']);
    // }
    // public function hookActionOrderReturn($params)
    // {
    //     $this->preHook(__FUNCTION__, $params['altern']);
    // }
    public function hookDisplayAdminOrder($params) 
    {
        $this->preHook(__FUNCTION__);
        $orderMapping = ErplyMapping::getMapping('SalesInvoice', 'local_id', $params['id_order']);
        Media::addJsDef(array('erply_erply_order_history' => ($orderMapping ? $orderMapping->getInfo('history') : 0)));
        if($orderMapping) {
            
        }
        
        $url = $this->context->link->getModuleLink($this->name, 'OrderSync', array('ajax' => 1, 'action' => 'sync', 'order' => $params['id_order']));
        $orderHistoryAry = Erply_Sync_OrderHistory::getPrestaOrderHistory($params['id_order']);
        foreach($orderHistoryAry as $key => $value) {
            $elem = $orderHistoryAry[$key];
            $orderState = new OrderState($elem['id_order_state'], Configuration::get('PS_LANG_DEFAULT'));
            $orderHistoryAry[$key]['order_state_name'] = $orderState->name;
        }
        
		Media::addJsDef(array(
                                'erply_sync_url' => $url,
                                'erply_presta_order_history' => $orderHistoryAry
                            )); 
                            
        $output = $this->display(__FILE__, 'views/templates/admin/order.tpl');
        return $output;
    }
    
    /**
     * called twice on product quanitity update.
     * 1st time with total product quantity and attrbiute combination id is 0
     * 2nd time with non-null attrbiute combination id with its quantity
     * @param  array $params [description]
     */
    public function hookActionUpdateQuantity($params) 
    {
        $this->preHook(__FUNCTION__, $params['altern']);
        
        $attributeCombinationId = $params['id_product_attribute'];
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD') && 
            $attributeCombinationId &&
            Tools::getValue('controller') != 'validation') // we don't reduce quantity here on product order order 
        {
            $output = '';
            $hook = ErplyError::HOOK_UPDATE_QUANTITY;
            try {
                $output .= Erply_Sync_Inventory::exportSingle($attributeCombinationId, $params['quantity']);
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(__FUNCTION__ . ' ' . $hook . ' caught exception Attribute Combination id: ' . $attributeCombinationId . ' q: ' . $params['quantity']);
                $error = new ErplyError();
                $error->msg = ErplyError::quantityAddMessage($attributeCombinationId, $e);
                $error->hook = $hook;
                $error->presta_id = $attributeCombinationId;
                $error->active = true;
                $error->add();
            }
        }
    }
    
    public function hookActionObjectCombinationAddAfter($params) 
    {
        $this->preHook(__FUNCTION__, $params['altern']);
        
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD')) {
            $output = '';
            $hook = ErplyError::HOOK_COMB_ADD_AFTER;
            $combination = new Combination($params['object']->id);
            $idProduct = $combination->id_product;
            $idCombination = $combination->id;
            if(Tools::isSubmit('attribute_combination_list')) {
                // if manually add combination
                $combination->setAttributes(Tools::getValue('attribute_combination_list'));
            } else if(Tools::isSubmit('options')) {
                // if using combination generator
                $options = self::createCombinations(Tools::getValue('options'));
                $combinations = array();
                foreach($options as $comb) {
                    $combinations[] = self::addAttribute($comb, $idProduct);
                }
                $values = array_values($combinations);
                
                self::generateMultipleCombinations($values, $options, $idCombination);
            }

            Product::flushPriceCache();
            
            // Combinations generator passes newly created attribute combinations, must create, if not exists
            if(!ErplyMapping::getMapping('ParentProduct', 'local_id', $idProduct)) {
                try {
                    $output .= Erply_Sync_Products::exportSingleParentProduct($idProduct);
                }
                catch(Erply_Exception $e) {
                    ErplyFunctions::log(__FUNCTION__ . ' ' . $hook . ' caught exception on exportSingleProduct id_product_attribute: ' . $attributeCombinationId . ' id_product: ' . $idProduct);
                    $error = new ErplyError();
                    $error->msg = ErplyError::quantityAddMessage($attributeCombinationId, $e) . ' on exportSingleProduct';
                    $error->hook = $hook;
                    $error->presta_id = $attributeCombinationId;
                    $error->active = true;
                    $error->add();
                    return;
                }
            }
            
            try {
                $output .= Erply_Sync_Products::exportSingleProduct($idProduct, $idCombination);
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(__FUNCTION__ . ' ' . $hook . ' caught exception on hookActionObjectCombinationAddAfter id_product_attribute: ' . $idCombination . ' id_product: ' . $idProduct);
                $error = new ErplyError();
                $error->msg = ErplyError::attributeCombinationMessage($idCombination, $idProduct, $hook, $e);
                $error->hook = $hook;
                $error->presta_id = $idCombination;
                $error->active = true;
                $error->add();
                return;
            }
        }
    }
    
    public function hookActionObjectCombinationUpdateAfter($params) 
    {
        $this->preHook(__FUNCTION__, $params['altern']);
        
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD')) {
            $output = '';
            $hook = ErplyError::HOOK_COMB_UPD_AFTER;
            $combination = $params['object'];
            $idProduct = $combination->id_product;
            $idCombination = $combination->id;
            Product::flushPriceCache();
            try {
                $output .= Erply_Sync_Products::exportSingleProduct($idProduct, $idCombination);
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(__FUNCTION__ . ' ' . $hook . ' caught exception on hookActionObjectCombinationUpdateAfter id_product_attribute: ' . $idCombination . ' id_product: ' . $idProduct);
                $error = new ErplyError();
                $error->msg = ErplyError::attributeCombinationMessage($idCombination, $idProduct, $hook, $e);
                $error->hook = $hook;
                $error->presta_id = $idCombination;
                $error->active = true;
                $error->add();
                return;
            }
        }
    }
    
    public function hookActionObjectCombinationDeleteAfter($params) 
    {
        $this->preHook(__FUNCTION__, $params['altern']);
        
        if(Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD')) {
            $output = '';
            $hook =  ErplyError::HOOK_COMB_DEL_AFTER;
            $combination = $params['object'];
            $idProduct = $combination->id_product;
            $idCombination = $combination->id;
            try {
                $output .= Erply_Sync_Products::deleteProduct($idCombination);
            }
            catch(Erply_Exception $e) {
                ErplyFunctions::log(__FUNCTION__ . ' ' . $hook . ' caught exception on hookActionObjectCombinationDeleteAfter id_product_attribute: ' . $idCombination . ' id_product: ' . $idProduct);
                $error = new ErplyError();
                $error->msg = ErplyError::attributeCombinationMessage($idCombination, $idProduct, $hook, $e);
                $error->hook = $hook;
                $error->presta_id = $idCombination;
                $error->active = true;
                $error->add();
                return;
            }
        }
    }
    
    public function getContent()
    {
        $output = '<h2>' . $this->displayName . '</h2>';
        $output .= $this->postProcessHookErrors();
        $output .= $this->displayHookErrors();
        $output .= $this->postProcess();
        $output .= $this->displayForm();
        return $output;
    }
    
    protected function displayHookErrors()
    {
        if(ErplyError::getActiveErrorCount()) {
            $output = $this->displayWarning($this->l('Note that this table contains errors that happened in action hooks'));
            
            $fields_list = array(
                ErplyError::ID_NAME => array(
                    'title' => $this->l('Id'),
                    'width' => 25,
                    'type' => 'text'
                ),
                'msg' . $this->context->language->id => array(
                    'title' => $this->l('Error message'),
                    'width' => 300,
                    'type' => 'text'
                ),
                'hook' => array(
                    'title' => $this->l('Hook name'),
                    'width' => 100,
                    'type' => 'text'
                ),
                'presta_id' => array(
                    'title' => $this->l('Prestashop Item Id'),
                    'width' => 50,
                    'type' => 'text'
                ),
                'active' => array(
                    'title' => $this->l('Active'),
                    'width' => 25,
                    'type' => 'text'
                ),
            );

            $this->activeHelper            = new HelperList();
            $helper                        = $this->activeHelper;
            $helper->module                = $this;
            $helper->shopLinkType          = '';
            $helper->simple_header         = false;
            $helper->show_toolbar          = false;
            $helper->actions               = array(
                'Resolve',
                'Ignore'
            );
            $helper->identifier            = ErplyError::ID_NAME;
            $helper->title                 = $this->l('Action hook errors list');
            $helper->table                 = ErplyError::TABLE_NAME;
            $helper->token                 = Tools::getAdminTokenLite('AdminModules');
            $helper->currentIndex          = $this->getAdminIndexUrl(false);

            $output .= $helper->generateList(self::generateListItems('ErplyError'), $fields_list);
            return $output;
        }
    }
    
    protected function postProcessHookErrors()
    {
        $output = '';
        if(Tools::isSubmit('resolve_erply_error')) {
            ;
        } else if(Tools::isSubmit('ignore_erply_error')) {
            ;
        }
        return $output;
    }
    
    protected function displayForm()
    {
        $output          = '';
        $defaultBtnClass = 'btn btn-default btn-block';
        
        $helper = new HelperForm();
        
        $index                         = 0;
        $fields_form                   = array();
        
        $fields_form[$index++]['form'] = array(
            'legend' => array(
                'title' => $this->l('Switches')
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Sync Products to Erply'),
                    'name' => 'ERPLY_HOOK_ACTION_PRODUCT_ADD',
                    'values' => array(
    					array(
    						'id' => 'active_on',
    						'value' => 1,
    						'label' => $this->l('Enabled')
    					),
    					array(
    						'id' => 'active_off',
    						'value' => 0,
    						'label' => $this->l('Disabled')
    					)
    				)
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'name' => 'submitSwitch',
                'class' => $defaultBtnClass
            )
        );
        
        $helper->fields_value['ERPLY_HOOK_ACTION_PRODUCT_ADD'] = (int)Configuration::get('ERPLY_HOOK_ACTION_PRODUCT_ADD');
        
        
        $fields_form[$index++]['form'] = array(
            'legend' => array(
                'title' => $this->l('Login data')
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Customer code'),
                    'name' => 'ERPLY_CLIENTCODE',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Username'),
                    'name' => 'ERPLY_USERNAME',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Password'),
                    'name' => 'ERPLY_PASSWORD',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'name' => 'submitErplyData',
                'class' => $defaultBtnClass
            )
        );
        
        $helper->fields_value['ERPLY_CLIENTCODE'] = Configuration::get('ERPLY_CLIENTCODE');
        $helper->fields_value['ERPLY_USERNAME']   = Configuration::get('ERPLY_USERNAME');
        $helper->fields_value['ERPLY_PASSWORD']   = base64_decode(Configuration::get('ERPLY_PASSWORD'));
        
        $taxOptions     = array();
        $currentTaxRule = Configuration::get('ERPLY_DEFAULT_TAXGROUP');
        foreach(TaxRulesGroup::getTaxRulesGroupsForOptions() as $taxRule) {
            $taxOptions[] = array(
                'id_option' => $taxRule['id_tax_rules_group'],
                'name' => $taxRule['name']
            );
            
            if(isset($taxRule['active']) && !$currentTaxRule) {
                $currentTaxRule = $taxRule['id_tax_rules_group'];
            }
        }
        
        
        $fields_form[$index++]['form'] = array(
            'legend' => array(
                'title' => $this->l('General settings')
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label' => $this->l('Priority'),
                    'name' => 'ERPLY_OBJECT_PRIORITY',
                    'options' => array(
                        'query' => array(
                            array(
                                'id_option' => 'ERPLY',
                                'name' => 'Erply'
                            ),
                            array(
                                'id_option' => 'Presta',
                                'name' => 'Prestashop'
                            )
                        ),
                        'id' => 'id_option',
                        'name' => 'name'
                    ),
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Default Tax'),
                    'desc' => $this->l('If tax value is not found in product data this value is used.'),
                    'name' => 'ERPLY_DEFAULT_TAXGROUP',
                    'options' => array(
                        'query' => $taxOptions,
                        'id' => 'id_option',
                        'name' => 'name'
                    ),
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Export orders from'),
                    'desc' => $this->l('Format: YYYY-MM-DD. Initial order export will be made starting from this date.'),
                    'name' => 'ERPLY_EXPORT_ORDERS_FROM',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('ID of root Category in PrestaShop'),
                    'desc' => $this->l('ID of PrestaShop root category. All product groups imported form Erply will appear under this category. Value 2 defaults to "Home".'),
                    'name' => 'ERPLY_PRESTA_ROOT_CATEGORY_ID',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'name' => 'saveGeneralSettings',
                'class' => $defaultBtnClass
            )
        );
        
        $helper->fields_value['ERPLY_OBJECT_PRIORITY']         = Configuration::get('ERPLY_OBJECT_PRIORITY');
        $helper->fields_value['ERPLY_DEFAULT_TAXGROUP']        = $currentTaxRule;
        $helper->fields_value['ERPLY_EXPORT_ORDERS_FROM']      = Configuration::get('ERPLY_EXPORT_ORDERS_FROM') != false ? date('Y-m-d', Configuration::get('ERPLY_EXPORT_ORDERS_FROM')) : '';
        $helper->fields_value['ERPLY_PRESTA_ROOT_CATEGORY_ID'] = Configuration::get('ERPLY_PRESTA_ROOT_CATEGORY_ID');
        
        
        
        // WAREHOUSE
        $fields_form[$index++]['form'] = array(
            'legend' => array(
                'title' => $this->l('Warehouse Locations Settings #1')
            ),
            'input' => array(),
            'submit' => array(
                'title' => $this->l('Get Warehouse Locations'),
                'name' => 'getWarehouseLocations',
                'class' => $defaultBtnClass
            )
        );
        
        $warehouseMappings = ErplyMapping::getMappings('Warehouse');
        
        if($warehouseMappings) {
            $queryArr = array();
            foreach($warehouseMappings as $whMapping) {
                $queryArr[] = array(
                    'id_option' => $whMapping->erply_id,
                    'name' => $whMapping->getInfo('name')
                );
            }
            
            $fields_form[$index++]['form'] = array(
                'legend' => array(
                    'title' => $this->l('Warehouse Locations Settings #2')
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('Choose warehouse for export'),
                        'name' => 'ERPLY_WAREHOUSE',
                        'options' => array(
                            'query' => $queryArr,
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Set Warehouse Location'),
                    'name' => 'setWarehouseLocation',
                    'class' => $defaultBtnClass
                )
            );
            
            $currWarehouse = Configuration::get('ERPLY_WAREHOUSE');
            $helper->fields_value['ERPLY_WAREHOUSE'] = $currWarehouse;
            
            if(!$currWarehouse) {
                $output .= $this->displayWarning($this->l('Warehouse location must be set prior to Product export'));
            } else {
                // WAREHOUSE END
                
                $fields_form[$index++]['form'] = array(
                    'legend' => array(
                        'title' => $this->l('Synchronization Controls')
                    ),
                    'input' => array(
                        array(
                            'type' => 'select',
                            'label' => $this->l('Sync direction'),
                            'desc' => $this->l('If both ways, then Erply is imported first'),
                            'name' => 'all_sync_direction',
                            'options' => array(
                                'query' => array(
                                    array(
                                        'id_option' => 'export',
                                        'name' => 'Prestashop => Erply'
                                    ),
                                    array(
                                        'id_option' => 'import',
                                        'name' => 'Erply => Prestashop'
                                    ),
                                    array(
                                        'id_option' => 'sync',
                                        'name' => 'Erply <=> Prestashop'
                                    )
                                ),
                                'id' => 'id_option',
                                'name' => 'name'
                            ),
                            'required' => true
                        )
                    ),
                    'submit' => array(
                        'title' => $this->l('Sync'),
                        'name' => 'syncAll',
                        'class' => $defaultBtnClass
                    )
                );
                
                $helper->fields_value['all_sync_direction'] = Configuration::get('ERPLY_LAST_ALL_SYNC_DIRECTION');
                
                if(Tools::getValue('advanced')) {
                    $this->renderAdvanced($fields_form, $index, $helper);
                } else {
                    $fields_form[$index++]['form'] = array(
                        'legend' => array(
                            'title' => $this->l('Advanced')
                        ),
                        'submit' => array(
                            'title' => $this->l('Advanced...'),
                            'name' => 'advanced',
                            'class' => $defaultBtnClass
                        )
                    );
                }
            }
        } else {
            Configuration::updateValue('ERPLY_WAREHOUSE', null);
            $output .= $this->displayWarning($this->l('No Warehouses in local mapping'));
        }
        
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        
        // Module, token and currentIndex
        $helper->module          = $this;
        $helper->name_controller = $this->name;
        $helper->token           = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex    = $this->getAdminIndexUrl(false);
        
        // Language
        $helper->default_form_language    = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        
        //No submitAction
        
        $output .= $helper->generateForm($fields_form);
        if(Tools::getValue('advanced')) {
            $output .= $this->renderMappingList();
        }
        return $output;
    }
    
    protected function postProcess()
    {
        $output = '';
        if(Tools::isSubmit('submitSwitch')) {
            foreach($_POST as $key => $postVal) {
                if(preg_match('/^ERPLY_HOOK.*/', $key)) {
                    Configuration::updateValue($key, $postVal);
                }
            }
        } else if(Tools::isSubmit('submitErplyData')) {
            $erp_client_code = (int) Tools::getValue('ERPLY_CLIENTCODE');
            $erp_username    = (string) Tools::getValue('ERPLY_USERNAME');
            $erp_password    = base64_encode(Tools::getValue('ERPLY_PASSWORD'));
            
            try {
                if(Configuration::updateValue('ERPLY_CLIENTCODE', $erp_client_code) && Configuration::updateValue('ERPLY_USERNAME', (string) $erp_username) && Configuration::updateValue('ERPLY_PASSWORD', $erp_password) && is_a(ErplyFunctions::getErplyApi(), 'ErplyAPI')) {
                    $output .= $this->displayConfirmation($this->l('Account information successfully saved'));
                } else {
                    $output .= $this->displayError($this->l('Cannot update account information'));
                }
            }
            catch(Erply_Exception $e) {
                $output .= Utils::getErrorHtml($e);
            }
        } else if(Tools::isSubmit('saveGeneralSettings')) {
            $success = true;
            
            // Save object priority.
            $objectPriorityVal = Tools::getValue('ERPLY_OBJECT_PRIORITY');
            if(!Configuration::updateValue('ERPLY_OBJECT_PRIORITY', (string) $objectPriorityVal)) {
                $output .= $this->displayError($this->l('Cannot save "Priority" value.'));
                $success = false;
            }
            
            // Save default taxgroup.
            $defaultTaxgroupVal = Tools::getValue('ERPLY_DEFAULT_TAXGROUP');
            if(!Configuration::updateValue('ERPLY_DEFAULT_TAXGROUP', (int) $defaultTaxgroupVal)) {
                $output .= $this->displayError($this->l('Cannot save "Default Tax" value.'));
                $success = false;
            }
            
            // Save export orders from.
            $exportOrdersFromVal = Tools::getValue('ERPLY_EXPORT_ORDERS_FROM');
            if(empty($exportOrdersFromVal)) {
                $exportOrdersFromTS = 0;
            } else {
                $exportOrdersFromAry = explode('-', Tools::getValue('ERPLY_EXPORT_ORDERS_FROM'));
                if(count($exportOrdersFromAry) == 3) {
                    $exportOrdersFromTS = mktime(0, 0, 0, (int) $exportOrdersFromAry[1], (int) $exportOrdersFromAry[2], (int) $exportOrdersFromAry[0]);
                } else {
                    $exportOrdersFromTS = 0;
                }
            }
            
            if(!Configuration::updateValue('ERPLY_EXPORT_ORDERS_FROM', $exportOrdersFromTS)) {
                $output .= $this->displayError($this->l('Cannot save "Export orders from" value.'));
                $success = false;
            }
            
            // presta root category id
            $prestaRootCategoryId = Tools::getValue('ERPLY_PRESTA_ROOT_CATEGORY_ID');
            if(!Configuration::updateValue('ERPLY_PRESTA_ROOT_CATEGORY_ID', (int) $prestaRootCategoryId)) {
                $output .= $this->displayError($this->l('Cannot save "Root category ID" value.'));
                $success = false;
            }
            
            if($success) {
                $output .= $this->displayConfirmation($this->l('Settings successfully saved'));
            }
            // WAREHOUSE
        } else if(Tools::isSubmit('getWarehouseLocations')) {
            $output .= Erply_Sync_Warehouses::updateWarehouseLocations();
        } else if(Tools::isSubmit('setWarehouseLocation')) {
            Configuration::updateValue('ERPLY_WAREHOUSE', Tools::getValue('ERPLY_WAREHOUSE'));
            $warehouseMapping = ErplyMapping::getMapping('Warehouse', 'erply_id', Tools::getValue('ERPLY_WAREHOUSE'));
            $warehouseName    = $warehouseMapping->getInfo('name');
            $output .= $this->displayConfirmation($this->l('Warehouse set to ') . $warehouseName);
            // WAREHOUSE END
        } else if(Tools::isSubmit('syncAll')) {
            $direction = Tools::getValue('all_sync_direction');
            Configuration::updateValue('ERPLY_LAST_ALL_SYNC_DIRECTION', $direction);
            try {
                // Changed records
                $output .= call_user_func(array(
                    'Erply_Sync',
                    $direction . 'All'
                ));
                // $output .= $this->displayConfirmation($this->l('Synchronization completed successfully!'));
            }
            catch(Erply_Exception $e) {
                $output .= Utils::getErrorHtml($e);
            }
        }
        
        if(Tools::getValue('advanced')) {
            $output .= $this->postProcessAdvanced();
            $output .= $this->postProcessMappingList();
        }
        
        return $output;
    }
    
    protected function renderAdvanced(array &$fields_form, &$index, HelperForm &$helper)
    {
        $defaultBtnClass = 'btn btn-default btn-block';
        // $fields_form[index++]['form'] = array(
        //     'legend' => array(
        //         'title' => $this->l('Advanced'),
        //     ),
        //     'submit' => array(
        //         'title' => $this->l('Close Advanced'),
        //         'name' => 'resetAdvanced',
        //         'class' => 'btn btn-success btn-block'
        //     )
        // );
        
        $fields_form[$index++]['form'] = array(
            'legend' => array(
                'title' => $this->l('Mappings')
            ),
            'input' => array(
                array(
                    'type' => 'hidden',
                    'label' => $this->l('Advanced'),
                    'name' => 'advanced',
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Clear Mappings (only local database, erply will be untouched)'),
                'name' => 'deleteMappings',
                'class' => $defaultBtnClass
            )
        );
        
        $fields_form[$index++]['form'] = array(
            'legend' => array(
                'title' => $this->l('Manual Synchronization')
            ),
            'input' => array(
                array(
                    'type' => 'hidden',
                    'label' => $this->l('Advanced'),
                    'name' => 'advanced',
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Sync Direction'),
                    'desc' => $this->l('If both ways, then Erply is imported first'),
                    'name' => 'advanced_sync_direction',
                    'options' => array(
                        'query' => array(
                            array(
                                'id_option' => 'export',
                                'name' => 'Prestashop => Erply'
                            ),
                            array(
                                'id_option' => 'import (incomplete)',
                                'name' => 'Erply => Prestashop'
                            ),
                            array(
                                'id_option' => 'sync (incomplete)',
                                'name' => 'Erply <=> Prestashop'
                            )
                        ),
                        'id' => 'id_option',
                        'name' => 'name'
                    ),
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Sync Items'),
                    'name' => 'advanced_sync_item',
                    'options' => array(
                        'query' => array(
                            array(
                                'id_option' => 'customers',
                                'name' => 'Customers (Groups,Clients,Addresses)'
                            ),
                            array(
                                'id_option' => 'categories',
                                'name' => 'Product Categories'
                            ),
                            array(
                                'id_option' => 'attributes',
                                'name' => 'Product Attributes (Matrix Dimensions)'
                            ),
                            array(
                                'id_option' => 'products',
                                'name' => 'Products (not Inventory) (only export)'
                            ),
                            array(
                                'id_option' => 'orders',
                                'name' => 'Orders (only export)'
                            ),
                            array(
                                'id_option' => 'inventory',
                                'name' => 'Inventory (Quantities) (only export)'
                            )
                        ),
                        'id' => 'id_option',
                        'name' => 'name'
                    ),
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Sync'),
                'name' => 'advancedSync',
                'class' => $defaultBtnClass
            )
        );
        
        $helper->fields_value['advanced_sync_direction'] = Configuration::get('ERPLY_LAST_ADVANCED_SYNC_DIRECTION');
        $helper->fields_value['advanced_sync_item']      = Configuration::get('ERPLY_LAST_ADVANCED_SYNC_ITEM');
        
        $this->renderSingleProductExport($fields_form, $index, $helper);
        
        $fields_form[$index++]['form'] = array(
            'legend' => array(
                'title' => $this->l('Set last IMPORT timestamps') . ' (Y-m-d H:i:s)'
            ),
            'input' => array(
                array(
                    'type' => 'hidden',
                    'label' => $this->l('Advanced'),
                    'name' => 'advanced',
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Categories'),
                    'name' => 'ERPLY_CATEGORIES',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Products'),
                    'name' => 'ERPLY_PRODUCTS',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Customer Groups'),
                    'name' => 'ERPLY_CUST_GROUPS',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'name' => 'setImportTimestamps',
                'class' => $defaultBtnClass
            )
        );
        
        $helper->fields_value['ERPLY_CATEGORIES']  = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_ERPLY_CATEGORIES_LS_TS'));
        $helper->fields_value['ERPLY_PRODUCTS']    = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_ERPLY_PRODUCTS_LS_TS'));
        $helper->fields_value['ERPLY_CUST_GROUPS'] = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_ERPLY_CUST_GROUPS_LS_TS'));
        
        $fields_form[$index++]['form'] = array(
            'legend' => array(
                'title' => $this->l('Set last EXPORT timestamps') . ' (Y-m-d H:i:s)'
            ),
            'input' => array(
                array(
                    'type' => 'hidden',
                    'label' => $this->l('Advanced'),
                    'name' => 'advanced',
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Categories'),
                    'name' => 'PRESTA_CATEGORIES',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Products'),
                    'name' => 'PRESTA_PRODUCTS',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Customer Groups'),
                    'name' => 'PRESTA_CUST_GROUPS',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Customers'),
                    'name' => 'PRESTA_CUSTOMERS',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Customer Addresses'),
                    'name' => 'PRESTA_CUST_ADDR',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Orders'),
                    'name' => 'PRESTA_ORDER',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Order History'),
                    'name' => 'PRESTA_ORDER_HISTORY',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Inventory'),
                    'name' => 'PRESTA_INVENTORY',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'name' => 'setExportTimestamps',
                'class' => $defaultBtnClass
            )
        );
        
        $helper->fields_value['PRESTA_CATEGORIES']    = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_PRESTA_CATEGORIES_LS_TS'));
        $helper->fields_value['PRESTA_PRODUCTS']      = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_PRESTA_PRODUCTS_LS_TS'));
        $helper->fields_value['PRESTA_CUST_GROUPS']   = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_PRESTA_CUST_GROUPS_LS_TS'));
        $helper->fields_value['PRESTA_CUSTOMERS']     = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_PRESTA_CUSTOMERS_LS_TS'));
        $helper->fields_value['PRESTA_CUST_ADDR']     = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_PRESTA_CUST_ADDR_LS_TS'));
        $helper->fields_value['PRESTA_ORDER']         = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_PRESTA_ORDER_LS_TS'));
        $helper->fields_value['PRESTA_ORDER_HISTORY'] = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_PRESTA_ORDER_HISTORY_LS_TS'));
        $helper->fields_value['PRESTA_INVENTORY']     = date('Y-m-d H:i:s', (int) Configuration::get('ERPLY_PRESTA_INVENTORY_LS_TS'));
        
        $helper->fields_value['advanced'] = 1;
    }
    
    protected function postProcessAdvanced()
    {
        $output = '';
        
        if(Tools::isSubmit('setImportTimestamps')) {
            $timestampsAry                      = array();
            $timestampsAry['ERPLY_CATEGORIES']  = Tools::getValue('ERPLY_CATEGORIES');
            $timestampsAry['ERPLY_PRODUCTS']    = Tools::getValue('ERPLY_PRODUCTS');
            $timestampsAry['ERPLY_CUST_GROUPS'] = Tools::getValue('ERPLY_CUST_GROUPS');
            
            foreach($timestampsAry as $key => $val) {
                $ts = !empty($val) ? strtotime($val) : 0;
                ErplyFunctions::setLastSyncTS($key, $ts);
            }
            
            $output .= $this->displayConfirmation($this->l('Synchronization timestamps saved!'));
        } else if(Tools::isSubmit('setExportTimestamps')) {
            $timestampsAry                         = array();
            $timestampsAry['PRESTA_CATEGORIES']    = Tools::getValue('PRESTA_CATEGORIES');
            $timestampsAry['PRESTA_PRODUCTS']      = Tools::getValue('PRESTA_PRODUCTS');
            $timestampsAry['PRESTA_CUST_GROUPS']   = Tools::getValue('PRESTA_CUST_GROUPS');
            $timestampsAry['PRESTA_CUSTOMERS']     = Tools::getValue('PRESTA_CUSTOMERS');
            $timestampsAry['PRESTA_CUST_ADDR']     = Tools::getValue('PRESTA_CUST_ADDR');
            $timestampsAry['PRESTA_ORDER']         = Tools::getValue('PRESTA_ORDER');
            $timestampsAry['PRESTA_ORDER_HISTORY'] = Tools::getValue('PRESTA_ORDER_HISTORY');
            $timestampsAry['PRESTA_INVENTORY']     = Tools::getValue('PRESTA_INVENTORY');
            
            foreach($timestampsAry as $key => $val) {
                $ts = !empty($val) ? strtotime($val) : 0;
                ErplyFunctions::setLastSyncTS($key, $ts);
            }
            
            $output .= $this->displayConfirmation($this->l('Synchronization timestamps saved!'));
        } else if(Tools::isSubmit('advancedSync')) {
            $direction = Tools::getValue('advanced_sync_direction');
            $item      = Tools::getValue('advanced_sync_item');
            
            Configuration::updateValue('ERPLY_LAST_ADVANCED_SYNC_DIRECTION', $direction);
            Configuration::updateValue('ERPLY_LAST_ADVANCED_SYNC_ITEM', $item);
            try {
                $output .= call_user_func(array(
                    'Erply_Sync',
                    $direction . ucfirst($item)
                ));
            }
            catch(Erply_Exception $e) {
                $output .= Utils::getErrorHtml($e);
            }
        } else if(Tools::isSubmit('deleteMappings')) {
            // Delete current mappings. Only for advanced users.
            ErplyMapping::deleteAll();
        }
        
        $output .= $this->postProcessSingleProductExport();
        
        return $output;
    }
    
    protected function renderSingleProductExport(array &$fields_form, &$index, HelperForm &$helper)
    {
        $defaultBtnClass = 'btn btn-default btn-block';
        
        $fields_form[$index++]['form'] = array(
            'legend' => array(
                'title' => $this->l('Export Single Product')
            ),
            'input' => array(
                array(
                    'type' => 'hidden',
                    'label' => $this->l('Advanced'),
                    'name' => 'advanced',
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Product Id'),
                    'name' => 'idSingleProductExport',
                    'required' => true,
                    'class' => 'input fixed-width-lg'
                )
            ),
            'submit' => array(
                'title' => $this->l('Export'),
                'name' => 'singleProductExport',
                'class' => $defaultBtnClass
            )
        );
        
        $helper->fields_value['idSingleProductExport'] = 0;
    }
    
    protected function postProcessSingleProductExport()
    {
        $output = '';
        
        if(Tools::isSubmit('singleProductExport')) {
            $id = (int) Tools::getValue('idSingleProductExport');
            
            if(is_int($id) && $id > 0) {
                try {
                    $output .= Erply_Sync_Products::exportSingle($id);
                }
                catch(Erply_Exception $e) {
                    $output .= Utils::getErrorHtml($e);
                }
            } else {
                $output .= $this->displayError('Product Id not integer or less than or equal to  0');
            }
        }
        
        return $output;
    }
    
    protected function renderMappingList()
    {
        $output = $this->displayWarning($this->l('Note that deleting a mapping here, will delete the item in erply if possible'));
        
        $fields_list = array(
            'id_erply_mapping' => array(
                'title' => $this->l('Id'),
                'width' => 50,
                'type' => 'text'
            ),
            'erply_code' . $this->context->language->id => array(
                'title' => $this->l('Customer Code'),
                'width' => 100,
                'type' => 'text'
            ),
            'object_type' => array(
                'title' => $this->l('Object Type'),
                'width' => 100,
                'type' => 'text'
            ),
            'local_id' => array(
                'title' => $this->l('Prestashop Item Id'),
                'width' => 100,
                'type' => 'text'
            ),
            'erply_id' => array(
                'title' => $this->l('Erply Item Id'),
                'width' => 100,
                'type' => 'text'
            ),
            'info' => array(
                'title' => $this->l('Info'),
                'width' => 100,
                'type' => 'text'
            )
        );
        
        $this->activeHelper            = new HelperList();
        $helper                        = $this->activeHelper;
        $helper->module                = $this;
        $helper->shopLinkType          = '';
        $helper->simple_header         = false;
        $helper->show_toolbar          = false;
        $helper->actions               = array(
            'DeleteAdvanced'
        );
        $helper->identifier            = ErplyMapping::ID_NAME;
        $helper->title                 = $this->l('Erply <=> Prestashop Object Mapping List');
        $helper->table                 = ErplyMapping::TABLE_NAME;
        $helper->token                 = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex          = $this->getAdminIndexUrl(false);
        
        $output .= $helper->generateList(self::generateListItems('ErplyMapping'), $fields_list);
        return $output;
    }
    
    protected function postProcessMappingList()
    {
        $output = '';
        if(Tools::isSubmit('deleteerply_mapping')) {
            $id           = Tools::getValue('id_erply_mapping');
            $erplyMapping = new ErplyMapping($id);
            $objectType   = $erplyMapping->object_type;
            
            switch($objectType) {
                case 'Customer':
                    try {
                        $customer  = new Customer($erplyMapping->local_id);
                        $addresses = ErplyFunctions::getErplyApi()->deleteCustomer($erplyMapping->erply_id);
                        foreach($addresses as $address) {
                            $addressMapping = ErplyMapping::getMapping('CustomerAddress', 'erply_id', $address['addressID']);
                            
                            $output .= $this->deleteMappingAndOutput($addressMapping, 'CustomerAddress', $address['address']);
                        }
                        $output .= $this->deleteMappingAndOutput($erplyMapping, 'Customer', $customer->firstname . ' ' . $customer->lastname);
                    }
                    catch(Erply_Exception $e) {
                        $output .= Utils::getErrorHtml($e);
                    }
                    catch(TypeError $e) {
                        $output .= $this->displayError($this->l("Maybe address mapping was null? Message: ") . $e->getMessage());
                    }
                    $output .= $this->displayConfirmation($objectType . ': ' . $this->l('Delete from Erply succesful'));
                    break;
                case 'CustomerAddress':
                case 'CustomerGroup':
                    $output .= $this->displayError($this->l('Cannot delete ') . $objectType . '. ' . $this->l('Delete Customer to delete address.
                                          Customer Groups can only be deleted within Erply'));
                    break;
                case 'Category':
                    try {
                        $output .= Erply_Sync_Categories::deleteCategory($erplyMapping->local_id);
                    }
                    catch(Erply_Exception $e) {
                        $output .= Utils::getErrorHtml($e);
                    }
                    break;
                case 'ParentAttribute':
                    try {
                        $output .= Erply_Sync_Attributes::deleteMatrix($erplyMapping->local_id);
                    }
                    catch(Erply_Exception $e) {
                        $output .= Utils::getErrorHtml($e);
                    }
                    break;
                case 'Attrbiute':
                    $output .= $this->displayWarning($this->l('Single Attribute removal not implemented. Remove ParentAttribute'));
                    break;
                case 'ParentProduct':
                    try {
                        $output .= Erply_Sync_Products::deleteParentProduct($erplyMapping->local_id);
                    }
                    catch(Erply_Exception $e) {
                        $output .= Utils::getErrorHtml($e);
                    }
                    break;
                case 'Product':
                    $output .= $this->displayWarning($this->l('Attribute Combination removal not implemented. Remove ParentProduct'));
                    break;
                default:
                    $output .= $this->displayError($this->l('Cannot delete, unknown object type: ') . $objectType);
                    break;
            }
        }
        
        return $output;
    }
    
    /**
     * override of classes/helper/HelperList.php displayDeleteLink method
     * to add advanced=1 to href
     * 
     * @param  [type] $token [description]
     * @param  [type] $id    [description]
     * @param  [type] $name  [description]
     * @return [type]        [description]
     */
    public function displayDeleteAdvancedLink($token, $id, $name)
    {
        $helper = $this->activeHelper;
        $tpl    = $helper->createTemplate('list_action_delete.tpl');
        
        if(!array_key_exists('Delete', HelperList::$cache_lang)) {
            HelperList::$cache_lang['Delete'] = $this->l('Delete', 'Helper');
        }
        
        if(!array_key_exists('DeleteItem', HelperList::$cache_lang)) {
            HelperList::$cache_lang['DeleteItem'] = $this->l('Delete selected item?', 'Helper', true, false);
        }
        
        if(!array_key_exists('Name', HelperList::$cache_lang)) {
            HelperList::$cache_lang['Name'] = $this->l('Name:', 'Helper', true, false);
        }
        
        if(!is_null($name)) {
            $name = addcslashes('\n\n' . HelperList::$cache_lang['Name'] . ' ' . $name, '\'');
        }
        
        $data = array(
            $helper->identifier => $id,
            // the only change is .'&advanced=1'
            'href' => $helper->currentIndex . '&' . $helper->identifier . '=' . $id . '&delete' . $helper->table . '&token=' . ($token != null ? $token : $helper->token) . '&advanced=1',
            'action' => HelperList::$cache_lang['Delete']
        );
        
        if($helper->specificConfirmDelete !== false) {
            $data['confirm'] = !is_null($helper->specificConfirmDelete) ? '\r' . $helper->specificConfirmDelete : Tools::safeOutput(HelperList::$cache_lang['DeleteItem'] . $name);
        }
        
        $tpl->assign(array_merge($helper->tpl_delete_link_vars, $data));
        
        return $tpl->fetch();
    }
    
    /**
     * Display resolve action link
     */
    public function displayResolveLink($token = null, $id, $name = null)
    {
        $helper = $this->activeHelper;
        $tpl = $helper->createTemplate('list_action_edit.tpl');
        if (!array_key_exists('Resolve', HelperList::$cache_lang)) {
            HelperList::$cache_lang['Resolve'] = $this->l('Resolve', 'Helper');
        }

        $tpl->assign(array(
            'href' => $helper->currentIndex.'&'.$helper->identifier.'='.$id.'&resolve_'.$helper->table.'&token='.($token != null ? $token : $helper->token),
            'action' => HelperList::$cache_lang['Resolve'],
        ));

        return $tpl->fetch();
    }
    
    
    /**
     * Display ignore action link
     */
    public function displayIgnoreLink($token = null, $id, $name = null)
    {
        $helper = $this->activeHelper;
        $tpl = $helper->createTemplate('list_action_edit.tpl');
        if (!array_key_exists('Ignore', HelperList::$cache_lang)) {
            HelperList::$cache_lang['Ignore'] = $this->l('Ignore', 'Helper');
        }

        $tpl->assign(array(
            'href' => $helper->currentIndex.'&'.$helper->identifier.'='.$id.'&ignore_'.$helper->table.'&token='.($token != null ? $token : $helper->token),
            'action' => HelperList::$cache_lang['Ignore'],
        ));

        return $tpl->fetch();
    }
    
    protected function getAdminIndexUrl($withToken = true)
    {
        return $this->context->link->getAdminLink('AdminModules', $withToken) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
    }
    
    public static function generateListItems($type)
    {
        $sql = new DbQuery();
        $sql->select(constant($type . '::ID_NAME'));
        $sql->from(constant($type . '::TABLE_NAME'));
        $mappingIds = Db::getInstance()->executeS($sql);
        
        $result = array();
        
        $i = 0;
        foreach($mappingIds as $idElem) {
            $id         = $idElem[constant($type . '::ID_NAME')];
            $elem       = new $type($id);
            $result[$i++] = $elem->getFields();
        }
        
        return $result;
    }
    
    protected function deleteMappingAndOutput(ErplyMapping &$mapping, $what, $msg)
    {
        $mappingId = $mapping->id_erply_mapping;
        if(!$mapping->delete()) {
            return $this->displayError($this->l('Error while deleting local mapping for: ') . $what . ' id: ' . $mappingId . '<br> Info: ' . $msg);
        } else {
            return $this->displayConfirmation($this->l('Deleted local mapping for: ') . $what . ' id: ' . $mappingId . '<br> Info: ' . $msg);
        }
    }
    
    /**
     * UNUSED
     */
    protected function getOrderHistoryId($id_order)
    {
        $result = Db::getInstance()->getRow('
		SELECT `id_order_history`
		FROM `' . _DB_PREFIX_ . 'order_history`
		WHERE `id_order` = ' . intval($id_order) . '
		ORDER BY `date_add` DESC, `id_order_history` DESC');
        if($result) {
            return $result['id_order_history'];
        }
        return false;
    }
    
    protected static function createCombinations($list)
    {
        $list = array_values($list);
        if (count($list) <= 1) {
            return count($list) ? array_map(create_function('$v', 'return (array($v));'), $list[0]) : $list;
        }
            $res = array();
            $first = array_pop($list);
            foreach ($first as $attribute) {
            $tab = self::createCombinations($list);
            foreach ($tab as $to_add) {
                $res[] = is_array($to_add) ? array_merge($to_add, array($attribute)) : array($to_add, $attribute);
            }
        }
        return array_values($res);
    }
    
    protected static function addAttribute($attributes, $idProduct)
    {
        $price = 0;
        $weight = 0;
        foreach ($attributes as $attribute) {
            $price += (float)preg_replace('/[^0-9.-]/', '', str_replace(',', '.', Tools::getValue('price_impact_'.(int)$attribute)));
            $weight += (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', Tools::getValue('weight_impact_'.(int)$attribute)));
        }
        if ($idProduct) {
            return array(
                'id_product' => (int)$idProduct,
                'price' => (float)$price,
                'weight' => (float)$weight,
                'ecotax' => 0,
                'quantity' => (int)Tools::getValue('quantity'),
                'reference' => pSQL($_POST['reference']),
                'default_on' => 0,
                'available_date' => '0000-00-00'
            );
        }
        return array();
    }
    
    protected static function generateMultipleCombinations($combinations, $attributes, $idCombination)
    {
        $default_on = 1;
        $attribute_list = array();
        ErplyFunctions::Log('it: ' . self::$combinationsGenIt);
        foreach ($attributes[self::$combinationsGenIt++] as $id_attribute) {
            $attribute_list[] = array(
                'id_product_attribute' => (int)$idCombination,
                'id_attribute' => (int)$id_attribute
            );
        }
        return Db::getInstance()->insert('product_attribute_combination', $attribute_list);
    }
}
