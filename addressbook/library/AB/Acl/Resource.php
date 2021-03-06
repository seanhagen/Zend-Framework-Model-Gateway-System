<?php

class AB_Acl_Resource implements Zend_Acl_Resource_Interface
{

  protected $_resourceId;

  /**
   *
   * Stores a resource id in the format Model::id
   *
   * @param $resourceId
   * @return unknown_type
   */
  public function __construct($resourceId)
  {
    if (is_a($resourceId,"AB_Model_Abstract")) {
      $this->_resourceId = sprintf("%s::%d",$resourceId->getGateway->getRowClass(),$resourceId->id);
    } elseif (is_array($resourceId)) {
      $this->_resourceId = sprintf("%s::%s",$resourceId[0],$resourceId[1]);
    } else {
      $this->_resourceId = (string) $resourceId."::ALL";
    }
  }

  public function getResourceId()
  {
    return $this->_resourceId;
  }


}