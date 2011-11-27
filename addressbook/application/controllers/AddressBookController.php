<?php
/**
 * 
 */
class AddressBookController extends IndexController {
  
  public function indexAction(){
    $gateway = AB_Model_Gateway::Factory("Contact");
    $this->view->letters = $gateway->getLetters(self::$_me->id);
    $this->view->contacts = $gateway->getContacts()
      ->byUserId(self::$_me->id)
      ->order("last_name ASC")
      ->order("first_name ASC")
      ->load();
  }

  public function viewAction(){
    $this->_getContact();
  }

  public function createAction(){
    $emsg = "";
    $efields = array();
    $contact = new StdClass();

    // only try to create a contact if the request is a post request
    if ( $this->getRequest()->isPost() ) {
      // get array of contact info
      $data = $this->_getParam('contact');

      // attempt to create and save contact model
      $contact = AB_Model_Gateway::Factory("Contact")->create($data);
      try {
	$contact->save();
      } catch ( Exception $e ) {}

      if ( !count($contact->errors()) ) {
        // no errors
	$this->_flashMessenger->addMessage("1_Contact saved!");
	$this->_redirect(
          $this->_helper->getHelper('url')->url(
            array(
              'action' => 'view',
              'controller' => 'address-book',
              'module' => 'default',
              'id' => $contact->id
            ),
            'viewContacts',
            true
          )
        );
      } else {
        // get errors message, fields with errors, and contact data, and re-render create view
        foreach ( $contact->errors() as $field=>$error ) {
          $emsg .= AB_Inflector::titleize($field) . ": ". array_shift($error). " <br/>";
          if ( !in_array($field,$efields)) {
            $efields[] = $field;            
          }
        }
      }
    }

    $this->view->contact = $contact;
    $this->view->error_msg = $emsg;
    $this->view->error_fields = $efields;
  }

  public function editAction(){
    $this->_getContact();

    $contact = $this->view->contact;
    $emsg = "";
    $efields = array();

    if ( $this->getRequest()->isPost() ) {
      $data = $this->_getParam('contact');

      $contact->setFromArray($data);

      try {
        $contact->save();
      } catch ( Exception $e ) {}

      if ( !count($contact->errors()) ) {
        // no errors
	$this->_flashMessenger->addMessage("1_Contact saved!");
	$this->_redirect(
          $this->_helper->getHelper('url')->url(
            array(
              'action' => 'view',
              'controller' => 'address-book',
              'module' => 'default',
              'id' => $contact->id
            ),
            'viewContacts',
            true
          )
        );
      } else {
        // get errors message, fields with errors, and contact data, and re-render create view
        foreach ( $contact->errors() as $field=>$error ) {
          $emsg .= AB_Inflector::titleize($field) . ": ". array_shift($error). " <br/>";
          if ( !in_array($field,$efields)) {
            $efields[] = $field;            
          }
        }
      }      
    }

    $this->view->error_msg = $emsg;
    $this->view->error_fields = $efields;

  }

  public function deleteAction(){
    $this->_getContact();

    $this->view->contact->delete();

    $this->_flashMessenger->addMessage("1_Contact deleted");
    $this->_redirect(
      $this->_helper->getHelper('url')->url(
        array(
          'action' => 'index',
          'controller' => 'address-book',
          'module' => 'default'
        ),
        'default',
        true
      )
    );
  }

  protected function _getContact(){
    $id = intval($this->_getParam('id'));
    $contact = null;

    if ( $id > 0 ) {
      $contact = AB_Model_Gateway::Factory("Contact")
	->getContact()
	->byId($id)
	->loadOne();
    }

    if ( is_null($contact) ) {
      $this->_flashMessenger->addMessage("0_No such contact in your address book!");
      $this->_redirect(
        $this->_helper->getHelper('url')->url(
          array(
            'action' => 'index',
            'controller' => 'address-book',
            'module' => 'default'
          ),
          'default',
          true
        )
      );
    }

    $this->view->contact = $contact;
  }

}