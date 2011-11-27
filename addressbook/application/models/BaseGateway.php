<?php
/**
 *	Class BaseGateway
 *	Specifies specific gateway rules
 */
class BaseGateway extends AB_Model_Gateway {
  /**
   * Active status only
   * @var boolean
   */
  public $_active_only = true;

  /**
   * Put the deleted record constraint and channel constraint before calling load()
   *
   * @see library/Z2h/Model/AB_Model_Gateway#load()
   */
  public function load($dumpQuery = false, $exit = false)
  {
    if ($this->_active_only) {
      $this->where($this->_name.'.status = ?',AB_Model_Gateway::STATUS_ACTIVE);
    } else {
      $this->where($this->_name.'.status > ?',AB_Model_Gateway::STATUS_DELETED);
    }

    return parent::load($dumpQuery,$exit);
  }

  /**
   * Override to include the deleted record constraint
   *
   * @see library/Z2h/Model/AB_Model_Gateway#count()
   */
  public function count($dumpQuery = false, $exit = false)
  { 
    if ($this->_active_only) {
      $this->where($this->_name.'.status = ?',AB_Model_Gateway::STATUS_ACTIVE);
    } else {
      $this->where($this->_name.'.status > ?',AB_Model_Gateway::STATUS_DELETED);
    }

    return parent::count($dumpQuery,$exit);
  }

  public function having($having){
    $this->_select->having($having);
    return $this;
  }

  /**
   * Adds a 'date' field based on created_at so that functions that need date but not time will have it
   */
  public function addDateField($date_field = 'created_at'){
    $select = $this->_select;
    if ( !preg_match("/FROM/",$select->__toString())) {
      $select->from($this->_name);
    }
    $select->columns(new Zend_Db_Expr("date({$date_field}) as `date`"));
    return $this;
  }

  /**
   * Filter only today items.
   *
   * @param  none
   * @return AB_Model_Gateway
   */
  public function todayOnly()
  {
    $this->where("date({$this->_name}.created_at) = date(now())");

    return $this;
  }

  /**
   * Sort records.
   *
   * @param  string $field
   * @param  string $order
   * @return AB_Model_Gateway
   */
  public function sortBy($field = 'created_at', $order = 'DESC')
  {
    $this->order("{$this->_name}.{$field} {$order}");
    return $this;
  }

}