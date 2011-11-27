<?php
/**
 * Handles user authentication
 */
class UserController extends IndexController {
  
  /**
   * Logs the user in
   *
   *  @param  none
   *  @return void
   */
  public function loginAction()
  {
    // if user is already logged in, redirect to home page.
    if (AB_Auth::getInstance()->active) {
      $this->_redirect('/');
    }

    // if form is submitted, process the form. if disable_enter is set to true,
    // only process the form if it's submitted by ajax.
    if ($this->getRequest()->isPost() ){
      $data = $this->getRequest()->getPost();

      // attempt to log the user in
      $is_logged = AB_Auth::getInstance()->authenticate($data['username'], md5($data['password']));

      // if user is logged in successfully,
      if ($is_logged) {
	// get the page url where a user atempts to log in from.
	// use this url to redirect the user after login is successful.
	$referrer = AB_Auth::getInstance()->getLastUrl(true);
        $this->_redirect($referrer);
      } else {
	// authentication failed; print the reasons why.
	$error = AB_Auth::getInstance()->getErrorMsg();
	$this->view->error_msg = $error;
	$this->view->login_username = $data['username'];
      }
    }
  }

  /**
   *  Log out a user.
   *
   *  @param  none
   *  @return void
   */
  public function logoutAction()
  {
    if (AB_Auth::getInstance()->active) {
      // clear user session
      AB_Auth::getInstance()->clearIdentity();
    }

    // redirect to home page
    $this->_redirect('/');
  }

}