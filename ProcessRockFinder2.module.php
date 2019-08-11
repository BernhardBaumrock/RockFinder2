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
    $this->headline('RF2 Sandbox');
    $this->wire('processBrowserTitle', 'RF2 Sandbox');
    $form = modules('InputfieldForm');
    $form->action = './';
    $form->id = 'sandboxform';
    $out = '';

    $out .= '<p><a href="'.$this->page->url.'">< Back to overview</a></p>';

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
    
    $f->notes = "Execute on CTRL+ENTER or ALT+ENTER";
    $f->description = "The code must return a RockFinder2 instance!";
    if(!$ace) $f->notes .= "\nYou can install 'InputfieldAceExtended' for better code editing";

    // get code
    $code = file_get_contents(__DIR__ . '/includes/demo.php');
    $name = $this->input->get('name', 'string');
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
      'value' => '<div id="debuginfo" style="display: none;"></div>',
    ]);

    $out .= $form->render();
    return $out;
  }
}

