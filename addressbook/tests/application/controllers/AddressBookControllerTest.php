<?php

class AddressBookControllerTest extends Zend_Test_PHPUnit_ControllerTestCase
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

  public function testIndexAction()
  {
    $params = array('action' => 'index', 'controller' => 'address-book', 'module' => 'default');
    $url = $this->url($this->urlizeOptions($params));
    $this->dispatch($url);

    // assertions
    $this->assertModule($params['module']);
    $this->assertController($params['controller']);
    $this->assertAction($params['action']);
    $this->assertQueryContentContains("div#contact_header h2", "Contacts");
  }

  public function testViewAction()
  {
    $contact = $this->_getContact();

    $params = array('action' => 'view', 'controller' => 'address-book', 'module' => 'default','id' => $contact->id);
    $url = $this->url($this->urlizeOptions($params),'viewContacts',true);
    $this->dispatch($url);

    // assertions
    $this->assertModule($params['module']);
    $this->assertController($params['controller']);
    $this->assertAction($params['action']);

    
    $this->_deleteContact($contact);
  }

  public function testCreateAction()
  {
    $email = 'fortestingpurposes@donotreply.com';
    $this->request->setMethod("POST")
      ->setPost(
        array(
          'contact' => array(
            'first_name' => 'Somelongfirstname',
            'last_name' => 'Notshortlastname',
            'email' => $email,
            'user_id' => AB_Auth::getInstance()->data['User']['id']
          )
        )
      );
    $params = array('action' => 'create', 'controller' => 'address-book', 'module' => 'default');
    $url = $this->url($this->urlizeOptions($params),'createContact',true);
    $this->dispatch($url);

    $contact = AB_Model_Gateway::Factory("Contact")
      ->getContact()
      ->byEmail($email)
      ->loadOne();

    // assertions
    $this->assertNotNull($contact);

    if ( $contact ) {
      $params = array('action'=>'view', 'controller' => 'address-book','module' =>'default', 'id' => $contact->id);
      $url = $this->url($this->urlizeOptions($params),'viewContacts',true);
      $this->assertRedirectTo($url);
      
      $this->resetRequest()
        ->resetResponse();

      $this->request->setMethod('GET')
        ->setPost(array());

      $this->dispatch($url);

      $this->assertModule($params['module']);
      $this->assertController($params['controller']);
      $this->assertAction($params['action']);

      $this->assertQueryContentContains('h2',$contact->first_name);
      $this->assertQueryContentContains('h2',$contact->last_name);
    }
  }

  public function testEditAction()
  {
    $newFirstName = "BOB";

    $contact = $this->_getContact();
    $params = array('action' => 'edit', 'controller' => 'address-book', 'module' => 'default', 'id' => $contact->id);
    $url = $this->url($this->urlizeOptions($params),'editContact',true);
    $this->request->setMethod("POST")
      ->setPost(
        array(
          'id' => $contact->id,
          'contact' => array(
            'first_name' => $newFirstName
          )
        )
      );
    $this->dispatch($url);

    $params = array('action'=>'view', 'controller'=>'address-book', 'module'=>'default', 'id'=>$contact->id);
    $url = $this->url($this->urlizeOptions($params),'viewContacts',true);
    $this->assertRedirectTo($url);

    $this->resetRequest()
      ->resetResponse();
    $this->request->setMethod("GET")
      ->setPost(array());
    $this->dispatch($url);

    // assertions
    $this->assertModule($params['module']);
    $this->assertController($params['controller']);
    $this->assertAction($params['action']);
    $this->assertQueryContentContains('h2', $newFirstName);

    $this->_deleteContact($contact);
  }

  public function testDeleteAction()
  {
    $contact = $this->_getContact();
    $params = array('action' => 'delete', 'controller' => 'address-book', 'module' => 'default', 'id' => $contact->id);
    $url = $this->url($this->urlizeOptions($params),'deleteContact',true);
    $this->dispatch($url);
    
    $contact = AB_Model_Gateway::Factory("Contact")
      ->getContact()
      ->byFirstName($contact->first_name)
      ->byLastName($contact->last_name)
      ->byEmail($contact->email)
      ->byUserId($contact->user_id)
      ->loadOne();

    $this->assertEquals(null,$contact);
    // assertions
    $this->assertModule($params['module']);
    $this->assertController($params['controller']);
    $this->assertAction($params['action']);
  }

  protected function _getContact(){
    $contact = AB_Model_Gateway::Factory("Contact")
      ->create(
        array(
          'user_id' => AB_Auth::getInstance()->data['User']['id'],
          'first_name' => 'John',
          'last_name' => 'Smith',
          'email' => 'jsmith@ubc.bc.ca',
        )
      );

    try {
      $contact->save();
    } catch ( Exception $e ) {
      print "Error saving contact: {$e->getMessage()}\n";
    }
    $this->assertGreaterThan(0,intval($contact->id));

    return $contact;
  }

  protected function _deleteContact($contact){

    try {
      AB_Model_Gateway::Factory("Contact")
        ->getContact()
        ->byId($contact->id)
        ->delete(null,true);
    } catch (Exception $e) {
      print "Error deleting contact: {$e->getMessage()}";
    }
    
  }

}



