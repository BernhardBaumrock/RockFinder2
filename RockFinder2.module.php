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
      'requires' => ['TracyDebugger'],
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
   * The query that selects all columns (final data) for this finder
   * @var DatabaseQuerySelect
   */
  public $query;

  /**
   * Columns that are added to this finder
   * @var WireArray
   */
  public $columns;

  /**
   * Array of column names in 'pages' DB table
   */
  public $baseColumns;

  /**
   * Main data
   * @var array
   */
  private $mainData;

  /**
   * Reference to RockFinder2 api variable
   */
  public $rf;

  /**
   * Show debug info?
   * @var bool
   */
  public $debug;

  /**
   * Array of options
   * @var array
   */
  public $options = [];
  
  /**
   * Array of relations
   * @var array
   */
  private $relations = [];
  private $_relations = [];

  /**
   * Class constructor
   */
  public function __construct() {
    $this->name = uniqid();
    
    // init columns array
    $this->columns = $this->wire(new WireArray);

    // default hasAccess callback
    $this->hasAccess = function() {
      return $this->user->isSuperuser();
    };
  }

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
        $tag = $this->getScriptTag();
        $event->return = str_replace('</head>', $tag.'</head>', $html);
      });

      // create backup path
      $path = $this->config->paths->assets . 'RockFinder2/bak/';
      $this->files->mkdir($path, true);
      $this->bak = $path;
    }

    // Add reference to RockFinder2 api var to this instance
    $this->rf = $this->wire->RockFinder2;

    // attach column types via hook
    $this->addHookAfter("RockFinder2::getCol", $this, 'addColumnTypes');

    // attach renderers
    $this->addHookAfter("RockFinder2::render(debug)", $this, 'debugRenderer');
    $this->addHookAfter("RockFinder2::render(gzip)", $this, 'gzipRenderer');

    // add dir to RockTabulator
    $this->addHookAfter('RockTabulator::getDirs', function($event) {
      $rt = $event->object;
      $dirs = $event->return;
      $dirs[] = $rt->toUrl(__DIR__ . '/tabulators');
      $event->return = $dirs;
    });
  }

  /**
   * API ready
   */
  public function ready() {
    $this->checkUrl();
  }

  /* ########## general ########## */

  /**
   * Return RockFinder2 JavaScript init tag
   * @return string
   */
  public function getScriptTag() {
    return $this->files->render(__DIR__.'/includes/script.php', [
      'conf' => [
        'url' => $this->url,
      ],
    ]);
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
    // if no finder is set we execute this one
    if(!$finder) $this->output();

    // if finder is a RockFinder2 we execute this one
    if($finder instanceof RockFinder2) $finder->output();

    // if it is a string get it and execute it
    $file = $this->getFiles($finder);
    $this->executeFile($file);
  }

  /**
   * Send output to browser
   * 
   * This method can be hooked so you can attach custom renderers.
   * 
   * @return void
   */
  public function output() {
    $type = $this->input->get('type', 'string');
    if(!$type) $type = $this->input->post('type', 'string');
    if(!$type) $type = 'gzip';

    // check access
    $this->checkAccess($type);

    // log request
    if($this->debug) {
      fl('See PW logs for more RockFinder2 debug info!');
      $this->log("Finder {$this->name} SQL: " . $this->getSQL());
    }

    // render output
    echo $this->render($type);
    die();
  }

  /**
   * Render content of this finder
   * 
   * By default this returns gzip data as application/json. This can be 
   * modified via hooks, so you can attach all kinds of custom renderers.
   */
  public function ___render($type) {}

  /**
   * Execute sandbox finder
   */
  public function executeSandbox() {
    if(!$this->user->isSuperuser()) {
      throw new WireException("Only SuperUsers have access to the sandbox");
    }
    if($this->input->requestMethod() != 'POST') {
      throw new WireException("The sandbox is only available via POST");
    }
    
    // get code from input and write it to a temp file
    $tmp = $this->files->tempDir($this->className);
    $file = $tmp.uniqid().".php";
    file_put_contents($file, $this->input->post('code'));
    $this->executeFile($file, true);
  }

  /**
   * Execute finder file
   * @param string $file
   * @param bool $debug are we in the debug mode?
   * @return void
   */
  public function executeFile($file, $debug = false) {
    try {
      /** @var RockFinder2 $rf */
      $rf = $this->files->render($file);
      if(!$rf instanceof RockFinder2) {
        // if superuser: try to eval to get proper error message
        if($this->user->isSuperuser()) {
          $php = file_get_contents($file);
          if(strpos($php, '<?php') === 0) $php = substr($php, 5);
          eval($php);
        }

        throw new WireException("Your code must return a RockFinder2 instance!");
      }
      $rf->debug = $debug;
      $rf->execute();
    } catch (\Throwable $th) {
      $this->die($th->getMessage());
    }
  }

  /**
   * Return JSON error
   * @param string $msg
   * @return json
   */
  public function err($msg) {
    $this->log($msg);
    return (object)[
      'error' => $msg,
    ];
  }

  /**
   * Return error json response
   */
  public function die($msg) {
    header("Content-type: application/json");
    die(json_encode($this->err($msg)));
  }

  /**
   * Get all backup files for given finder
   * @return array
   */
  public function getBackupFiles($name) {
    $files = $this->files->find($this->bak, ['extensions'=>['php']]);
    rsort($files);

    $num = 0;
    $max = $this->numBakFiles;
    $arr = [];
    foreach($files as $file) {
      $info = (object)pathinfo($file);
      if(strpos($info->basename, $name) !== 0) continue;

      // delete old files if a maximum number is set
      $num++;
      if($max AND $num > $max) {
        $this->files->unlink($file);
        continue;
      }

      // add file to array
      $arr[] = $file;
    }

    return $arr;
  }

  /**
   * Check access to this finder
   * @param string $type
   * @return bool
   */
  public function checkAccess($type = null) {
    // check access
    if(!is_callable($this->hasAccess)) throw new WireException("hasAccess must be callable");
    if(!$this->hasAccess->__invoke($type)) throw new WireException("No access!");
    return true;
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
   * Same as getByName but does not throw exceptions if files do not exist
   * @return mixed
   */
  public function findByName($name) {
    try {
      $finder = $this->getByName($name);
      return $finder;
    } catch (\Throwable $th) {
      return false;
    }
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
    return $files ?: [];
  }

  /**
   * Get php file that corresponds to finder
   * @return string
   */
  public function getFile($finder) {
    $file = false;
    try {
      $file = $this->getFiles($finder);
    } catch (\Throwable $th) {
      // don't throw errors
    }
    return $file;
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
   * @param array $options
   * @return void
   */
  public function find($selector, $options = []) {
    $this->selector = $selector;
    $defaults = [
      // ignore sort order of initial page find operation
      // this can significantly increase performance 
      'nosort' => false,
    ];
    $options = array_merge($defaults, $options);
    
    // get ids of base selector
    $selector = $this->wire(new Selectors($selector));
    $pf = $this->wire(new PageFinder());
    $query = $pf->find($selector, ['returnQuery' => true]);

    // modify the base query to our needs
    // we only need the page id
    $query->set('select', ['`pages`.`id`']);
    // if possible ignore sort order for better performance
    if($options['nosort']) $query->set('orderby', []);

    // save this query object for later
    $this->query = $query;
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
      // skip null value columns
      if($v === null) continue;

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
   * Add columns of given template
   * @param string|Template $template
   * @param array $hide
   * @return void
   */
  public function addTemplateColumns($template, $hide = []) {
    $tpl = $this->templates->get((string)$template);
    foreach($tpl->fields as $field) {
      $fieldname = (string)$field;
      if(in_array($fieldname, $hide)) continue;
      $this->addColumn($fieldname);
    }
  }

  /**
   * Add options from field
   * @param array|string $fields
   * @return void
   */
  public function addOptions($fields) {
    if(is_array($fields)) {
      foreach($fields as $field) $this->addOptions($field);
      return;
    }
    
    $fieldname = (string)$fields;
    $field = $this->fields->get($fieldname);
    if(!$field) throw new WireException("Field $fieldname not found");
    
    $data = [];
    foreach($field->type->getOptions($field) as $opt) {
      $data[$opt->id] = $opt->title;
    }
    $this->options[$fieldname] = $data;
  }

  /**
   * Add relation to this finder
   * 
   * A relation is an array of objects that is stored with the finder. This
   * makes it possible to reference multiple related values from one row (1:n).
   * 
   * @param string name
   * @param mixed $data
   * @param string $rows
   * @return RockFinder2
   */
  public function addRelation($name, $data, $rows = null) {
    // check if name already exists
    if(array_key_exists($name, $this->_relations)) {
      throw new WireException("A relation with name $name already exists");
    }

    // init relation variable
    $relation = $data;

    // check data
    if(is_array($data)) {
      // data is provided as simple array
      // we convert it to a RockFinder2 instance
      $relation = $this->wire(new RockFinder2);
      $relation->setData($data);
    }

    // modify RockFinder2 instance
    if(!$relation instanceof RockFinder2) {
      // $relation must be instance of RockFinder2
      throw new WireException("Invalid data type for relation $name");
    }

    // save relation info
    $relation->rows = $rows;

    // save relation to array
    $this->_relations[$name] = $relation;
  }

  /**
   * Load data of all relations
   * @param array $maindata
   * @param bool $debug
   * @return void
   */
  public function loadRelationsData($maindata, $debug) {
    foreach($this->_relations as $name=>$relation) {
      // quickfix to prevent multiple loading of relations
      // todo: why is this method executed twice?
      if($relation->loaded) return;

      /** @var RockFinder2 $relation */
      $relation->debug = $this->debug;

      // get relation info
      $rows = $relation->rows;

      // get id restrictions
      if(strpos($rows, 'self:') === 0) {
        // the keyword SELF means that we limit the relation to a resultset
        // where only rows are returned that have an ID of the main finder in
        // the column that is specified as self:yourcolumnname
        $field = str_replace('self:', '', $rows);

        // get ids from main dataset
        $ids = $this->getColData($maindata, 'id');
        if(!count($ids)) {
          // if no matching ids where found we return an empty resultset
          // for this relation
          $relation->query->where("_field_$field.data IS NULL");
        }
        else {
          $ids = implode(",", $ids);
          $relation->query->where("_field_$field.data IN ($ids)");
        }
      }
      elseif($rows) {
        // if ids restriction is a column of the main finder get ids from its data
        $colname = $rows;
        $ids = $this->getColData($maindata, $colname);
        sort($ids); // performance pro or con?

        // add ids to query
        $ids = implode(",", $ids);
        $relation->query->where("pages.id IN ($ids)");
      }

      // log this request
      if($relation->debug) {
        $this->log("Relation {$relation->name} SQL: " . $relation->getSQL());
      }

      // load data
      $data = $relation->getData($debug);
      $relation->loaded = true;

      $this->relations[$name] = $data;
    }
  }

  /**
   * Get column data of array
   * @param array $data
   * @param array $column
   * @return array
   */
  public function getColData($data, $column) {
    if(!is_array($data)) throw new WireException("Data must be an array");
    
    $arr = [];
    foreach($data as $item) {
      $item = (array)$item;
      $items = explode(",", $item[$column]);
      foreach($items as $i) $arr[] = $i;
    }
    $arr = array_unique($arr);
    $arr = array_filter($arr);
    return $arr;
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

    // add this column to columns array
    $colname = (string)$column;
    if($this->columns->has($colname)) {
      throw new WireException("Column $column already exists in this finder");
    }
    $this->columns->add((object)[
      'name' => $colname,
      'alias' => $alias,
    ]);

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
   * Add a RockFinder to join
   * @param string|RockFinder $finder
   * @param string $column column to join on
   * @param array $columns columns to join
   * @return void;
   */
  public function addJoin($finder, $column, $columns = null) {
    // check finder
    if(is_string($finder)) $finder = $this->getByName($finder);
    if(!$finder instanceof RockFinder2) throw new WireException("First parameter must be a RockFinder2");

    // setup columns
    if(!$columns) $columns = $finder->columns;

    // get column from array
    $col = $this->columns->get($column);
    if(!$col) throw new WireException("Column $column not found");

    if(!$this->fields->get($col->name)) {
      throw new WireException("Field \"{$col->name}\" does not exist so the join can not be performed");
    }

    // join data
    $sql = str_replace("\n", "\n  ", $finder->getSQL());
    $this->query->leftjoin("($sql) AS `join_{$col->name}` ON `join_{$col->name}`.id = _field_{$col->name}.data");
    foreach($columns as $c) {
      $this->query->select("GROUP_CONCAT(DISTINCT `join_$column`.`{$c->alias}`) as `{$col->alias}:{$c->alias}`");
      $this->columns->add($c);
    }
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
      // file and image fields
      if($field->type instanceof FieldtypeFile) return 'FieldMulti';
      if($field->type instanceof FieldtypePage) return 'FieldMulti';
      if($field->type instanceof FieldtypeOptions) return 'FieldMulti';

      // by default we take it as text field
      return 'FieldText';
    }
    else return 'FieldNotFound';
  }

  /**
   * Hookable getCol method for column definitions
   * @return Closure
   */
  public function ___getCol($type = null) {}

  /**
   * Get column types via hook
   * 
   * Caution: Make sure tu use getTableAlias() on all your queries! See notes
   * of the method for explanation.
   * 
   * @param HookEvent $event;
   * @return void;
   */
  public function addColumnTypes($event) {
    $type = $event->arguments('type');
    switch($type) {

      // column of the pages table
      case 'BaseColumn':
        $event->return = function($data) {
          $data->query->select("`{$data->column}` AS `{$data->alias}`");
        };
        return;

      // default pw field
      case 'FieldText':
        $event->return = function($data) {
          $table = $this->getTable($data->column);
          $tablealias = $this->getTableAlias($data->column);
          $data->query->leftjoin("`$table` AS `$tablealias` ON $tablealias.pages_id=pages.id");
          $data->query->select("`$tablealias`.data AS `$data->alias`");
        };
        return;

      // pw file/image field
      case 'FieldMulti':
        $event->return = function($data) {
          $table = $this->getTable($data->column);
          $tablealias = $this->getTableAlias($data->column);
          $data->query->leftjoin("`$table` AS `$tablealias` ON $tablealias.pages_id=pages.id");
          $data->query->select("GROUP_CONCAT(DISTINCT `$tablealias`.data ORDER BY `$tablealias`.sort SEPARATOR ',') AS `$data->alias`");
        };
        return;
        
      // default pw field
      case 'FieldNotFound':
        $event->return = function($data) {
          $data->query->select("'Field not found' AS `$data->alias`");
        };
        return;

      // default fallback
      default:
        $event->return = function($data) {
          $data->query->select("'No column type found for type {$data->type}' AS `{$data->alias}`");
        };
        return;
    }
  }

  /**
   * Get table name for this column
   * @param string $column
   * @return string
   */
  public function getTable($column) {
    return "field_$column";
  }
  
  /**
   * Get table alias name for this column
   * 
   * We prepend the original table name with an underscore so that we do not end
   * up with a "non unique table alias" error. This can happen when the selector
   * contains a field that is also listed in the RockFinder columns, eg:
   * $rf->find('template=foo, field_bar=123');
   * $rf->addColumns(['title', 'bar']);
   * Field "bar" is joined (JOIN) by the initial PW query and also joined later
   * via RockFinder2 (LEFT JOIN).
   * 
   * @param string $column
   * @return string
   */
  public function getTableAlias($column) {
    return "_".$this->getTable($column);
  }

  /* ########## get data ########## */

  /**
   * Return data object
   * @return object
   */
  public function getData($debug = null) {
    if($debug === null) $debug = $this->debug;
    
    // timings
      // $timings = [];
      // $start = $previous = microtime(true);

      $mainData = $this->getMainData();
      // $now = microtime(true);
      // $timings['data'] = $now - $previous;
      // $previous = $now;

      // $now = microtime(true);
      $this->loadRelationsData($mainData, $debug);
      // $timings['relations'] = $now - $previous;
      // $previous = $now;

      // $timings['total'] = $now - $start;

      // convert to ms and round to 2 digits
      // foreach($timings as $k=>$v) $timings[$k] = round($v*1000, 2);

    // return data
    if($this->dataObject) return $this->dataObject;
    
    $data = (object)[];
    $data->name = $this->name;
    if($debug) $data->columns = $this->columns;
    $data->data = $mainData;
    $data->options = $this->options;
    $data->context = $this->getContext();
    $data->relations = $this->relations;
    if($debug) $data->_relations = $this->_relations;
    if($debug) $data->sql = $this->prettify($this->getSQL());
    if($debug) $data->timings = 'todo'; //$timings;

    $this->dataObject = $data;
    return $data;
  }

  /**
   * Prettify SQL string
   * @return string
   */
  private function prettify($sql) {
    $str = str_replace("SELECT ", "SELECT\n  ", $sql);
    $str = str_replace("`,", "`,\n  ", $str);
    return $str;
  }

  /**
   * Set main data array
   * @param array $data
   * @return void
   */
  public function setData($data) {
    if(!is_array($data)) throw new WireException("Data must be an array");
    $this->mainData = $data;
  }

  /**
   * Get main data from PW selector
   * 
   * If a column index is provided it will return a plain array of values stored
   * in that column.
   * 
   * @param int $columnindex
   * @return array
   */
  public function getMainData($columnindex = null) {
    // if data is already set return it
    if($this->mainData) return $this->mainData;

    // if no query is set return an empty array
    if(!$this->query) return [];

    $result = $this->query->execute();
    if($columnindex === null) return $result->fetchAll(\PDO::FETCH_OBJ);
    else return $result->fetchAll(\PDO::FETCH_COLUMN, $columnindex);
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
  public function getJSON() {
    return json_encode($this->getData());
  }

  /**
   * Return current sql query string
   * @return string
   */
  public function getSQL($pretty = false) {
    if(!$this->query) return;
    $sql = $this->query->getQuery();
    return $pretty ? $this->prettify($sql) : $sql;
  }

  /* ########## renderers ########## */
  
  /**
   * Debug renderer
   * 
   * This renderer is used for debugging.
   * 
   * @param HookEvent $event
   * @return void
   */
  public function debugRenderer($event) {
    if(!$this->user->isSuperuser()) {
      throw new WireException("Debug feature may only be used by Superusers!");
    }
    
    $finder = $event->object;
    $finder->debug = true;
    $data = $finder->getData();

    ob_start();
    \TD::dump($finder, null, ['maxDepth' => 15, 'maxLength' => 9999]);
    $dump = ob_get_clean();
    $html = $this->files->render(__DIR__ . '/includes/debug.php', [
      'dump' => "<div class='tracy-inner'>$dump</div>",
      'json' => $this->database->escapeStr(json_encode($data)),
      'finder' => $data,
      'tag' => $this->getScriptTag(),
    ]);
    $event->return = $html;
  }

  /**
   * Gzip renderer
   * 
   * This is the default renderer for returning gzipped json data.
   * 
   * @param HookEvent $event
   * @return void
   */
  public function gzipRenderer($event) {
    $finder = $event->object;
    header('Content-Type: application/json');
    ob_start("ob_gzhandler");
    echo $finder->getJSON();
    $event->return = ob_end_flush();
  }

  /* ########## debug info ########## */

  /**
   * debugInfo PHP 5.6+ magic method
   * @return array
   */
  public function __debugInfo() {
    $info = $this->settings ?: [];
    $info['name'] = $this->name;
    $info['find'] = $this->selector;
    $info['getData()'] = $this->getData();
    return $info; 
  }
}
