<?php

if(!defined('_CAN_LOAD_FILES_'))
    exit;

require_once(_PS_MODULE_DIR_ . '/erply/ErplyFunctions.class.php');

class ErplyMapping extends ObjectModel
{
    const TABLE_NAME = 'erply_mapping';
    const ID_NAME = 'id_erply_mapping';
    
    /** @var integer Mapping id */
    public $id_erply_mapping;
    /** @var string Erply Customer Code */
    public $erply_code;
    /** @var string Object type */
    public $object_type;
    /** @var integer Local object id */
    public $local_id;
    /** @var integer ERPLY object id */
    public $erply_id;
    /** @var text Validate */
    public $info;
    
    public static $definition = array(
        'table' => self::TABLE_NAME, 
        'primary' => self::ID_NAME, 
        'fields' => array(
            'erply_code' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 50, 'required' => true), 
            'object_type' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 50, 'required' => true), 
            'local_id' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true), 
            'erply_id' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true), 
            'info' => array('type' => self::TYPE_STRING)
        )
    );
    
    
    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id_erply_mapping;
    }
    
    /**
     * @return integer
     */
    public function getPrestaId()
    {
        return $this->local_id;
    }
    
    /**
     * @return integer
     */
    public function getErplyId()
    {
        return $this->erply_id;
    }
    
    /**
     * @param string $key
     * @return mixed
     */
    public function getInfo($key = null)
    {
        if(is_string($this->info)) {
            $this->info = unserialize($this->info);
        }
        
        if(!is_array($this->info)) {
            $this->info = array();
        }
        
        if(is_null($key)) {
            return $this->info;
        }
        
        return isset($this->info[$key]) ? $this->info[$key] : null;
    }
    
    /**
     * @param string | array $key
     * @param mixed $val
     * @return ErplyMapping
     */
    public function setInfo($key, $val = null)
    {
        if(is_array($key)) {
            $this->info = $key;
            
            return $this;
        }
        
        if(!is_array($this->info)) {
            $this->info = array();
        }
        $this->info[$key] = $val;
        
        return $this;
    }
    
    /**
     * @param array
     */
    public function getFields()
    {
        parent::validateFields();
        
        if(isset($this->id)) {
            $fields['id_erply_mapping'] = intval($this->id);
        }
        $fields['erply_code']  = strval($this->erply_code);
        $fields['object_type'] = strval($this->object_type);
        $fields['local_id']    = intval($this->local_id);
        $fields['erply_id']    = intval($this->erply_id);
        $fields['info']        = strval($this->info);
        
        return $fields;
    }
    
    /**
     * Mark: added args to remove warns
     * @return boolean
     */
    public function add($auto_date = true, $null_values = false)
    {
        // if($manual_customer_code) {
        $this->erply_code = ErplyFunctions::getErplyCustomerCode();
        // }
        $this->info       = serialize($this->info);
        return parent::add($auto_date, $null_values);
    }
    
    /**
     * Mark: added args to remove warns
     * @return bool
     */
    public function update($null_values = false)
    {
        $this->info = serialize($this->info);
        return parent::update($null_values);
    }
    
    /*
     * Static methods
     */
    
    
    /**
     * Get single Mapping by object_type and if $fieldName == $fieldValue
     *
     * @param string $objectType
     * @param string $fieldName - local_id or erply_id
     * @param integer $fieldValue
     * @return ErplyMapping
     */
    public static function getMapping($objectType, $fieldName, $fieldValue)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'erply_mapping` 
                WHERE ' . self::erplyWhere() . '
                AND `object_type` = \'' . $objectType . '\' 
                AND `' . $fieldName . '` = ' . $fieldValue;
        
        $row = Db::getInstance()->getRow($sql);
        if(is_array($row)) {
            $resp                   = new ErplyMapping();
            $resp->id               = $row['id_erply_mapping'];
            $resp->id_erply_mapping = $row['id_erply_mapping'];
            $resp->erply_code       = $row['erply_code'];
            $resp->object_type      = $row['object_type'];
            $resp->local_id         = $row['local_id'];
            $resp->erply_id         = $row['erply_id'];
            $resp->info             = unserialize($row['info']);
            return $resp;
        }
        
        return null;
    }
    
    public static function getMappings($objectType)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'erply_mapping` 
                WHERE ' . self::erplyWhere() . '
                AND `object_type` = \'' . $objectType . '\'';
        
        if($res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
            $return = array();
            foreach($res as $row) {
                $erplyMapping                   = new ErplyMapping();
                $erplyMapping->id               = $row['id_erply_mapping'];
                $erplyMapping->id_erply_mapping = $row['id_erply_mapping'];
                $erplyMapping->erply_code       = $row['erply_code'];
                $erplyMapping->object_type      = $row['object_type'];
                $erplyMapping->local_id         = $row['local_id'];
                $erplyMapping->erply_id         = $row['erply_id'];
                $erplyMapping->info             = unserialize($row['info']);
                $return[]                       = $erplyMapping;
            }
            return $return;
        }
        return false;
    }
    
    public static function getMappingIds($objectType, $idType)
    {
        $sql = 'SELECT ' . $idType . ' FROM `' . _DB_PREFIX_ . 'erply_mapping` 
				WHERE ' . self::erplyWhere() . '
				AND `object_type` = \'' . $objectType . '\'';
        
        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if(is_array($res) && count($res)) {
            $return = array();
            foreach($res as $it) {
                $return[] = (int) $it[$idType];
            }
            return $return;
        }
        return false;
    }
    
    public static function getMappingErplyIds($objectType)
    {
        return self::getMappingIds($objectType, 'erply_id');
    }
    
    public static function getMappingPrestaIds($objectType)
    {
        return self::getMappingIds($objectType, 'local_id');
    }
    
    public static function getMappingIdsPair($objectType, $local_id = null)
    {
        $sql = 'SELECT local_id, erply_id FROM `' . _DB_PREFIX_ . 'erply_mapping`
                WHERE ' . self::erplyWhere() . ($local_id && is_int($local_id) ? ' AND local_id=' . $local_id : '') . '
                AND `object_type` = \'' . $objectType . '\'';
        
        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if(is_array($res) && count($res)) {
            return $res;
        }
        return false;
    }
    
    public static function getMappingIdsByParent($idParent, $objectType)
    {
        $sql = 'SELECT id_erply_mapping, info FROM `' . _DB_PREFIX_ . 'erply_mapping`
				WHERE ' . self::erplyWhere() . '
                AND `object_type` = \'' . $objectType . '\'';
        
        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        
        if(!$res) {
            ErplyFunctions::log(__FUNCTION__ . ' no result');
            return false;
        }
        
        $return = array();
        foreach($res as $row) {
            if(is_string($row['info'])) {
                $info = unserialize($row['info']); // TODO: if !isset($info['parent'])
                if(is_array($info) && $info['parent'] == $idParent) {
                    ErplyFunctions::log(__FUNCTION__ . ' found ' . $objectType . ' id_erply_mapping: ' . $row['id_erply_mapping']);
                    $return[] = $row['id_erply_mapping'];
                }
            }
        }
        
        if(is_array($return) && count($return)) {
            return $return;
        }
        
        return false;
    }
    
    public static function deleteParentAndChildrenMappingsByPrestaId($prestaId, $objectType, $parentPrefix = 'Parent')
    {
        $parentObject         = $parentPrefix . $objectType;
        $parentProductMapping = ErplyMapping::getMapping($parentObject, 'local_id', $prestaId);
        if($parentProductMapping) {
            $parentProductMapping->delete();
            ErplyFunctions::log(__FUNCTION__ . ' delete ' . $parentObject . ' id:' . $prestaId);
            
            $productCombinationMappingIds = ErplyMapping::getMappingIdsByParent($prestaId, $objectType);
            if($productCombinationMappingIds) {
                ErplyFunctions::log(__FUNCTION__ . ' ' . $objectType . ' children ids count: ' . count($productCombinationMappingIds));
                $idStr = implode(',', $productCombinationMappingIds);
                $table = _DB_PREFIX_ . 'erply_mapping';
                $where = self::erplyWhere() . '
                         AND `id_erply_mapping` in (' . $idStr . ')';
                $res   = Db::getInstance()->delete($table, $where);
                if(!$res) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }
    
    public static function deleteAll()
    {
        $table = _DB_PREFIX_ . 'erply_mapping';
        return Db::getInstance()->delete($table);
    }
    
    public static function deleteByObjectType($objectType)
    {
        $table = _DB_PREFIX_ . 'erply_mapping';
        $where = self::erplyWhere() . 'AND `object_type` = \'' . $objectType . '\'';
        
        return Db::getInstance()->delete($table, $where);
    }
    
    public static function getDistinctObjectTypes()
    {
        $sql = 'SELECT DISTINCT object_type 
                FROM ' . _DB_PREFIX_ . 'erply_mapping;';
        $db  = Db::getInstance(_PS_USE_SQL_SLAVE_);
        return $db->executeS();
    }
    
    protected static function erplyWhere()
    {
        return '`erply_code` = \'' . ErplyFunctions::getErplyCustomerCode() . '\'';
    }
}
;
