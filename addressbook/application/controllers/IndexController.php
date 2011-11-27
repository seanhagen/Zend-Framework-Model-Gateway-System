<?php
/**
 * No actions in this controller.
 * Used to setup helpers:
 *  - acl
 *  - flash messenger
 *  - session 
 */
class IndexController extends Zend_Controller_Action
{
  /**
   * FlashMessenger helper
   *
   * @var Zend_Controller_Action_Helper_FlashMessenger
   */
  protected $_flashMessenger = null;

  /**
   * Acl helper
   *
   * @var AB_Controller_Action_Helper_AclHelper
   */
  protected $_acl = null;

  /**
   * Session
   *
   * @var Zend_Session_Namespace
   */
  protected $_session;

  /**
   * Current User
   *  only set if there is a current user.
   */
  public static $_me = null;

  public function init()
  {
    // initialize flash messenger helper to display session messages.
    $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

    // initialize acl helper.
    $this->_acl = Zend_Controller_Action_HelperBroker::getStaticHelper('AclHelper');

    // initialize session.
    $this->_session = new Zend_Session_Namespace();

    // setup css
    foreach ( array('screen','print','ie') as $css ) {
      $extra = ($css=='print')?"print":"screen, projection";
      $this->view->headLink()->appendStylesheet('/css/blueprint/'.$css.'.css',$extra);      
    }
    $this->view->headLink()->appendStylesheet('/css/main.css','screen, projection');

    $this->view->addHelperPath(APPLICATION_PATH . "/views/helpers/" , "My_View_Helper");
  }

  public function preDispatch(){
    // check access permission before doing anything!
    $this->_acl->checkGlobalAccess($this);

    //if user logged in, set $_me
    if (AB_Auth::getInstance()->active) {
      if (self::$_me === null) {  
        self::$_me = AB_Model_Gateway::Factory('User')
          ->getUser()
          ->byId(AB_Auth::getInstance()->data['User']['id'])
          ->loadOne();
      }
      
      $this->view->me = self::$_me;
    }
  }

  public function postDispatch(){
    // check whether a session message is set after action.
    if ($this->_flashMessenger->hasMessages()) {
      // if exists, get messages.
      $messages =  $this->_flashMessenger->getMessages();

      // only one message should be set for application.
      if($messages && count($messages) >= 1){
        foreach ($messages as $current_message) {
          // get the first message and seperate the message by '-'.
          $result = explode('_',$current_message);

          if (count($result) == 3) {
            $msg = $result[2];
            $code = (int) $result[1];
            $module = $result[0]."_";
          } else {
            $msg = $result[1];
            $code = (int) $result[0];
            $module = "";
          }

          // decide whether it's a success message or error message.
          switch ($code) {
            case 0: // if the message starts with '0_', then it's an error message.
              $field = $module.'error_msg';
              $this->view->{$field} = $msg;
              break;

            case 1: // if the message starts with '1_', then it's a success message.
              $field = $module.'success_msg';
              $this->view->{$field} = $msg;
              break;
          }
        }
      }
    }
  } // end postDispatch

}