<?php

class AB_Acl_Resource_Model implements Zend_Acl_Resource_Interface
{

  protected $_resourceId;

  /**
   *
   * @param $resourceId
   * @return unknown_type
   */
  public function __construct($resourceId)
  {
    $this->_resourceId = (string) "model::".$resourceId;
  }

  public function getResourceId()
  {
    return $this->_resourceId;
  }

}