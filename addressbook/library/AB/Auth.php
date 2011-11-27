<?php
/**
 *	Class AB_Auth
 *	Manages user authentication.
 */
class AB_Auth
{
  /**
   * Singleton instance
   *
   * @var AB_Auth
   */
  protected static $_instance = null;

  /**
   * Front controller instance
   *
   * @var AB_Auth
   */
  protected $_controller = null;

  /**
   * Object to proxy $_SESSION storage
   *
   * @var Zend_Session_Namespace
   */
  protected $_auth_session = null;
 
  /**
   * Token of the current session
   *
   * @var string
   */
  protected $_token = null;

  /**
   * Session timeout in seconds (4 hours as default)
   *
   * @var string
   */
  protected $_session_timeout = 14400;

  /**
   * Random string used for session string
   *
   * @var string
   */
  protected $_session_string = 'DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi';

  /**
   * Authentication error
   *
   * @var string
   */
  protected $_error = null;

  /**
   * User session data
   *
   * @var array
   */
  public $data = array();

  /**
   * If user session exists, it's set to true
   *
   * @var bool
   */
  public $active = false;

  /**
   * Singleton pattern implementation makes "new" unavailable.
   *
   * @param  none
   * @return void
   */
  protected function __construct()
  {
    // initialize auth session.
    $this->_auth_session = new Zend_Session_Namespace('AB_Auth'); 
		
    $this->_auth_session->setExpirationSeconds($this->_session_timeout);

    // retrieve token from session if exists.
    $this->_token = $this->_loadToken();

    // get front controller.
    $this->_controller = Zend_Controller_Front::getInstance();

    // reset default values.
    $this->_reset();

    // if session token exists, look for the session record from database.
    if ($this->_token != null) {
      $session = AB_Model_Gateway::Factory('Session')
        ->getSession()
        ->where('sessions.disabled = ?', 0, 'int')
        ->byId($this->_token)
        ->canCache(true, 300)
        ->loadOne();

      if (!is_null($session)) {
        // prepare time and expiry time stamp for sql statement.
        $expire = time() + $this->_session_timeout;

        // check whether token is expired.
        if ($session->modified < $expire) {
          // if token is valid, touch the modified time.
          if ((time() - $session->modified) > 300) {
            $session->modified = time();
            $session->save();
          } 

          // restore additional data.
          $this->data = unserialize($session->data);
          $this->active = true;
        }
      }
    }
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

  /**
   * Create a new auth session with given (username, password) credential.
   *
   * @param  string $username
   * @param  string $password (encrpted)
   * @param  string $role
   * @return bool return false if credential validation is failed.
   */
  public function authenticate($username, $password, $role = "")
  {
    if (!empty($username) && !empty($password)) {
      $user = AB_Model_Gateway::Factory('User')
        ->getUser()
        ->byUsername($username)
        // ->byPassword($password)
        ->loadOne();  

      if (!is_null($user)) {
        if ($user->password !== $password) {
	  $this->_error = "FAILURE_CREDENTIAL_INVALID";
	} else if ($user->access_level == 5) {
          $this->_error = "FAILURE_ACCOUNT_BLOCKED";
        } else if ($user->authorized == 0) {
          $this->_error = "FAILURE_ACCOUNT_INACTIVE";
        } else if (!empty($role) && $user->role != $role) {
          $this->_error = "FAILURE_ROLE_INVALID";
        } else { 
          // remove previous session from the same user.
          //AB_Model_Gateway::Factory('Session')->clearOldSessions($user->id);

          // create session data.
          $this->_token = $this->_generateToken();
          $this->data['User'] = $user->toArray();
          $token_data = serialize($this->data);

          $data = array(
            'id'      => $this->_token,
            'user_id' => $user->id,
            'lifetime'=> $this->_session_timeout,
            'data'    => $token_data
          );

          $new_session = AB_Model_Gateway::Factory('Session')->create($data);

          try {
            $new_session->save();
          } catch (Exception $e) {}

          // store token.
          $this->_auth_session->token = $this->_token;

          $this->active = true;
          return true;
        }
      } else {
      	// no such username exists.
	$this->_error = "FAILURE_ACCOUNT_NOTEXIST"; 
      }
    } else {
      $this->_error = "FIELD_EMPTY";
    }

    return false;
  }

  /**
   * Sets session to be remembered for a long time, resets session id
   *
   * @param none
   * @return void
   */
  public function remember()
  {
    Zend_Session::rememberMe($this->_session_timeout);
  }

  /**
   * Return error message based on error code.
   *
   * @param none
   * @return void
   */
  public function getErrorMsg()
  {
    $error_message = '';

    // Authentication failed; print the reasons why.
    if ($this->_error !== 0) {
      switch ($this->_error) {
        case "FAILURE_ACCOUNT_BLOCKED":
          /** do stuff for nonexistent identity **/
          $error_message = "Your account has been suspended.";
          break;

        case "FAILURE_ACCOUNT_INACTIVE":
          /** do stuff for inactive identity **/
          $error_message = "Your account hasn't been activated yet.";
          break;

        case "FAILURE_CREDENTIAL_INVALID":
          /** do stuff for invalid credential **/
          $error_message = "Incorrect username/password combination.";
          break;
		
        case "FAILURE_ACCOUNT_NOTEXIST":
          /** do stuff for invalid credential **/
          $error_message = "No such account exists in the system.";
          break;
					
        case "FAILURE_ROLE_INVALID":
          /** do stuff for invalid credential **/
          $error_message = "Your don't have a right permission to log in.";
          break;

        case "FIELD_EMPTY":
          /** do stuff for invalid credential **/
          $error_message = "Both username and password are required.";
          break;

        default:
          /** do stuff for other failure **/
          $error_message = "Failed to create authentication.";
          break;
      }
    }

    return $error_message;
  }

  /**
   * Require session to be active or throw user to blackhole immediately.
   *
   * @param  $request_format
   * @return bool
   */
  public function checkActive($request_format = 'html')
  {
    if ($this->active) {
      return true;
    } else {
      return $this->blackHole($request_format);
    }
  }

  /**
   * Black-hole an invalid request with a 403 error or custom callback
   * before displaying the error message, store the current page URL.
   *
   * @param  $request_format
   * @return void
   */
  public function blackHole($request_format = 'html')
  {
    // store current page URL before displaying error page.
    $flash = Zend_Controller_Action_HelperBroker::getExistingHelper('FlashMessenger');
    $flash->addMessage('timeout_0_You need to log in first.');

    if ($request_format == 'json') {
      $errors['logged_out'] = "/login-required";
      $return = array("result"=>0, "errors"=>$errors);

      Zend_Controller_Action_HelperBroker::getStaticHelper('Json')->sendJson($return);
    } elseif ($request_format == 'text') {
      echo 'logged_out';

      // for ajax request, disable layout and rendering view.
      Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->setNoRender();

      $layout = Zend_Layout::getMvcInstance();
      if (null !== $layout) {
        $layout->disableLayout();
      }
    } else {
      $this->_auth_session->referrer = $_SERVER['REQUEST_URI'];

      $this->_controller->getResponse()->setRedirect('/login-required');
      $this->_controller->getResponse()->sendResponse();
    }
  }

  /**
   * get last url before user is redirected to blackhole.
   * @return callback in controller
   * @access public
   */
  public function getLastUrl($reset = false) {
    $target = $this->_auth_session->referrer;

    if ($reset) {
      unset($this->_auth_session->referrer);
    }

    if (!isset($target) || $target == '/logout') {
      $target = '/';
    }

    return $target;
  }

  /**
   * Update user session data.
   *
   * if user info is changed, you need to update user session data to synchronize values.
   *
   * @param none
   * @return void
   */
  public function updateIdentity()
  {
    // only if user session exist, update user session data.
    if ($this->active && $this->_token != null) {
      $session = AB_Model_Gateway::Factory('Session')
        ->getSession()
        ->byId($this->_token)
        ->loadOne();

      if (!is_null($session)) {
        $user = AB_Model_Gateway::Factory('User')
          ->getUser()
          ->byId($session->user_id)
          ->loadOne();

        if (!is_null($user)) {
          $this->data['User'] = $user->toArray();

          $session->data = serialize($this->data);
          $session->modified = time();
          $session->save();
				 	
        }
      }
    } 
  }

  /**
   * Preemptively destory a session.
   *
   * @param  none
   * @return void
   */
  public function clearIdentity()
  {
    // only if user session exist, clear user session data.
    if ($this->active && $this->_token != null) {
      // log out user session
      $session = AB_Model_Gateway::Factory('Session')
        ->getSession()
        ->byId($this->_token)
        ->loadOne();

      if (!is_null($session)) {
        $session->logout();
      }

      // destroy token from storage.
      $this->_destroyToken();

      // reset authentication values
      $this->_reset();
    }
  }

  /**
   * Retrieve session token.
   *
   * @param  none
   * @return string
   */
  public function token()
  {
    return $this->_token;
  }

  /**
   * Generate a new session token that is unique with respect of:
   * - remote address
   * - current time
   *
   * @param  none
   * @return string
   */
  protected function _generateToken()
  {
    return md5($_SERVER['REMOTE_ADDR'] + time() + $this->_session_string);
  }

  /**
   * Load session token from storage.
   *
   * @param  none
   * @return string
   */
  protected function _loadToken()
  {
    return $this->_auth_session->token;
  }

  /**
   * Delete session token from storage.
   *
   * @param  none
   * @return void
   */
  protected function _destroyToken()
  {
    Zend_Session::forgetMe();
    $this->_token = null;
    unset($this->_auth_session->token);
    unset($this->_auth_session->referrer);
  }

  /**
   * Reset authentication values.
   *
   * @param  none
   * @return string
   */
  protected function _reset()
  {
    $this->_error = null;
    $this->data['User']['id'] = 0;
    $this->active = false;
  }
}