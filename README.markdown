Model Gateway Sample Application
====================

"Work smarter, not harder."

This sample Zend Framework application shows how to create and use a Model-Gateway system, in lieu of interfacing directly with the Zend DB Table classes. The purpose of these classes are simple: make the code dealing with the database as simple as possible.

The sample application is a simple address book. It allows users to create contact entries, list entries, view entries, and delete entries.

Using the Model-Gateway system makes finding, displaying, updating, and deleting records in a database fairly easy.

A very basic gateway for interfacing with the 'contacts' table would look like so:

ContactGateway.php:

	class ContactGateway extends BaseGateway {
	      protected $_name = "contacts";
	}

And that's it! Along with the Gateway, you'd also need a Model like so:

Contact.php

	class Contact extends AB_Model_Abstract {
	}

Now, assuming the database table has the following fields:

     id: int auto increment
     user_id: int
     title: varchar
     first_name: varchar
     last_name: varchar
     email: varchar
     phone: varchar
     city: varchar
     state: varchar
     postal_code: varchar
     country: varchar
     created_at: datetime
     updated_at: datetime
     status: int

You'd be able to do any of the following to load data from the database:

      // load all contacts that have 'Vancouver' as their city
      $contact = AB_Model_Gateway::Factory("Contact")
      ->getContacts()
      ->byCity("Vancouver")
      ->load();

      // load all contacts with the last name 'Smith'
      $contact = AB_Model_Gateway::Factory("Contact")
      ->getContacts()
      ->byLastName("Smith")
      ->load();

What about creating a Contact? That's pretty straight-forward too:

     // usually you'd just get this data from a post request
     $data = array(
     	   'user_id' => 1,
	   'title' => 'Mr',
	   'first_name' => 'John',
	   'last_name' => 'Smith',
	   'email' => 'john.smith@example.com',
	   'phone' => '604-555-1234',
	   'city' => 'Vancouver',
	   'state' => 'British Columbia',
	   'postal_code' => '1A1 B2B',
	   'country' => 'Canada'
     );

     $contact = AB_Model_Gateway::Factory("Contact")->create($data);

     try {
     	 $contact->save();
     } catch ( Exception $e ){}

You have to wrap the save() call inside a try-catch, due to the function not doing any error catching on it's own. That's something that I'm looking at improving, but for now that's how it's got to be.

Once you've attempted to save the model to the database, calling errors() will return an array that is either empty, or contain an array with all the errors pertaining to what happened when you tried to save the model. Take a look at the AddressBookController::createAction() to see how to handle an error when saving.

If you take a look at the Contact model, you're notice that in the init() function there are calls to addValidator(). The method signature is pretty simple:

   addValidator( field_name, validator, error_message )

If you've added validators, they will be run before the model is saved ( updated or inserted ). You can also add validators to just an insert or update operation using the following:

   addInsertValidator( field_name, validator, error_message )
   addUpdateValidator( field_name, validator, error_message )

The error_messages are what will be returned in the array you get when you call errors() after saving a model fails.
