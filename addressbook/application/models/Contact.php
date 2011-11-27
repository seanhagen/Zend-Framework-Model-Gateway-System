<?php

class Contact extends AB_Model_Abstract { 

  public function init(){
    $this->addValidator("first_name", new Zend_Validate_NotEmpty(), "First name is required")
      ->addValidator("last_name", new Zend_Validate_NotEmpty(), "Last name is required")
      ->addValidator("email", new Zend_Validate_NotEmpty(), "Email is required")
      ->addValidator("email", new Zend_Validate_EmailAddress(), "Not a valid email address");

    $this->addDefaultValue('user_id',AB_Auth::getInstance()->data['User']['id']);

    parent::init();
  }
  
}