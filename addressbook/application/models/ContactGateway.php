<?php

class ContactGateway extends BaseGateway {
  
  protected $_name = "contacts";

  protected $_parents = array("User");

  public function getLetters($user_id = null){
    $user_id = intval($user_id);
    if ( $user_id > 0 ) {
      $db = Zend_Db_Table_Abstract::getDefaultAdapter();
      return $db->fetchAll("select distinct substring(last_name,1,1) letter from contacts where user_id={$user_id} and status=".self::STATUS_ACTIVE);
    }

    return array();
  }

}