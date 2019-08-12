<?php namespace ProcessWire;
/**
 * RockFinder2
 *
 * @author Bernhard Baumrock, 08.08.2019
 * @license Licensed under MIT
 */
class ProcessRockFinder2 extends Process {
  public static function getModuleInfo() {
    return [
      'title' => 'RockFinder2 Process Module',
      'summary' => 'RockFinder2 Backend',
      'version' => '0.0.1',
      'author' => 'Bernhard Baumrock',
      'icon' => 'search',
      'requires' => ['RockFinder2'],
      'page' => [
        'name' => 'rockfinder2',
        'title' => 'RockFinder2',
        'parent' => 'setup',
      ],
    ];
  }

  /**
   * @var RockFinder2
   */
  public $rf;

  /**
   * Init the module
   */
  public function init() {
    parent::init();
    $this->rf = $this->wire->RockFinder2;
  }

  /**
   * Main screen
   */
  public function execute() {
    $out = '';

    // list all available finders
    $out .= "<h2>Available Finders</h2>";
    $out .= '<table class="uk-table uk-table-divider uk-table-striped">';
    $out .= '<thead><tr><th>Name</th><th>Description</th></tr></thead>';
    $out .= '<tbody>';
    $path = $this->config->paths->assets . "RockFinder2";
    foreach($this->files->find($path, ['extensions' => ['php']]) as $file) {
      $info = (object)pathinfo($file);
      $desc = str_replace('// ', '', file($file)[1]);
      $out .= "<tr><td><a href='./sandbox/?name={$info->filename}'>{$info->filename}</a></td><td>$desc</td></tr>";
    }
    $out .= '</tbody>';
    $out .= '</table>';
    $out .= "<p><a href='./sandbox'>Open Sandbox</a></p>";

    return $out;
  }

  /**
   * übersicht über alle leistungen
   */
  public function ___executeSandbox() {
    $this->handleSaveAction();
    $name = $this->input->get('name', 'string');

    $headline = 'RF2 Sandbox';
    if($name) $headline .= " - $name";
    $this->headline($headline);
    $this->wire('processBrowserTitle', $headline);
    
    $form = modules('InputfieldForm');
    $form->action = './';
    $form->id = 'sandboxform';
    $out = '';

    $out .= '<p>';
    $out .= '<a href="'.$this->page->url.'">< Back to overview page</a>';
    if($name) {
      $apiUrl = $this->rf->url . '?name=' . $name;
      $out .= " | API Endpoint URL: <a href='$apiUrl'>$apiUrl</a></p>";
    }

    // code field
    $f = $this->modules->get('InputfieldTextarea');
    if($ace = $this->modules->get('InputfieldAceExtended')) {
      $ace->rows = 10;
      $ace->theme = 'monokai';
      $ace->mode = 'php';
      $ace->setAdvancedOptions(array(
        'highlightActiveLine' => false,
        'showLineNumbers'     => false,
        'showGutter'          => false,
        'tabSize'             => 2,
        'printMarginColumn'   => false,
      ));
      $f = $ace;
    }
    
    $f->notes = "Execute on CTRL+ENTER or ALT+ENTER, save on CTRL+S\n"
      ."Backups are stored in " . $this->rf->bak;
    $f->description = "The code must return a RockFinder2 instance!";
    if(!$ace) $f->notes .= "\nYou can install 'InputfieldAceExtended' for better code editing";

    // get code
    $code = file_get_contents(__DIR__ . '/includes/demo.php');
    if($name) {
      $file = $this->rf->getFiles($name);
      if(!$file) throw new WireException("Finder for $name not found");
      $code = file_get_contents($file);
    }
    
    $f->name = 'code';
    $f->label = 'Code to execute';
    $f->icon = 'code';
    $f->wrapAttr('data-name', $name);
    $f->value = $code;
    $form->add($f);

    // add debug field
    $form->add([
      'type' => 'markup',
      'name' => 'debug',
      'label' => 'Debug Info',
      'icon' => 'bug',
      'collapsed' => Inputfield::collapsedYes,
      'value' => '<div id="debuginfo" style="display: none;"></div>',
    ]);
    
    // add tabulator field
    if($this->modules->isInstalled('RockTabulator')) {
      $form->add([
        'type' => 'RockTabulator',
        'name' => 'rockfinder2_sandbox',
        'label' => 'RockTabulator',
        'icon' => 'table',
        'notes' => 'An instance of this tabulator is available in the console as \'grid\'',
        'initMsg' => '',
      ]);
    }
    else {
      $form->add([
        'type' => 'markup',
        'name' => 'tabulator',
        'label' => 'RockTabulator',
        'icon' => 'table',
        'value' => 'Install RockTabulator to see data here',
      ]);
    }

    $out .= $form->render();
    return $out;
  }

  /**
   * Handle sandbox save actions
   */
  public function handleSaveAction() {
    if(!$this->input->post('action', 'string') == 'save') return;
    $name = $this->input->get('name', 'string');
    if(!$name) $name = '_sandbox_';

    try {
      // check access
      if(!$this->user->isSuperuser()) throw new WireException("No access!");

      // get file
      $file = $this->rf->getFiles($name);
      $code = $this->input->post('code', 'string');
      if(!is_writable($file)) throw new WireException("File not writable!");

      // write old code to tmp history
      $path = $this->rf->bak;
      $old = file_get_contents($file);
      file_put_contents($path.$name.'-'.date('Ymd-His').".php", $old);

      // do cleanup
      $this->rf->getBackupFiles($name);

      // write code to file
      file_put_contents($file, $code);

      die("Changes saved!");
    } catch (\Throwable $th) {
      die($th->getMessage());
    }
  }
}

