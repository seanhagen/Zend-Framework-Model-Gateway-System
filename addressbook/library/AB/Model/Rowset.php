<?php

class AB_Model_Rowset extends Zend_Db_Table_Rowset_Abstract
{
  protected $_sphinxData = array();

  public function setSphinxData(array $data)
  {
    $this->_sphinxData = $data;
  }

  public function getSphinxData(){
    return $this->_sphinxData;
  }

  public function current()
  {
    $cur = parent::current();
    try {
      if ( isset($this->_sphinxData[$cur->id]) ){
        $cur->sphinx_weight = $this->_sphinxData[$cur->id];
      }
    } catch ( Exception $e ){}
    return $cur;
  }

  public function offsetUnset($offset)
  {
    if (isset($this->_data[$offset])) {
      $this->_data[$offset] = false;
      $this->_data = array_merge(array(),array_filter($this->_data));
      $this->_count--;
    }
  }

  public function shuffle(){
    return shuffle($this->_data);
  }

  /**
   * Returns a model from the rowset by id
   *
   * @param id int
   * @return model or false
   */
  public function getModelById($id = null){
    $row = false;
    $this->rewind();
    foreach ( $this->_data as $key=>$data) {
      if ( isset($data['id']) &&
        intval($data['id']) > 0 &&
        intval($data['id']) == intval($id) ) {
        try {
          $row = $this->getRow($key);
          return $row;
        } catch ( Exception $e) {}

      }
    }
    return $row;
  }

  public function getModelByKey($key = null){
    $row = false;
    foreach ( $this->_data as $kk=>$data) {
      if ( $k == $key ) {
	try {
	  $row = $this->getRow($key);
	} catch ( Exception $e ) {}
      }
    }
    return $row;
  }
}