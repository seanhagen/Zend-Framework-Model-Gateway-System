<?php
// global configuration
return array(
  'cache' => array(
    'on' => true,
    'lifetime' => 82800, // 23 hours
    'id' => 'ab_' 
  ),
 'acl' => array(
    'roles' => array(
      'Guest'	=> null,      // Guest inherits from no one
      'Member' => 'Guest',  // Member inherits form Guest
    ),
    'models' => array(
      'allow' => array(),
      'deny' => array()
    ),
    'locations' => array( // need to define allow permission first since permission can be overwritten in deny.
      'allow' => array(
        'default' => array(
          'error' => array(
            'any' => 'Guest'
          ),
          'user' => array(
            'any' => 'Guest'
          ),
          'address-book' => array(
            'any' => 'Member'
          )
        )
      ),
      'deny' => array() // as default, resources are denied if not specified in allow.
      // only use deny array to overwrite action-level permission.
    )
  ),
  'routes' => array(
    'default' => array(
      'route' => '/',
      'defaults' => array(
        'module' => 'default',
        'controller' => 'address-book',
        'action' => 'index'
      )
    ),
    'createContact' => array(
      'route' => 'contact/create',
      'defaults' => array(
        'module' => 'default',
        'controller' => 'address-book',
        'action' => 'create'
      )
    ),
    'viewContacts' => array(
      'route' => 'contact/view/:id',
      'defaults' => array(
        'module'=> 'default',
        'controller' => 'address-book',
        'action'=> 'view'
      )
    ),
    'editContact' => array(
      'route' => 'contact/edit/:id',
      'defaults' => array(
        'moudle' => 'default',
        'controller' => 'address-book',
        'action' => 'edit'
      )
    ),
    'deleteContact' => array(
      'route' => 'contact/delete/:id',
      'defaults' => array(
        'module' => 'default',
        'controller' => 'address-book',
        'action' => 'delete'
      )
    ),
    'login_required' => array( // required by error controller.
      'route' => 'login-required',
      'defaults' =>  array(
        'module' => 'default',
        'controller' => 'user',
        'action' => 'login'
      )
    ),
    'logout_user' => array(
      'route' => 'logout',
      'defaults' => array(
        'module' => 'default',
        'controller' => 'user',
        'action' => 'logout'
      )
    )
  )
);
