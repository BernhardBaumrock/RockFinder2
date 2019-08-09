<?php namespace ProcessWire;
/**
 * RockFinder2
 *
 * @author Bernhard Baumrock, 08.08.2019
 * @license Licensed under MIT
 */
class RockFinder2 extends WireData implements Module {
  public static function getModuleInfo() {
    return [
      'title' => 'RockFinder2',
      'version' => '0.0.1',
      'summary' => 'RockFinder2',
      'icon' => 'search',
      'installs' => ['ProcessRockFinder2'],
      'autoload' => true,
      'singular' => true,
    ];
  }

  /**
   * RockFinder data object
   */
  private $dataObject;

  /**
   * Callable to check for access
   * 
   * By default this will only return true for superusers.
   * @var callback
   */
  public $hasAccess;

  /**
   * @var string name
   */
  public $name;

  /**
   * @var string pw-selector to find base pages
   */
  public $selector;

  /**
   * Initialize the module (optional)
   */
  public function init() {
    // set api variable
    if(!$this->wire->RockFinder2) {
      $this->name = 'rf2';
      $this->wire->set('RockFinder2', $this);
      return;
    }
  }

  /**
   * Class constructor
   */
  public function __construct() {
    $this->name = uniqid();

    // default hasAccess callback
    $this->hasAccess = function() {
      return $this->user->isSuperuser();
    };
  }

  /* ########## get data ########## */

  /**
   * Return data object
   * @return object
   */
  public function getData() {
    // check access
    if(!is_callable($this->hasAccess)) throw new WireException("hasAccess must be callable");
    if(!$this->hasAccess->__invoke()) throw new WireException("No access!");

    // return data
    if($this->dataObject) return $this->dataObject;
    $this->dataObject = (object)[
      'name' => $this->name,
      'data' => [],
      'relations' => [],
      'options' => [],
      'context' => [],
    ];
    return $this->dataObject;
  }

  /**
   * Get JSON data
   * @return string
   */
  public function getJSON() {
    return json_encode($this->getData());
  }

  /**
   * Return gzipped data as applicaton/json
   */
  public function getGzip() {
    header('Content-Type: application/json');
    ob_start("ob_gzhandler");
    echo $this->getJSON();
    ob_end_flush();
    exit();
  }

  
  public function foo() {
    return 'bar';
  }

  /**
   * debugInfo PHP 5.6+ magic method
   * @return array
   */
  public function __debugInfo() {
    $info = $this->settings ?: [];
    $info['name'] = $this->name;
    $info['selector'] = $this->selector;
    $info['getData()'] = $this->getData();
    return $info; 
  }
}
