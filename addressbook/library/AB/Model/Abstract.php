<?php

class AB_Model_Abstract extends Zend_Db_Table_Row_Abstract
{

  protected $_insertValidationChain = array();
  protected $_updateValidationChain = array();

  protected $_filterChain = array();

  protected $_validationErrors = array();

  protected $_defaults = array();

  protected $_syntheticFields = array();

  public function __construct(array $config)
  {
    parent::__construct($config);
  }

  public function init()
  {
    parent::init();
  }

  /**
   * Returns the model gateway
   *
   * @return AB_Model_Gateway
   */
  public function getGateway()
  {
    return $this->getTable();
  }

  /**
   * Sets the model gateway
   *
   * @param AB_Model_Gateway $gateway
   * @return AB_Model_Abstract
   */
  public function setGateway(AB_Model_Gateway $gateway)
  {
    if ($gateway instanceof AB_Model_Gateway) {
      $this->setTable($gateway);
    }
    return $this;
  }

  /**
   * Returns an array of table columns
   *
   * @return array
   */
  public function properties()
  {
    return $this->getTable()->info('cols');
  }

  /**
   * Subclasses can use this method in the init() to map a model "property" to a protected
   * method in the model in order to generate programatic values.
   *
   * @param string $name
   * @param string $method
   * @param boolean $cacheResult
   * @param boolean $settable
   * @return AB_Model_Abstract
   */
  public function addSyntheticField($name, $method, $cacheResult = true, $settable = false)
  {
    $getMethod = "_get".$method;
    if (method_exists($this,$getMethod)) {
      $this->_syntheticFields[$name] = array('getMethod' => $getMethod, 'value' => null, 'cache' => $cacheResult);
    }
    if ($settable) {
      $setMethod = "_set".$method;
      $this->_syntheticFields[$name]['setMethod'] = $setMethod;
    }

    return $this;
  }

  /**
   * Remove a synthetic field mapping
   *
   * @param string $name
   * @return void
   */
  public function removeSyntheticField($name)
  {
    unset($this->_syntheticFields[$name]);
  }

  /**
   * Magic method to handle associations, or hand down to parent
   *
   * @see Zend/Db/Table/Row/Zend_Db_Table_Row_Abstract#__call()
   */
  public function __call($name,$args)
  {

    // assocations
    if (strpos($name,"get")===0) {

      $className = AB_Inflector::classify(substr($name,3));

      if (class_exists($className)) {

        // has-one
        $child = $this->getGateway()->getAssociation('child');
        if (in_array($className,array_keys($child))) {
          return $this->_hasOne($className);
        }

        // has-many
        $children = $this->getGateway()->getAssociation('children');
        if (in_array($className,array_keys($children))) {
          return $this->_hasMany($className);
        }

        // belongs-to
        $parent = $this->getGateway()->getAssociation('parent');
        if (in_array($className,array_keys($parent))) {
          return $this->_belongsTo($className);
        }

        // has-and-belongs-to-many
        $peers = $this->getGateway()->getAssociation('peers');
        if (in_array($className,array_keys($peers))) {
          return $this->_hasAndBelongsToMany($className);
        }

      }

    }

    // association/disassociation
    if (strpos($name,"add")===0) {
      $className = AB_Inflector::classify(substr($name,3));
      if (class_exists($className)) {
        return $this->_associate($className,$args[0]);
      }
    }
    if (strpos($name,"remove")===0) {
      $className = AB_Inflector::classify(substr($name,6));
      if (class_exists($className)) {
        return $this->_disassociate($className,$args[0]);
      }
    }

    return parent::__call($name,$args);

  }

  /**
   * Checks the synthetic field registry before the table data.
   *
   * @param string $name
   * @see library/Zend-1.7.5/Db/Table/Row/Zend_Db_Table_Row_Abstract#__get()
   */
  public function __get($name)
  {
    // check for synthetic fields first
    if (in_array($name,array_keys($this->_syntheticFields))) {
      if (($this->_syntheticFields[$name]['value'] == null) || ($this->_syntheticFields[$name]['cache'] == false)) {
        $this->_syntheticFields[$name]['value'] = $this->{$this->_syntheticFields[$name]['getMethod']}();
      }
      return $this->_syntheticFields[$name]['value'];
    }

    return parent::__get($name);

  }

  public function __set($name, $value)
  {

    // check for synthetic fields first
    if (in_array($name,array_keys($this->_syntheticFields)) && !empty($this->_syntheticFields[$name]['setMethod'])) {
      $this->_syntheticFields[$name]['value'] = $this->{$this->_syntheticFields[$name]['setMethod']}($value);
    } else {
      // check for a filter chain
      if ( isset($this->_filterChain[$name]) ){
        $value = $this->_filterChain[$name]->filter($value);
      }
      parent::__set($name,$value);
    }

  }

  /**
   * Overrides to check the synthetic fields as well
   *
   * @see library/Zend-1.7.5/Db/Table/Row/Zend_Db_Table_Row_Abstract#__isset()
   */
  public function __isset($name)
  {
    if (in_array($name,array_keys($this->_syntheticFields))) {
      return isset($this->_syntheticFields[$name]['value']);
    }
    return parent::__isset($name);
  }

  /**
   * Override to include any synthetic fields in a toArray() dump
   *
   * @see library/Zend-1.7.5/Db/Table/Row/Zend_Db_Table_Row_Abstract#toArray()
   */
  public function toArray()
  {
    $syntheticData = array();
    foreach ($this->_syntheticFields as $name => $field) {
      if (isset($field['value'])) {
        if ( is_object($field['value']) && is_subclass_of($field['value'],"AB_Model_Abstract") ) {
          $syntheticData[$name]= $field['value']->toArray();
        } else {
          $syntheticData[$name]= $field['value'];
        }
      }
    }

    return array_merge($this->_data,$syntheticData);

  }

  /**
   * This method only works on has-one, has-many and
   * has-and-belongs-to-many associations, and creates
   * the link between the objects as needed.
   *
   * @param string $associatedClassName
   * @param AB_Model_Abstract $model
   * @return boolean
   */
  protected function _associate($associatedClassName,$model)
  {

    // record id
    if (is_integer($model)) {
      if (in_array($associatedClassName,array_keys($this->getGateway()->getAssociation('child')))
        || in_array($associatedClassName,array_keys($this->getGateway()->getAssociation('children')))
        || in_array($associatedClassName,array_keys($this->getGateway()->getAssociation('peers')))
      ) {
        $model = $this->{"get".$associatedClassName}()->byId($model)->loadOne();
      }
    }

    if ((get_class($model) == $associatedClassName) || is_subclass_of($model,$associatedClassName)) {

      // has-one
      if (in_array($associatedClassName,array_keys($this->getGateway()->getAssociation('child')))) {
        $association = $this->getGateway()->getAssociation('child',$associatedClassName);
        $parentIdField = $association['parentId'];
        $model->{$parentIdField} = $this->id;
        $model->save();
      }

      // has-many
      if (in_array($associatedClassName,array_keys($this->getGateway()->getAssociation('children')))) {
        $association = $this->getGateway()->getAssociation('children',$associatedClassName);
        $parentIdField = $association['parentId'];
        $model->{$parentIdField} = $this->id;
        $model->save();
      }

      // has-and-belongs-to-many
      if (in_array($associatedClassName,array_keys($this->getGateway()->getAssociation('peers')))) {
        $association = $this->getGateway()->getAssociation('peers',$associatedClassName);
        // check if our association already exists
        $existing = $this->{"get".$associatedClassName}()->byId($model->id)->loadOne();
        if (!$existing) {
          // create the link in the link table
	  $db = Zend_Db_Table_Abstract::getDefaultAdapter();
          $db->insert(
            $association['linkTable'],
            array(
              $association['thisId'] => $this->id,
              $association['peerId'] => $model->id,
              'created_at' => new Zend_Db_Expr('NOW()'),
              'updated_at' => new Zend_Db_Expr('NOW()'),
              'status' => AB_Model_Gateway::STATUS_ACTIVE
            )
          );
        }
      }

    }

  }

  protected function _disassociate($associatedClassName,$model)
  {

    // record id
    if (is_integer($model)) {

      //@TODO: add support for adding by id

      // array to create new model on the fly
    } elseif (is_array($model)) {

      //@TODO: add support for create n' add on the fly

      // existing model being passed in by reference
      //		} elseif ((get_class($model) == $associatedClassName) || is_subclass_of($model,$associatedClassName)) {
    } elseif (is_a($model,$associatedClassName)) {

      // has-one
      if (in_array($associatedClassName,array_keys($this->getGateway()->getAssociation('child')))) {

        $association = $this->getGateway()->getAssociation('child',$associatedClassName);

        $parentIdField = $association['parentId'];
        $model->{$parentIdField} = 0;
        $model->save();

      }

      // has-many
      if (in_array($associatedClassName,array_keys($this->getGateway()->getAssociation('children')))) {

        $association = $this->getGateway()->getAssociation('children',$associatedClassName);

        $parentIdField = $association['parentId'];
        $model->{$parentIdField} = 0;
        $model->save();

      }

      // has-and-belongs-to-many
      if (in_array($associatedClassName,array_keys($this->getGateway()->getAssociation('peers')))) {

        $association = $this->getGateway()->getAssociation('peers',$associatedClassName);

        // check if our association already exists
        $existing = $this->{"get".$associatedClassName}()->byId($model->id)->loadOne();
        if ($existing) {

          // create the link in the link table
	  $db = Zend_Db_Table_Abstract::getDefaultAdapter();
          $db->delete(
            $association['linkTable'],
            array(
              $association['thisId'] => $this->id,
              $association['peerId'] => $model->id
            )
          );

        }

      }

    }

  }

  /**
   * Initializes the child gateway (if needed) and sets up the
   * select() object for a query in a has-one relationship
   *
   * @param string $className
   * @return AB_Model_Gateway
   */
  protected function _hasOne($className)
  {
    $associationGateway = $this->getGateway()->getAssociationGateway($className);
    $association = $this->getGateway()->getAssociation('child',$className);

    $associationGateway->select(true,AB_Model_Gateway::LOADTYPE_ONE)
      ->where($association['parentId'].' = ?',$this->id);

    return $associationGateway;
  }

  /**
   * Initializes the child gateway (if needed) and sets up the
   * select() object for a query in a has-many relationship
   *
   * @param string $className
   * @return AB_Model_Gateway
   */
  protected function _hasMany($className)
  {
    $associationGateway = $this->getGateway()->getAssociationGateway($className);
    $association = $this->getGateway()->getAssociation('children',$className);

    $associationGateway->select(true)
      ->where($association['parentId'].' = ?',$this->id);

    return $associationGateway;
  }

  /**
   * Initializes the child gateway (if needed) and sets up the
   * select() object for a query in a belongs-to relationship
   *
   * @param string $className
   * @return AB_Model_Gateway
   */
  protected function _belongsTo($className)
  {
    $associationGateway = $this->getGateway()->getAssociationGateway($className);
    $association = $this->getGateway()->getAssociation('parent',$className);

    $associationGateway->select(true,AB_Model_Gateway::LOADTYPE_ONE)
      ->where('id = ?',$this->{$association['parentId']});

    return $associationGateway;
  }

  /**
   * Initializes the child gateway (if needed) and sets up the
   * select() object for a query in a has-and-belongs-to-many
   * relationship
   *
   * @param string $className
   * @return AB_Model_Gateway
   */
  protected function _hasAndBelongsToMany($className)
  {

    $associationGateway = $this->getGateway()->getAssociationGateway($className);
    $association = $this->getGateway()->getAssociation('peers',$className);

    $associationGateway->select(true)
      ->from($associationGateway->info('name'))
      ->join(array('lt' => $association['linkTable']),
        '`lt`.`'.$association['peerId'].'` = `'.$associationGateway->info('name').'`.`id`',
        array())
      ->where('`lt`.`'.$association['thisId'].'` = '.$this->id);

    return $associationGateway;

  }

  /**
   * Adds 2 events, pre-save and post-save
   */
  public function save()
  {
    $this->_save();
    parent::save();
    $this->_postSave();
  }

  protected function _save()
  {
  }

  protected function _postSave()
  {
  }


  /**
   * This runs before the insert()
   *
   * @see library/Zend-1.7.5/Db/Table/Row/Zend_Db_Table_Row_Abstract#_insert()
   */
  protected function _insert()
  {
    $this->_validationErrors = array();
    // run through our insert validation chain here and throw an exception if validation fails
    foreach ($this->_insertValidationChain as $field => $validators) {
      foreach ($validators as $validator) {
        if (!$validator->isValid($this->{$field})) {
          $this->_addError($field,current($validator->getMessages()));
        }
      }
    }
    if (count($this->errors())) {
      throw new Exception("Validation errors were encoutered when trying to insert the model.");
    }

    // set defaults before insert, if field has not been modified
    foreach ($this->_defaults as $field => $value) {
      if ($this->_modifiedFields[$field] != true) {
        $this->__set($field,$value);
      }
    }

  }

  /**
   * This runs before the update()
   *
   * @see library/Zend-1.7.5/Db/Table/Row/Zend_Db_Table_Row_Abstract#_update()
   */
  protected function _update()
  {
    $this->_validationErrors = array();
    // run through our update validation chain here and throw an exception if validation fails
    foreach ($this->_updateValidationChain as $field => $validators) {
      foreach ($validators as $validator) {
        if (!$validator->isValid($this->{$field})) {
          $this->_addError($field,current($validator->getMessages()));
        }
      }
    }
    if (count($this->errors())) {
      throw new Exception("Validation errors were encoutered when trying to update the model.");
    }

  }

  /**
   * Returns the array of model errors that is populated by the validation chain
   *
   * @param string $field
   * @return array
   */
  public function errors($field = null)
  {
    return isset($field) ? $this->_validationErrors[$field] : $this->_validationErrors ;
  }

  /**
   * Adds an error to the errors array
   * @param string $field
   * @param string $message
   * @return void
   */
  protected function _addError($field, $message)
  {
    $this->_validationErrors[$field][] = $message;
  }

  /**
   * Adds a validator to the insert validation chain
   *
   * @param string|array $field
   * @param Zend_Validate_Abstract $validator
   * @return AB_Model_Abstract
   */
  public function addInsertValidator($field,Zend_Validate_Abstract $validator, $message = null)
  {
    if ( is_array($field) ){
      foreach ( $field as $f ){

        if (is_array($message)) {
          foreach ($message as $template => $msg) {
            $validator->setMessage($message,$template);
          }
        } elseif (!empty($message)) {
          $validator->setMessage($message);
        }

        $this->_insertValidationChain[$f][] = $validator;
      }
    } else {

      if (is_array($message)) {
        foreach ($message as $template => $msg) {
          $validator->setMessage($message,$template);
        }
      } elseif (!empty($message)) {
        $validator->setMessage($message);
      }

      $this->_insertValidationChain[$field][] = $validator;
    }
    //		$this->_insertValidationChain[$field] = array_unique($this->_insertValidationChain[$field]);
    return $this;
  }

  /**
   * Adds a validator to the update validation chain
   *
   * @param string|array $field
   * @param Zend_Validate_Abstract $validator
   * @return AB_Model_Abstract
   */
  public function addUpdateValidator($field,Zend_Validate_Abstract $validator, $message = null)
  {
    if ( is_array($field) ){
      foreach ( $field as $f ){

        if (is_array($message)) {
          foreach ($message as $template => $msg) {
            $validator->setMessage($message,$template);
          }
        } elseif (!empty($message)) {
          $validator->setMessage($message);
        }

        $this->_updateValidationChain[$f][] = $validator;
      }
    } else {

      if (is_array($message)) {
        foreach ($message as $template => $msg) {
          $validator->setMessage($message,$template);
        }
      } elseif (!empty($message)) {
        $validator->setMessage($message);
      }

      $this->_updateValidationChain[$field][] = $validator;
    }
    //		$this->_updateValidationChain[$field] = array_unique($this->_updateValidationChain[$field]);
    return $this;
  }

  /**
   *  Adds a validator to both the update and insert validation chain
   *  @param string|array $field
   *  @param Zend_Validate_Abstract $validator
   *  @return AB_Model_Abstract
   */
  public function addValidator($field, Zend_Validate_Abstract $validator, $message = null){
    return $this->addInsertValidator($field,$validator,$message)->addUpdateValidator($field,$validator,$message);
  }

  /**
   * Sets a default value to be used when creating a new record.  This
   * method should be called in the init() stage of a model creation to
   * set defaults values (such as SQL expressions or runtime values) that
   * cannot be set as defaults in the SQL table definition.
   *
   * @param string $field
   * @param mixed $value
   * @return AB_Model_Simsple
   */
  public function addDefaultValue($field,$value)
  {
    if (in_array($field,array_keys($this->_data)) && !isset($this->_defaults[$field])) $this->_defaults[$field] = $value;
    return $this;
  }

  /**
   * Add a filter that is applied on assignment ( before saving to database )
   *
   * @param string|array $field
   * @param Zend_Filter_Interface $filter
   * @return AB_Model_Abstract
   */
  public function addFilter($field, Zend_Filter_Interface $filter){
    if ( $filter instanceof Zend_Filter_Interface ){
      if ( is_array($field)) {
        foreach ( $field as $f ){
          if ( isset($this->_filterChain[$f])
            && is_a($this->_filterChain[$f],"Zend_Filter" ) ){
            $this->_filterChain[$f]->addFilter($filter);
          } else {
            $this->_filterChain[$f] = new Zend_Filter();
            $this->_filterChain[$f]->addFilter($filter);
          }
        }
      } else {
        if ( isset($this->_filterChain[$field])
          && is_a($this->_filterChain[$field],"Zend_Filter" ) ) {
          $this->_filterChain[$field]->addFilter($filter);
        } else {
          $this->_filterChain[$field] = new Zend_Filter();
          $this->_filterChain[$field]->addFilter($filter);
        }
      }
    }
    return $this;
  }

  /*
   * Hides model using STATUS_INACTIVE constant from AB_Model_Gateway
   */
  public function hide(){
    $this->updated_at = new Zend_Db_Expr('NOW()');
    $this->status = AB_Model_Gateway::STATUS_INACTIVE;
    parent::save();
    $this->_data = array();
    return null;
  }


  /**
   * This method should be overriden in subclasses to indicate how an object should be represented
   * if it must be returned as a string.
   *
   * @return string
   */
  public function toString()
  {
    return $this->_data['id'];
  }

}