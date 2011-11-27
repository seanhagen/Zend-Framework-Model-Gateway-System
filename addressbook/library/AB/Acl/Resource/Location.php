<?php

class AB_Acl_Resource_Location implements Zend_Acl_Resource_Interface
{

  protected $_resourceId;

  /**
   *
   * @param $resourceId
   * @return unknown_type
   */
  public function __construct($resourceId)
  {
    $this->_resourceId = (string) "location::".$resourceId;
  }

  public function getResourceId()
  {
    return $this->_resourceId;
  }


}