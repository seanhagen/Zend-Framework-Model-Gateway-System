<?php

class AB_Model_Gateway extends Zend_Db_Table_Abstract
{

  /**
   * Status codes
   */
  const STATUS_DELETED = -1;
  const STATUS_INACTIVE = 0;
  const STATUS_PENDING = 5;
  const STATUS_ACTIVE = 10;

  /**
   * criteria for field-based "by<Field>()" matching
   */
  const MATCH_EXACT = 'exact';
  const MATCH_START = 'start';
  const MATCH_END = 'end';
  const MATCH_ANY = 'any';

  const LOADTYPE_MANY = 'many';
  const LOADTYPE_ONE = 'one';

  /**
   * The primary key, defaults to 'id'
   *
   * @var string
   */
  protected $_primary = 'id';

  /**
   * Internal query composition tool
   * A Zend_Db_Table_Select instance
   *
   * @var Zend_Db_Table_Select
   */
  protected $_select;

  /**
   * A list of models that are parents to this model (belongs-to)
   *
   * @var array
   */
  protected $_parent = array();

  /**
   * A list of models that are single children to this model (has-one)
   *
   * @var array
   */
  protected $_child = array();

  /**
   * A list of models that are multiple children to this model (has-many)
   *
   * @var array
   */
  protected $_children = array();

  /**
   * A list of models that are peers to this model (has-and-belongs-to-many)
   *
   * @var array
   */
  protected $_peers = array();

  /**
   * Stores the Model gateway objects for each association
   *
   * @var array
   */
  protected $_associationGateways = array();

  /**
   * When load() is called, are we returning one or many rows?
   *
   * @var unknown_type
   */
  protected $_loadType = self::LOADTYPE_MANY;

  /**
   * An empty model to use for reference
   *
   * @var AB_Model_Abstract
   */
  protected $_model;

  /**
   * Name of the base class for this Gateway.  Used to check
   * for parenting when setting up rowClass to return a
   * subclass
   *
   * @var string
   */
  protected $_modelClass;

  /**
   * Can the model values be cached in our dataCache?
   *
   * @var boolean
   */
  protected $_canCache = false;

  /**
   * Can the model values be cached in our dataCache?
   *
   * @var boolean
   */
  protected $_cacheLifetime = false;

  /**
   * Returns a model gateway of the specified
   * class.
   *
   * @param string $model
   * @return AB_Model_Gateway
   */
  static public function Factory($model)
  {
    $gatewayClass = $model."Gateway";
    if (class_exists($gatewayClass)) {
      return new $gatewayClass;
    } else {
      throw new Exception('No such gateway: '.$gatewayClass);
    }
  }

  /**
   * Set up our Row and Rowset classes
   *
   * @see Zend/Db/Table/Zend_Db_Table_Abstract#init()
   */
  public function init()
  {
    $this->_modelClass = AB_Inflector::classify(substr(get_class($this),0,-7));

    $this->_mapAssociations();

    $this->setRowsetClass('AB_Model_Rowset');

    $this->setRowClass($this->_modelClass);

    $this->_cacheLifetime = Zend_Registry::get('configuration')->cache->lifetime;

    // create a blank row as a reference model
    $this->_model = $this->create();
  }

  protected function _cacheLoad($select)
  {
    $dc = Zend_Registry::get('dataCache');
    $key = $this->_cacheKey($select->__toString());
    return $dc->load($key);
  }

  protected function _cacheKey($val)
  {
    $id = Zend_Registry::get('configuration')->cache->id;
    return 'k'.$id.md5($val);
  }

  protected function _cacheSave($value,$select)
  {
    $key = $this->_cacheKey($select->__toString());
    $dc = Zend_Registry::get('dataCache');
    $tags = array(); //@todo: no tags are used for caching at the moment.
    //@question: no tags because the memcached backend for Zend_Cache doesn't support them?
    $dc->save($value,$key,$tags,$this->_cacheLifetime);
  }

  /**
   *
   * @see Zend/Db/Table/Zend_Db_Table_Abstract#fetchAll()
   */
  public function fetchAll($where = null, $order = null, $count = null, $offset = null)
  {

    if (!($where instanceof Zend_Db_Table_Select)) {
      $this->_select = $this->select(true);

      if ($where !== null) {
        $this->where($where);
      }

      if ($order !== null) {
        $this->order($order);
      }

      if ($count !== null || $offset !== null) {
        $this->_select->limit($count, $offset);
      }

    }

    return parent::fetchAll($this->_select);
  }

  /**
   *
   * @see Zend/Db/Table/Zend_Db_Table_Abstract#fetchRow()
   */
  public function fetchRow($where = null, $order = null)
  {

    if (!($where instanceof Zend_Db_Table_Select)) {
      $this->select(true);

      if ($where !== null) {
        $this->where($where);
      }

      if ($order !== null) {
        $this->order($order);
      }

      $this->_select->limit(1);

    }

    return parent::fetchRow($this->_select);
  }

  /**
   * Adds in support for caching the SQL result if asked to do so
   *
   * @see library/Zend-1.7.5/Db/Table/Zend_Db_Table_Abstract#_fetch()
   */
  protected function _fetch(Zend_Db_Table_Select $select)
  {
    if ($this->_canCache) {
      $data = $this->_cacheLoad($select);
      // instead of using !not condition, check false if nothing was cached before
      // when empty or zero value is cached.
      if ($data===false) {
        $data = parent::_fetch($select);
        $this->_cacheSave($data,$select);
      }
      return $data;
    } else {
      return parent::_fetch($select);
    }
  }


  /**
   * Magic method to add a fluent interface to the gateway, to retrieve sets of
   * models and also add associative constraints
   *
   * @param string $name
   * @param array $args
   * @return AB_Model_Gateway
   */
  public function __call($name,$args)
  {
    // check for a get method
    if (strpos($name,"get")===0) {
      //			$className = AB_Inflector::classify(substr($name,3));
      $className = AB_Inflector::singularize(substr($name,3));
      if (class_exists($className) && (($className == $this->_modelClass) || is_subclass_of($className,$this->_modelClass))) {
        return $this->_get($className);
      } else {
        throw new Exception("$className is not a subclass of ".$this->_modelClass);
      }
    }

    // use model metadata to add a where clause
    if (strpos($name,"by")===0) {
      $fieldName = AB_Inflector::underscore(substr($name,2));
      if (in_array($fieldName,$this->_model->properties())) {
	$type = (isset($args[1]))?$args[1]:null;
        return $this->_by($fieldName,$args[0],$type);
      }
    }

    // constrain if a relationship exists
    if (strpos($name,"from")===0) {
      $className = AB_Inflector::classify(substr($name,4));
      if (class_exists($className)) {
        return $this->_from($className,$args[0]);
      }
    }

  }

  protected function _associatedClasses()
  {
    return array_merge(
      array_keys($this->_child),
      array_keys($this->_children),
      array_keys($this->_parent),
      array_keys($this->_peers)
    );
  }

  public function getAssociation($type,$name = null) {
    return ($name != null) ? $this->{"_".$type}[$name] : $this->{"_".$type};
  }

  public function getAssociationGateway($name)
  {
    if (isset($this->_child[$name]['gateway'])) {
      return new $this->_child[$name]['gateway'];
    }
    if (isset($this->_children[$name]['gateway'])) {
      return new $this->_children[$name]['gateway'];
    }
    if (isset($this->_parent[$name]['gateway'])) {
      return new $this->_parent[$name]['gateway'];
    }
    if (isset($this->_peers[$name]['gateway'])) {
      return new $this->_peers[$name]['gateway'];
    }
  }

  protected function _isAssociation($className)
  {
    return in_array($className,$this->associatedClasses());
  }

  protected function _get($className)
  {
    // update the row class
    $this->setRowClass($className);
    // create a blank row as a reference model
    $this->_model = $this->create();

    $this->select(true)->from($this->_name);
    return $this;
  }

  protected function _by($fieldName,$value, $matching = AB_Model_Gateway::MATCH_EXACT)
  {
    if (!empty($value)) {

      switch ($matching) {
        case AB_Model_Gateway::MATCH_ANY:
          $operator = "LIKE";
          $value = "%".$value."%";
          break;
        case AB_Model_Gateway::MATCH_START:
          $operator = "LIKE";
          $value = $value."%";
          break;
        case AB_Model_Gateway::MATCH_END:
          $operator = "LIKE";
          $value = "%".$value;
          break;
        default:
          $operator = '=';
      }

      return $this->where("`".$this->_name."`.`".$fieldName."` ".$operator." ?",$value);
    } else {
      return $this;
    }
  }

  /**
   * This method only works on belongs-to and has-and-belongs-to-many
   * associations
   *
   * @param string $associatedClassName
   * @param array $classValues
   * @return AB_Model_Gateway
   */
  protected function _from($associatedClassName,$classValues)
  {

    if ($classValues != null) {

      $classValues = is_array($classValues) ? $classValues : array($classValues);


      // belongs to
      if (in_array($associatedClassName,array_keys($this->_parent))) {

        $this->_select
          ->where('`'.$this->_name.'`.`'.$this->_parent[$associatedClassName]['parentId']."` IN ('".join("','",$classValues)."')");

      }

      // has and belongs to many
      if (in_array($associatedClassName,array_keys($this->_peers))) {

        $this->_select
          ->join($this->_peers[$associatedClassName]['linkTable'], '`'.$this->_peers[$associatedClassName]['linkTable'].'`.`'.$this->_peers[$associatedClassName]['thisId'].'` = `'.$this->info('name').'`.`id`',				 array())
          ->where('`'.$this->_peers[$associatedClassName]['linkTable'].'`.`'.$this->_peers[$associatedClassName]['peerId']."` IN ('".join("','",$classValues)."')");

      }

    }

    return $this;

  }

  public function from($name, $cols = '*', $schema = null)
  {
    $this->select(true)->from($name,$cols,$schema);
    return $this;
  }

  /**
   * Maps to the association gateway select
   * @return AB_Model_Gateway
   */
  public function where($cond, $value = null, $type = null)
  {
    if ( !is_null($value) ) {
      if ( is_null($type) ){
        $this->_select->where($cond,$value);
      } else {
        $this->_select->where($cond,$value,$type);
      }
    } else {
      $cond = (array) $cond;
      foreach ($cond as $key => $val) {
        // is $key an int?
        if (is_int($key)) {
          // $val is the full condition
          $this->_select->where($val);
        } else {
          // $key is the condition with placeholder,
          // and $val is quoted into the condition
          $this->_select->where($key, $val);
        }
      }
    }
    return $this;
  }

  /**
   * Maps to the association gateway group
   * @param array|string $cond The column(s) to group by
   * @return AB_Model_Gateway
   */
  public function group($cond){
    if ( !is_null($cond) ){
      $this->_select->group($cond);
    }
    return $this;
  }

  public function orWhere($cond)
  {
    $cond = (array) $cond;
    foreach ($cond as $key => $val) {
      // is $key an int?
      if (is_int($key)) {
        // $val is the full condition
        $this->_select->orWhere($val);
      } else {
        // $key is the condition with placeholder,
        // and $val is quoted into the condition
        $this->_select->orWhere($key, $val);
      }
    }
    return $this;
  }

  public function order($spec)
  {
    $this->_select->order($spec);
    return $this;
  }

  public function limit($count = null, $offset = null)
  {
    $this->_select->limit($count, $offset);
    return $this;
  }

  public function limitPage($page, $rowCount)
  {
    $this->_select->limitPage($page, $rowCount);
    return $this;
  }

  public function loadOne($dumpQuery = false,$exit = false)
  {
    $this->_loadType = self::LOADTYPE_ONE;
    return $this->load($dumpQuery,$exit);
  }

  public function load($dumpQuery = false, $exit = false)
  {
    if ($dumpQuery) {
      echo $this->_select->__toString()."<br/>\n";
      if ( $exit ) exit(0);
    }
    return ($this->_loadType == self::LOADTYPE_ONE) ? $this->fetchRow($this->_select) : $this->fetchAll($this->_select);
  }

  public function count($dumpQuery = false,$exit = false)
  {
    $select = clone $this->_select;
    $select->limit(null,null);
    $select->reset('columns');
    //   $select->from($this->_name, array());
    $select->columns(new Zend_Db_Expr("COUNT({$this->_name}.id)"));

    if ( $dumpQuery ) {
      echo $select->__toString()."<br/>\n";
      if ( $exit ) exit(0);
    }
    return $select->query()->fetchColumn();
  }

  /**
   * sneaky combo function that returns an array
   * with the total rows and the (optionally) limited
   * resultset in one array
   *
   * @return array
   */
  public function countAndLoad($dump = FALSE, $exit = false)
  {
    //		echo $this->_select->__toString();
    //		exit(0);

    $result = array(
      'count' => $this->count($dump),
      'rows' => $this->load($dump)
    );

    if ( $exit ) {
      exit;
    }

    return $result;
  }

  public function canCache($value = true, $specificLifetime = false)
  {
    $this->_canCache = $value;
    $this->_cacheLifetime = $specificLifetime;

    return $this;
  }

  public function cleanCache($key)
  {
    $dc = Zend_Registry::get('dataCache');

    return $dc->remove($key);
  }

  /**
   * Returns a new (and unsaved) model populated
   * with the values from $data
   *
   * @param array $data
   * @param string $rowClass
   * @return AB_Model_Abstract
   */
  public function create($data = array(),$rowClass = null)
  {
    if ($rowClass) {
      $this->setRowClass($rowClass);
    }
    return parent::createRow($data,true);
  }

  /**
   * Extends the regular Db_Table insert() method to
   * automatically set our created_at and updated_at
   * fields
   *
   * @see Zend/Db/Table/Zend_Db_Table_Abstract#insert()
   */
  public function insert(array $data)
  {
    // these fields should never be set manually
    $data['created_at'] = new Zend_Db_Expr('NOW()');
    $data['updated_at'] = new Zend_Db_Expr('NOW()');
    if (!isset($data['status'])) {
      $data['status'] = AB_Model_Gateway::STATUS_ACTIVE;
    }

    return parent::insert($data);
  }

  /**
   * Extends the regular Db_Table update() method to
   * automatically set our updated_at field
   *
   * @see Zend/Db/Table/Zend_Db_Table_Abstract#update()
   */
  public function update(array $data, $where)
  {
    $data['updated_at'] = new Zend_Db_Expr('NOW()');
    return parent::update($data,$where);
  }
	 
  public function hide(array $data, $where)
  {
    $data['updated_at'] = new Zend_Db_Expr('NOW()');
    $data['status'] = AB_Model_Gateway::STATUS_INACTIVE;
    return parent::update($data,$where);
  }
	
  public function show(array $data, $where)
  {
    $data['updated_at'] = new Zend_Db_Expr('NOW()');
    $data['status'] = AB_Model_Gateway::STATUS_ACTIVE;
    return parent::update($data,$where);
  }

  /**
   * A fluent-interface method for updating all the records matched by a select.  Use this instead
   * of load() or countAndLoad()
   *
   * @param $conditions
   * @return unknown_type
   */
  public function updateResults($data, $dumpQuery = false)
  {

    // need to extract the where clause from the select()
    $where = join(" ",$this->_select->getPart('where'));

    if ($dumpQuery) {
      exit(0);
    }

    return $this->update($data,$where);

  }

  /**
   * Override the regular Db_Table delete() method to
   * not delete the actual database rows but rather update
   * them to set the status field to -1
   *
   * @see Zend/Db/Table/Zend_Db_Table_Abstract#delete()
   */
  public function delete($where, $realDelete = false)
  {
    return ($realDelete) ?
      parent::delete($where) :
      parent::update(array('status' => AB_Model_Gateway::STATUS_DELETED),$where) ;
  }

  /**
   * A fluent-interface method for deleting all the records matched by a select.  Use this instead
   * of load() or countAndLoad()
   *
   * @param $dumpQuery
   * @return unknown_type
   */
  public function deleteResults($realDelete = false, $dumpQuery = false)
  {

    // need to extract the where clause from the select()
    $where = join(" ",$this->_select->getPart('where'));

    if ($dumpQuery) {
      exit(0);
    }

    return ($realDelete) ? $this->realDelete($where) : $this->delete($where);
  }

  /**
   * Provide a method to actually delete rows if necessary, but this should
   * rarely be called in this context.
   *
   * @param string|Zend_Db_Table_Select $where
   * @return int
   */
  public function realDelete($where)
  {
    return parent::delete($where);
  }

  /*
   * Our gateway-like extensions start here
   */

  /**
   * Get gateway query compositor
   *
   * - A partially initialized Zend_Db_Select object may be injected
   * - Passing a boolean true will force the creation of a new compositor
   *
   * @param mixed Zend_Db_Table_Select $select / boolean
   * @return Zend_Db_Select
   */
  public function select($select = null,$loadType = self::LOADTYPE_MANY)
  {
    require_once 'Zend/Db/Table/Select.php';
    if($select instanceof Zend_Db_Table_Select){
      $this->_select = $select;
    } else if($select === true || is_null($this->_select)){
      $this->_select = new Zend_Db_Table_Select($this);
    }

    $this->_loadType = $loadType;

    return $this->_select;
  }


  /**
   * Internal Zend_Db_Table_Select reset method
   *
   * @param string $part
   */
  public function reset($part = null)
  {
    if($this->_select instanceof Zend_Db_Table_Select){
      $this->_select->reset($part);
    }
  }

  /**
   * Scans the model's association arrays and converts short-hand to
   * proper array-based structures
   *
   * @return void
   */
  protected function _mapAssociations()
  {
    $thisClass = $this->_modelClass;

    // has-one
    if (count($this->_child)) {
      $child = array();
      foreach ($this->_child as $key => $value) {

        if (is_integer($key)) {
          $_model = $value;
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping child associations for model ".$thisClass."!");
          }
          $child[$_model] = array(
            'parentId' => (($thisClass == $_model) ? 'parent_id' : AB_Inflector::foreignKey($thisClass))
          );

        } elseif (is_array($value)) {
          $_model = AB_Inflector::classify($key);
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping child associations for model ".$thisClass."!");
          }
          if (!isset($value['parentId'])) {
            $value['parentId'] = (($thisClass == $_model) ? 'parent_id' : AB_Inflector::foreignKey($thisClass));
          }
          $child[$key] = $value;

        } else {
          if (!class_exists($key)) {
            throw new Exception("No model class ".$key." found while mapping child associations for model ".$thisClass."!");
          }
          $child[$key] = array('parentId' => $value);

        }

        // set gateway class if needed
        if (!isset($child[$_model]['gateway'])) $child[$_model]['gateway'] = $_model."Gateway";

      }
      $this->_child = $child;
    }

    // has-many
    if (count($this->_children)) {
      $children = array();
      foreach ($this->_children as $key => $value) {

        if (is_integer($key)) {
          $_model = AB_Inflector::classify($value);
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping children associations for model ".$thisClass."!");
          }
          $children[$_model] = array(
            'parentId' => (($thisClass == $_model) ? 'parent_id' : AB_Inflector::foreignKey($thisClass))
          );

        } elseif (is_array($value)) {
          $_model = AB_Inflector::classify($key);
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping children associations for model ".$thisClass."!");
          }
          if (!isset($value['parentId'])) {
            $value['parentId'] = (($thisClass == $_model) ? 'parent_id' : AB_Inflector::foreignKey($thisClass));
          }
          $children[$_model] = $value;

        } else {
          $_model = AB_Inflector::classify($key);
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping children associations for model ".$thisClass."!");
          }
          $children[$_model] = array('parentId' => $value);

        }

        // set gateway class if needed
        if (!isset($children[$_model]['gateway'])) $children[$_model]['gateway'] = $_model."Gateway";

      }
      $this->_children = $children;
    }

    // belongs-to
    if (count($this->_parent)) {
      $parent = array();
      foreach ($this->_parent as $key => $value) {

        if (is_integer($key)) {
          $_model = AB_Inflector::classify($value);
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping parent associations for model ".$thisClass."!");
          }
          $parent[$_model] = array(
            'parentId' => (($thisClass == $_model) ? 'parent_id' : AB_Inflector::foreignKey($_model))
          );

        } elseif (is_array($value)) {
          $_model = AB_Inflector::classify($key);
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping parent associations for model ".$thisClass."!");
          }
          if (!isset($value['parentId'])) {
            $value['parentId'] = (($thisClass == $_model) ? 'parent_id' : AB_Inflector::foreignKey($_model));
          }
          $parent[$_model] = $value;

        } else {
          $_model = AB_Inflector::classify($key);
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping parent associations for model ".$thisClass."!");
          }
          $parent[$_model] = array('parentId' => $value);

        }

        // set gateway class if needed
        if (!isset($parent[$_model]['gateway'])) $parent[$_model]['gateway'] = $_model."Gateway";

      }
      $this->_parent = $parent;

    }

    // has-and-belongs-to-many
    if (count($this->_peers)) {
      $peers = array();
      foreach ($this->_peers as $key => $value) {

        if (is_integer($key)) {
          $_model = AB_Inflector::classify($value);
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping peer associations for model ".$thisClass."!");
          }
          $tables = array(
            AB_Inflector::tableize($this->_name),
            AB_Inflector::tableize($_model)
          );
          sort($tables);

          $peers[$_model] = array(
            'linkTable' => join('_',$tables),
            'thisId' => AB_Inflector::foreignKey($thisClass),
            'peerId' => AB_Inflector::foreignKey($_model)
          );

        } elseif (is_array($value)) {
          $_model = AB_Inflector::classify($key);
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping peer associations for model ".$thisClass."!");
          }
          if (!isset($value['linkTable'])) {
            $tables = array(AB_Inflector::tableize($this->_name),AB_Inflector::tableize($_model));
            sort($tables);
            $value['linkTable'] = join('_',$tables);
          }
          if (!isset($value['peerId'])) {
            $value['peerId'] = AB_Inflector::foreignKey($_model);
          }
          if (!isset($value['thisId'])) {
            $value['thisId'] = AB_Inflector::foreignKey($thisClass);
          }
          $peers[$_model] = $value;

        } else {

          $_model = AB_Inflector::classify($key);
          if (!class_exists($_model)) {
            throw new Exception("No model class ".$_model." found while mapping peer associations for model ".$thisClass."!");
          }

          $peers[$_model] = array(
            'linkTable' => $value,
            'thisId' => AB_Inflector::foreignKey($thisClass),
            'peerId' => AB_Inflector::foreignKey($_model)
          );

        }

        // set gateway class if needed
        if (!isset($peers[$_model]['gateway'])) $peers[$_model]['gateway'] = $_model."Gateway";

      }
      $this->_peers = $peers;
    }

  }


}