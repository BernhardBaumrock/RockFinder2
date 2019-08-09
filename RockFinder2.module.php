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
   * @var string name of this finder
   */
  public $name;

  /**
   * @var string pw-selector to find base pages
   */
  public $selector;

  /**
   * @var WireData config data for JavaScript
   */
  private $jsConfig;

  /**
   * Initialize the module (optional)
   */
  public function init() {
    // set api variable
    if(!$this->wire->RockFinder2) {
      $this->name = 'rf2';
      $this->wire->set('RockFinder2', $this);
      $this->url = "/".trim($this->url, "/")."/";

      // handle API Endpoint requests
      $this->addHookBefore('ProcessPageView::pageNotFound', $this, 'apiEndpoint');

      // load JS
      $this->config->scripts->add($this->config->urls($this). 'RockFinder2.js');
      $this->addHookAfter("Page(template=admin)::render", function(HookEvent $event) {
        if($this->config->ajax) return;

        $html = $event->return;
        $code = $this->files->render(__DIR__.'/includes/script.php', [
          'conf' => [
            'url' => $this->url,
          ],
        ]);
        $event->return = str_replace('</head>', $code.'</head>', $html);
      });

      return;
    }
  }
  
  /**
   * API ready
   */
  public function ready() {
    $this->checkUrl();
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

  /**
   * Handle API Endpoint requests
   * @param HookEvent $event
   * @return void
   */
  public function apiEndpoint($event) {
    // is this the API Endpoint url?
    $url = $event->arguments('url');

    // if url does not match do a regular 404
    if($url != $this->url) return;

    // get finder that should be executed
    $input = $this->input;
    $name = $input->post('name', 'string') ?: $input->get('name', 'string');

    // execute finder
    if($name) {
      $finder = $this->getByName($name);
      if($finder) $finder->execute();
    }
    else {
      $this->executeSandbox();
    }

    // execute finder
    // log errors (status codes)
  }

  /**
   * Execute given finder
   * @param null|string|RockFinder2 $finder
   * @return void
   */
  public function execute($finder = null) {
    // check access
    if(!is_callable($this->hasAccess)) throw new WireException("hasAccess must be callable");
    if(!$this->hasAccess->__invoke()) throw new WireException("No access!");
    
    // if no finder is set we execute this one
    if(!$finder) $this->getGzip();

    // if finder is a RockFinder2 we execute this one
    if($finder instanceof RockFinder2) $finder->getGzip();

    // if it is a string get it and execute it
    $file = $this->getFiles($finder);
    $this->executeFile($file);
  }

  /**
   * Execute sandbox finder
   */
  public function executeSandbox() {
    if(!$this->user->isSuperuser()) {
      throw new WireException("Only SuperUsers have access to the sandbox");
    }
    if($this->input->requestMethod() != 'POST') {
      throw new WireException("The sandbox supports only POST data");
    }
    
    // get code from input and write it to a temp file
    $tmp = $this->files->tempDir($this->className);
    $file = $tmp.uniqid().".php";
    file_put_contents($file, $this->input->post('code'));
    $this->executeFile($file);
  }

  /**
   * Execute finder file
   * @param string $file
   * @return void
   */
  public function executeFile($file) {
    try {
      $rf = $this->files->render($file);
      if(!$rf instanceof RockFinder2) {
        throw new WireException("Your code must return a RockFinder2 instance!");
      }
      $rf->execute();
    } catch (\Throwable $th) {
      echo $th->getMessage();
      exit();
    }
  }

  /**
   * Get finder by name
   * @param string $name
   * @return RockFinder2
   */
  public function getByName($name) {
    if(!$name) throw new WireException("Please specify a name");
    $file = $this->getFiles($name);
    if(!$file) throw new WireException("File for $name not found");

    $path = $this->config->paths->assets.$this->className;
    $rf = $this->files->render($file, [], ['allowedPaths' => [$path]]);
    if($rf instanceof RockFinder2) return $rf;

    return false;
  }

  /**
   * Get all finder files
   * 
   * If name is specified return only this single file (case sensitive).
   * 
   * @param string $name
   * @return string|array
   */
  public function getFiles($name = null) {
    $path = $this->config->paths->assets . 'RockFinder2';
    $files = $this->files->find($path, ['extensions' => ['php']]);
    if($name) {
      foreach($files as $file) {
        if(pathinfo($file)['filename'] == $name) return $file;
      }
      throw new WireException("File for $name not found");
    }
    return $files;
  }

  /**
   * Check API Endpoint Url
   */
  public function checkUrl() {
    // don't check on modules page
    if($this->page->id == 21) return;

    if(!$this->url) throw new WireException("Url of RockFinder2 must not be empty");
    if($this->url == '//') throw new WireException("Url of RockFinder2 must not be empty");
  }

  /* ########## get data ########## */

  /**
   * Return data object
   * @return object
   */
  private function getData() {
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
  private function getJSON() {
    return json_encode($this->getData());
  }

  /**
   * Return gzipped data as applicaton/json
   */
  private function getGzip() {
    header('Content-Type: application/json');
    ob_start("ob_gzhandler");
    echo $this->getJSON();
    ob_end_flush();
    exit();
  }

  /**
   * debugInfo PHP 5.6+ magic method
   * @return array
   */
  public function __debugInfo() {
    $info = $this->settings ?: [];
    $info['name'] = $this->name;
    $info['selector'] = $this->selector;
    $info['execute()'] = $this->execute();
    return $info; 
  }
}
