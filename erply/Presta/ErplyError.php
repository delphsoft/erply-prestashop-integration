<?php

if(!defined('_CAN_LOAD_FILES_'))
    exit;

require_once(_PS_MODULE_DIR_ . '/erply/ErplyFunctions.class.php');

class ErplyError extends ObjectModel
{
    const TABLE_NAME = 'erply_error';
    const ID_NAME = 'id_erply_error';
    
    /** @var integer id */
    public $id_erply_error;
    /** @var string error message*/
    public $msg;
    /** @var string hook name */
    public $hook;
    /** @var integer local object id (product, attribute_combination or order) */
    public $presta_id;
    /** @vat time of error */
    public $date_add;
    /** @var integer is error thread active */
    public $active;
    
    public static $definition = array(
        'table' => self::TABLE_NAME, 
        'primary' => self::ID_NAME, 
        'fields' => array(
            'msg' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true), 
            'hook' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true),
            'presta_id' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'active' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true), 
        )
    );
    
    const HOOK_ADD_PRODUCT = 'actionProductAdd';
    const HOOK_DEL_PRODUCT = 'actionProductDelete';
    const HOOK_UPDATE_QUANTITY = 'actionUpdateQuantity';
    
    const HOOK_COMB_ADD_AFTER = 'actionObjectCombinationAddAfter';
    const HOOK_COMB_UPD_AFTER = 'actionObjectCombinationUpdateAfter';
    const HOOK_COMB_DEL_AFTER = 'actionObjectCombinationDeleteAfter';
    
    public static function productMessage($idProduct, $exception, $hook) 
    {
        return 'Error on ' . $hook . ', Product id: ' . $idProduct . ', Erply error code: ' . $exception->getCode() . ' Erply error message: ' . $exception->getMessage();
    }
    
    public static function quantityAddMessage($idProductAttribute, $exception)
    {
        return 'Error on ' . self::HOOK_UPDATE_QUANTITY . ', Product Attrbiute id: ' . $idProductAttribute . ', Erply error code: ' . $exception->getCode() . ' Erply error message: ' . $exception->getMessage();
    }
    
    public static function attributeCombinationMessage($idCombination, $idProduct, $hookType, $exception)
    {
        return 'Error on ' . $hookType. ', Combination id: ' . $idCombination . ' Product id:' . $idProduct . ', Erply error code: ' . $exception->getCode() . ' Erply error message: ' . $exception->getMessage();
    }
    
    public static function genericMessage($exception) {
        return  'Erply error code: ' . $exception->getCode() . ' Erply error message: ' . $exception->getMessage();
    }
    
    public static function getActiveErrorCount()
    {
        $sql = 'SELECT COUNT(1) FROM ' . self::sqlTableName() . ' WHERE active=1';
        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        return (int)$res[0]['COUNT(1)'];
    }
    
    public static function sqlTableName()
    {
        return '`' . _DB_PREFIX_ . self::TABLE_NAME . '`';
    }
}