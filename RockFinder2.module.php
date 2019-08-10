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
   * @var array|string pw-selector to find base pages
   */
  public $selector;

  /**
   * The query to get all ids of selected pages
   * @var DatabaseQuerySelect
   */
  private $idQuery;

  /**
   * The query that selects all columns (final data) for this finder
   * @var DatabaseQuerySelect
   */
  private $query;

  /**
   * Array of column names in 'pages' DB table
   */
  public $baseColumns;

  /**
   * Reference to RockFinder2 api variable
   */
  public $rf;

  /* ########## init ########## */

  /**
   * Initialize the module (optional)
   */
  public function init() {
    // set api variable on first run
    if(!$this->wire->RockFinder2) {
      $this->name = 'rf2';
      $this->wire->set('RockFinder2', $this);
      $this->url = "/".trim($this->url, "/")."/";
      
      // get base table columns
      // this is only attached to the base instance for better performance
      $db = $this->config->dbName;
      $result = $this->database->query("SELECT `COLUMN_NAME`
        FROM `INFORMATION_SCHEMA`.`COLUMNS`
        WHERE `TABLE_SCHEMA`='$db'
        AND `TABLE_NAME`='pages';");
      $this->baseColumns = $result->fetchAll(\PDO::FETCH_COLUMN);

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
    }

    // Add reference to RockFinder2 api var to this instance
    $this->rf = $this->wire->RockFinder2;

    // attach column types via hook
    $this->addHookAfter("RockFinder2::getCol", $this, 'addColumnTypes');
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

  /* ########## general ########## */

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
      else throw new WireException("$name returns invalid result");
    }
    else {
      $this->executeSandbox();
    }

    // todo: log errors (status codes)
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

  /* ########## sql query constructor ########## */

  /**
   * Set selector of this finder
   * @param string|array $selector
   * @return void
   */
  public function selector($selector) {
    $this->selector = $selector;
    
    // get ids of base selector
    $selector = $this->wire(new Selectors($selector));
    $pf = $this->wire(new PageFinder());
    $query = $pf->find($selector, ['returnQuery' => true]);
    $query->set('select', ['pages.id']);

    // save this query object for later
    $this->query = $query;

    // idQuery is the minimal query that is used for joins and relations
    $this->idQuery = clone $query;
  }

  /**
   * Add columns to finder
   * @param array $columns
   */
  public function addColumns($columns) {
    if(!$this->query) throw new WireException("Setup the selector before calling addColumns()");
    if(!is_array($columns)) throw new WireException("Parameter must be an array");

    // add columns one by one
    foreach($columns as $k=>$v) {
      // if key is integer we take the value instead
      if(is_int($k)) {
        $k = $v;
        $v = null;
      }

      // setup initial column name
      $column = $k;

      // if a type is set, get type
      // syntax is type:column, eg addColumns(['mytype:myColumn'])
      $type = null;
      if(strpos($column, ":")) {
        $arr = explode(":", $column);
        $type = $arr[0];
        $column = $arr[1];
      }

      // column name alias
      $alias = $v;

      // add this column
      $this->addColumn($column, $type, $alias);
    }
  }

  /**
   * Add column to finder
   * @param mixed $column
   * @param mixed $type
   * @param mixed $alias
   * @return void
   */
  private function addColumn($column, $type = null, $alias = null) {
    if(!$type) $type = $this->getType($column);
    if(!$alias) $alias = $column;
    $query = $this->query;

    // get column type definition
    $col = $this->getCol($type);
    if(!$col OR !is_callable($col)) {
      $col = $this->getCol(); // get default coldef
    }

    // invoke callable
    $col->__invoke((object)[
      'query' => $query,
      'column' => $column,
      'alias' => $alias,
      'type' => $type,
    ]);
  }

  /**
   * Get column type from column name
   * 
   * @param string $column
   * @return string
   */
  public function ___getType($column) {
    // is this column part of the pages table?
    $columns = $this->wire->RockFinder2->baseColumns;
    if(in_array($column, $columns)) return 'BaseColumn';

    // is it a pw field?
    $field = $this->fields->get($column);
    if($field) {
      switch($field->type) {
        // by default we treat every field as a standard text field
        default:
          return 'FieldText';
      }
    }
  }

  /**
   * Hookable getCol method for column definitions
   * @return Closure
   */
  public function ___getCol($type = null) {}

  /**
   * Get column types via hook
   */
  public function addColumnTypes($event) {
    $type = $event->arguments('type');
    switch($type) {

      // column of the pages table
      case 'BaseColumn':
        $event->return = function($data) {
          $data->query->select("{$data->column} AS {$data->alias}");
        };
        return;

      // default pw field
      case 'FieldText':
        $event->return = function($data) {
          $table = $this->wire->database->escapeTable("field_{$data->column}");
          $data->query->leftjoin("$table ON $table.pages_id=pages.id");
          $data->query->select("field_{$data->column}.data as {$data->alias}");
        };
        return;

      // default fallback
      default:
        $event->return = function($data) {
          $data->query->select("'No column type found for type {$data->type}' AS {$data->alias}");
        };
        return;
    }
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
      'data' => $this->getMainData(),
      'relations' => $this->getRelations(),
      'options' => $this->getOptions(),
      'context' => $this->getContext(),
    ];
    return $this->dataObject;
  }

  /**
   * Get main data from PW selector
   */
  public function getMainData() {
    if(!$this->query) return [];

    $result = $this->query->execute();
    // d($this->query);
    // db($result->queryString, 'all');
    return $result->fetchAll(\PDO::FETCH_OBJ);
    
    // $result = $this->idQuery->execute();
    // d($this->idQuery);
    // db($result->queryString, 'ids');
    // d(implode("|", $result->fetchAll(\PDO::FETCH_COLUMN)));

    // return $data;
  }

  /**
   * Get relations
   * @return array
   */
  public function getRelations() {
    return [];
  }

  /**
   * Get options
   * @return array
   */
  public function getOptions() {
    return [];
  }

  /**
   * Get context
   * @return array
   */
  public function getContext() {
    return [];
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
    $info['getData()'] = $this->getData();
    return $info; 
  }
}
