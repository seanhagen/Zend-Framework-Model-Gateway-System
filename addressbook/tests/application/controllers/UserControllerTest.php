<?php

class UserControllerTest extends Zend_Test_PHPUnit_ControllerTestCase
{

  public function setUp()
  {
    $this->bootstrap = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');

    $this->bootstrap->bootstrap('db');
    $this->bootstrap->bootstrap('config');

    global $_SERVER;
    $_SERVER['HTTP_HOST'] = exec("hostname");
    $_SERVER['HTTP_USER_AGENT'] = 'cli';
    $_SERVER['REMOTE_ADDR']= "127.0.0.1";

    $auth = AB_Auth::getInstance()->authenticate('bobdole',md5('testing'));
    $this->assertTrue($auth);

    parent::setUp();
  }

  public function testLogin(){
    $this->request->setMethod("POST")
      ->setPost(
        array(
          'username'=>'bobdole',
          'password'=>'testing'
        )
      );

    $params = array('action'=>'login', 'controller'=>'user','module'=>'default');
    $url = $this->url($this->urlizeOptions($params),'login_required',true);
    $this->dispatch($url);
    $this->assertRedirectTo('/');
    $this->assertTrue(AB_Auth::getInstance()->active);
  }

  public function testLogout(){
    $params = array('action'=>'logout', 'controller'=>'user','module'=>'default');
    $url = $this->url($this->urlizeOptions($params),'logout_user',true);
    $this->dispatch($url);

    $this->assertFalse(AB_Auth::getInstance()->active);
  }

}