<?php
require_once 'Zend/Acl.php';

/**
 *	Class AB_Acl
 *	Custom ACL implementation based on Zend_Acl
 */
class AB_Acl extends Zend_Acl
{

  protected static $_instance = null;

  protected $_ag = null;

  /**
   * Get acl roles and resources from config.php
   *
   * @param none
   * @return void
   */
  protected function __construct()
  {
    $configuration = Zend_Registry::get('configuration');

    $roles = $configuration->acl->roles->toArray();
    $locations = $configuration->acl->locations->toArray();
    $models = $configuration->acl->models->toArray();
    $this->_addRoles($roles);
    $this->_addLocations($locations);
    $this->_addModels($models);

    $this->_ag = new AB_Acl_Gateway();
  }

  /**
   * Return an instance of AB_Auth.
   *
   * Singleton pattern implementation
   *
   * @param  none
   * @return AB_Auth Provides a fluent interface
   */
  public static function getInstance()
  {
    if (null === self::$_instance) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }

  public function can($user, $action, $model)
  {
    if (is_numeric($user)) {
      $user = AB_Model_Gateway::Factory('User')->getUser()->byId($user)->loadOne();
    }
    if ($user instanceof User) {
      $aclRecord = $this->_ag->find($model,$user->id,$action);
      if ($aclRecord) {
        if ($aclRecord->allow == true) {
          return true;
        } else {
          return false;
        }
      }
      $resource = new AB_Acl_Resource_Model(get_class($model));
      if ($this->hasModel($resource)) {
        if ( $this->isAllowed($user->role, $resource, $action) || $this->isAllowed($user->role, $resource, 'any') ) {
          return true;
        }
      }
    }
    return false;
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
    $auth = AB_Auth::getInstance();
    $user_id = $auth->active ? $auth->data['User']['id'] : 0 ;
    if ($auth->active) {
      $role = "Member";
    } else {
      $role = "Guest";
    }
    // STEP 1 - Check for a specific permission record
    if ($user_id > 0) {
      $aclRecord = $this->_ag->find($model,$user_id,$action);
      if ($aclRecord->allow == true) {
        return true;
      }
    }
    $resource = new AB_Acl_Resource_Model(get_class($model));
    // STEP 2 - Check the standard ACL
    if ($this->hasModel($resource)) {
      if ( $this->isAllowed($role, $resource, $action) || $this->isAllowed($role, $resource, 'any') ) {
        return true;
      }
    }
    // default is to return false
    return false;
  }

  public function addUserPermission($model,$user_id,$action = 'any')
  {
    return $this->_ag->add($model,$user_id,$action);
  }

  public function removeUserPermission($model,$user_id,$action = 'any')
  {
    return $this->_ag->remove($model,$user_id,$action);
  }


  /**
   * Get a role name for given resource.
   *
   * @param $resource - resource name (module name or module::controller name)
   *        $privilege - action name
   *
   * @return void
   */
  public function getAllowedRole($resource, $privilege = null)
  {
    if ($this->has($resource)) {

      $configuration = Zend_Registry::get('configuration');

      // decompose resource name.
      $resource_parts = explode('::',$resource->getResourceId());

      array_shift($resource_parts);

      if (count($resource_parts) > 1) {
        // resource name is combination of module name and controller name.
        $resource_actions = $configuration->acl->locations->allow->{$resource_parts[0]}->{$resource_parts[1]}->toArray();
      } else {
        $resource_actions = $configuration->acl->locations->allow->{$resource_parts[0]}->all->toArray();
      }

      // get action names.
      $action_names = array_keys($resource_actions);
      $last_action_name = array_pop($action_names);

      if ($last_action_name == 'all') {
        return $resource_actions['all'];
      } else if (!is_null($privilege)) {
        foreach ($resource_actions as $action => $role) {
          if ($privilege == $action) {
            return $role;
          }
        }
      }
    }

    return null;
  }

  public function hasLocation($location)
  {
    if ($location instanceof AB_Acl_Resource_Location) {
      $resourceId = $location->getResourceId();
    } else {
      $resourceId = (string) "location::".$location;
    }
    return isset($this->_resources[$resourceId]);
  }

  public function hasModel($model)
  {
    if ($model instanceof AB_Acl_Resource_Model) {
      $resourceId = $model->getResourceId();
    } else {
      $resourceId = (string) "model::".$model;
    }
    return isset($this->_resources[$resourceId]);
  }

  public function removeLocation($location)
  {
    if ($location instanceof AB_Acl_Resource_Location) {
      $resourceId = $location->getResourceId();
    } else {
      $resourceId = (string) "location::".$location;
    }
    return $this->remove($resourceId);
  }

  public function removeModel($model)
  {
    if ($model instanceof AB_Acl_Resource_Model) {
      $resourceId = $model->getResourceId();
    } else {
      $resourceId = (string) "model::".$model;
    }
    return $this->remove($resourceId);
  }

  public function getLocation($location)
  {
    if ($location instanceof AB_Acl_Resource_Location) {
      $resourceId = $location->getResourceId();
    } else {
      $resourceId = (string) "location::".$location;
    }
    return $this->get($resourceId);
  }

  public function getModel($model)
  {
    if ($model instanceof AB_Acl_Resource_Model) {
      $resourceId = $model->getResourceId();
    } else {
      $resourceId = (string) "model::".$model;
    }
    return $this->get($resourceId);
  }


  /**
   * Add acl roles.
   *
   * @param array $roles
   * @return void
   */
  protected function _addRoles($roles)
  {
    foreach ($roles as $name => $parents) {
      if (!$this->hasRole($name)) {
        if (is_null($parents)) {
          $parents = null;
        } else {
          $parents = explode(',', $parents);
        }

        $this->addRole(new Zend_Acl_Role($name), $parents);
      }
    }
  }

  /**
   * Adds locations from the Zend_Config ACL section of our main
   * configuration script
   *
   * @param array $locations
   * @return void
   */
  protected function _addLocations($locations)
  {
    foreach ($locations as $permission => $modules) {
      foreach ($modules as $module => $controllers) {

        $controller_names = array_keys($controllers);
        $last_controller_name = array_pop($controller_names);

        switch ($last_controller_name) {
          case 'all':
            $resource_name = $module;
            if ($this->hasLocation($resource_name)) {
              $this->removeLocation($resource_name);
            }
            $this->addResource(new AB_Acl_Resource_Location($resource_name));
            $actions = $controllers['all'];

            $this->_addLocationActionPermissions($permission, $resource_name, $actions);

            break;
          default:
            foreach ($controllers as $controller => $actions) {
              if ($controller != 'all') {
                $resource_name = $module."::".$controller;
                if ($this->hasLocation($resource_name)) {
                  $this->removeLocation($resource_name);
                }
                $this->addResource(new AB_Acl_Resource_Location($resource_name));

                $this->_addLocationActionPermissions($permission, $resource_name, $actions);
              }
            }

        }
      }
    }
  }

  /**
   * Add acl models.
   *
   * @param array $resources
   * @return void
   */
  protected function _addModels($models)
  {
    // go through each permission (either 'allow' or 'deny') in acl.

    foreach ($models as $permission => $mod) {

      // go through each module in acl.
      foreach ($mod as $modName => $actions) {

        $resource_name = $modName;

        // in case we want to deny a resource that is allowed.
        if ($this->hasModel($resource_name)) {
          $this->removeModel($resource_name);
        }

        $this->addResource(new AB_Acl_Resource_Model($resource_name));

        // set action permissions.
        $this->_addModelActionPermissions($permission, $resource_name, $actions);
      }
    }
  }

  protected function _addLocationActionPermissions($permission, $resource_name, &$actions)
  {
    $this->_addActionPermissions($permission, "location::".$resource_name, $actions);
  }

  protected function _addModelActionPermissions($permission, $resource_name, &$actions)
  {
    $this->_addActionPermissions($permission, "model::".$resource_name, $actions);
  }

  /**
   * Add acl action permissions.
   *
   * @param string $permission
   *        string $resource_name
   *        array $actions
   *
   * @return void
   */
  protected function _addActionPermissions($permission, $resource_name, &$actions)
  {
    // search for 'all'. if 'all' is set as last,
    // permission should be given for all actions for the resource.
    $action_names = array_keys($actions);
    $last_action_name = array_pop($action_names);

    if ($last_action_name == 'all') {
      $action = null;
      $role = $actions['all'];

      if ($permission == 'allow') {
        $this->allow($role, $resource_name, $action);
      }

      if ($permission == 'deny') {
        $this->deny($role, $resource_name, $action);
      }

    } else {
      // go through each action.
      // if 'all' exists between the action name, ignore it.
      foreach ($actions as $action => $role) {

        if ($action != 'all') {
          if ($permission == 'allow') {
            $this->allow($role, $resource_name, $action);
          }

          if ($permission == 'deny') {
            $this->deny($role, $resource_name, $action);
          }
        }
      }
    }
  }
}