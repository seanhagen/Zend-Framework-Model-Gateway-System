<?php
/**
 *	Class Session
 *
 *	Manages a session record
 */
class Session extends AB_Model_Abstract {
  /**
   * Sets default validation rules, filters and values
   *
   * @param  none
   * @return void
   */
  public function init()
  {
    // set up our defaults for new Session objects
    $this->addDefaultValue('ip', $_SERVER['REMOTE_ADDR'])
      ->addDefaultValue('browser', $_SERVER['HTTP_USER_AGENT'])
      ->addDefaultValue('disabled', 0);

    $this->addSyntheticField('user',"User");

    parent::init();
  }

  protected function _getUser(){
    return AB_Model_Gateway::Factory("User")
      ->getUser()
      ->where("id = ?",$this->user_id)
      ->canCache(false)
      ->loadOne();
  }

  /**
   * Logs out session
   *
   * @param  none
   * @return void
   */
  public function logout()
  {
    $this->disabled = 1;
    $this->save();
  }
}