<?php

class My_View_Helper_GetContactCount extends Zend_View_Helper_Abstract {
  
  public function getContactCount(){
    return AB_Model_Gateway::Factory("Contact")
      ->getContact()
      ->byUserId($this->view->me->id)
      ->count();
  }

}