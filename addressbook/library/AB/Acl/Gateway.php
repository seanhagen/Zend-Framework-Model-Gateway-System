<?php

class AB_Acl_Gateway extends AB_Model_Gateway
{
  protected $_name = 'acl';

  public function init()
  {
    $this->_modelClass = 'AB_Acl_Record';

    $this->_mapAssociations();

    $this->setRowsetClass('AB_Model_Rowset');

    $this->setRowClass($this->_modelClass);

    // create a blank row as a reference model
    $this->_model = $this->create();

  }


  public function find($model, $user_id, $action, $exact = false) {

    $resource_type = get_class($model);
    $resource_id = $model->id;

    if (!$exact) $action = array($action,'any');

    return $this->_get('AB_Acl_Record')
      ->byUserId($user_id)
      ->byResourceType($resource_type)
      ->byResourceId($resource_id)
      ->byAction($action)
      ->byStatus(AB_Model_Gateway::STATUS_ACTIVE)
      ->loadOne();

  }

  public function add($model, $user_id, $action = 'any')
  {
    $resource_type = get_class($model);
    $resource_id = $model->id;

    if (!$this->find($model,$user_id,$action)) {
      $this->insert(array(
          'resource_type' => $resource_type,
          'resource_id' => $resource_id,
          'user_id' => $user_id,
          'action' => $action,
          'allow' => 1
        ));
    }
  }

  public function remove($model, $user_id, $action = 'any')
  {
    $record = $this->find($model,$user_id,$action,true);
    if ($record) {
      $record->delete();
      return true;
    }
    return false;
  }

  protected function byAction($value)
  {
    if (!empty($value)) {
      if (is_array($value)) {
        return $this->where("`".$this->_name."`.`action` IN('".join("','",$value)."')");
      } else {
        return $this->_by('action',$value);
      }
    } else {
      return $this;
    }
  }


}