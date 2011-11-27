<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{

  protected function _initTimezone(){
    date_default_timezone_set('America/Vancouver');
    return null;
  }

  protected function _initAddActionHelper(){
    Zend_Controller_Action_HelperBroker::addPath(
      "AB/Controller/Action/Helper",
      "AB_Controller_Action_Helper"
    );
  }

  protected function _initConfig(){
    $configuration = new Zend_Config(require APPLICATION_PATH . DIRECTORY_SEPARATOR . 'configs/config.php', true);
    Zend_Registry::set('configuration', $configuration); 
  }

  protected function _initRouting(){
    $configuration = Zend_Registry::get('configuration');
    $frontController = Zend_Controller_Front::getInstance();

    $router = new Zend_Controller_Router_Rewrite();
    $router->addConfig($configuration, 'routes');
    $frontController->setRouter($router);
  }

  protected function _initCaching(){
    $configuration = Zend_Registry::get('configuration');

    if ($configuration->cache->on) {

      $dataCache = Zend_Cache::factory(
        'Core',
        'Memcached',
        array(
          'caching' => true,
          'cache_id_prefix' => $configuration->cache->id,
          'lifetime' => $configuration->cache->lifetime,
          'automatic_serialization' => true
        )
      );

      $outputCache = Zend_Cache::factory(
        'Output',
        'Memcached',
        array(
          'caching' => true,
          'cache_id_prefix' => $configuration->cache->id,
          'lifetime' => $configuration->cache->lifetime,
          'automatic_serialization' => true
        )
      );

      Zend_Registry::set('dataCache',$dataCache);

      // register our table metadata cache
      Zend_Db_Table_Abstract::setDefaultMetadataCache($dataCache);

      Zend_Registry::set('outputCache',$outputCache);
    }
  }    

}

