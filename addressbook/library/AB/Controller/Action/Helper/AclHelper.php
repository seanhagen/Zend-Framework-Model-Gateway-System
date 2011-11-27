<?php
/** Zend_Controller_Action_Helper_Abstract */
require_once "Zend/Controller/Action/Helper/Abstract.php";

/**
 *	Acl Action Helper
 *
 *	Manage module, controller and action acl control with AB_Auth and AB_Acl
 *
 *	@author		Cindy Son <cindy@zeros2heroes.com>
 *
 *	@since		March 11, 2009
 *	@package	Zend_Controller_Action_Helper_Abstract
 */
class AB_Controller_Action_Helper_AclHelper extends Zend_Controller_Action_Helper_Abstract
{
  /** AB_Auth */
  protected $_auth;

  /** AB_Acl */
  protected $_acl;

  /** action controller */
  protected $_action;

  /**
   * Initialize authentication and acl.
   *
   * @params none
   * @return void
   */
  public function __construct(Zend_View_Interface $view = null, array $options = array())
  {
    $this->_auth = AB_Auth::getInstance();
    $this->_acl = AB_Acl::getInstance();
  }

  public function can($user, $action, $model)
  {
    return $this->_acl->can($user,$action,$model);
  }

  /**
   * To check if a user has permission to perform a specific action against a
   * model resource
   *
   * @param $action
   * @param $res
   * @return unknown_type
   */
  public function userCan($action, $model)
  {
    return $this->_acl->userCan($action,$model);
  }

  public function addUserPermission($model,$user_id,$action = 'any')
  {
    return $this->_acl->addUserPermission($model,$user_id,$action);
  }

  public function removeUserPermission($model,$user_id,$action = 'any')
  {
    return $this->_acl->removeUserPermission($model,$user_id,$action);
  }

  /**
   * Check current user's role is higher or equal to the given role.
   *
   * @params $role
   * @return boolean true or false
   */
  public function checkRole($role)
  {
    if ($this->_auth->active) {
      $current_user_role = "Member";

      if (($current_user_role == $role) || ($this->_acl->inheritsRole($current_user_role, $role, false))) {
        return true;
      }
    }

    return false;
  }

  /**
   * Check page access based on module, controller and action.
   *
   * @params $action - action controller.
   * @return void
   */
  public function checkGlobalAccess(&$action)
  {
    // default role.
    $role = 'Guest';

    // if user's logged in, get user's role.
    if ($this->_auth->active) {
      $role = "Member";
    }

    // get current module name, controller name and action name.
    $request = $action->getRequest();
    $module = $request->getModuleName();
    $controller = $request->getControllerName();
    $action = $request->getActionName();
    $format = $request->getParam('format', 'html');

    $resource = $module;
    $privilege = $action;

    // first check access control against the entire module.
    if (!$this->_acl->hasLocation($resource)) {

      // if not, check access control against the module's specific controller.
      $resource = $module."::".$controller;

      // if no module name and no module::controller name is set in acl config,
      if (!$this->_acl->hasLocation($resource)) {

        // check module_name::any for generic controllers permissions.
        $resource = $module."::any";

        if (!$this->_acl->hasLocation($resource)) {
          $resource = null;
        }
      }
    }

    // create our Acl_Resource object
    if (!is_null($resource)) {
      $resource = new AB_Acl_Resource_Location($resource);
    }

    // if access is not allowed, set error handler.
    if (is_null($resource)  || 
      !$this->_acl->isAllowed($role, $resource, $privilege) || 
      !$this->_acl->isAllowed($role, $resource, 'any')) {

      // set error type.
      $error = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
      $error->type = 'PAGE_NOT_FOUND';
      $permission_denied = true;

      // find a reason why access is denied.
      if (!$this->_acl->isAllowed($role, $resource, $privilege)) {

        // find who has access to the resource and privilege.
        $allowed_role = $this->_acl->getAllowedRole($resource, $privilege);

        if (is_null($allowed_role)) {
          $allowed_role = $this->_acl->getAllowedRole($resource, 'any');

          if ($allowed_role == 'Guest' || $this->checkRole($allowed_role)) {
            // permission is allowed for guest.
            $permission_denied = false;
          }
        }
      }

      if ($permission_denied) {
        switch ($allowed_role) {
          case 'Guest':
            $error->type = 'PAGE_NOT_FOUND';
            break;

          case 'Member':
            $error->type = 'LOGIN_REQUIRED';
            break;

          default:
            $error->type = 'PERMISSION_DENIED';
            break;
        }

        $request->setParam('error_handler', $error)
          ->setParam('format', $format)
          ->setModuleName('default')
          ->setControllerName('error')
          ->setActionName('error')
          ->setDispatched(false);
      }
    }
  }
}