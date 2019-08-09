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
   * übersicht über alle leistungen
   */
  public function ___executeSandbox() {
    $this->headline('RF2 Sandbox');
    $this->wire('processBrowserTitle', 'RF2 Sandbox');
    $form = modules('InputfieldForm');
    $form->action = './';

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
    $f->description = "The code must return a RockFinder2 instance, Result will be logged to the browser console!";
    if(!$ace) $f->notes .= "\nYou can install 'InputfieldAceExtended' for better code editing";
    
    $f->name = 'code';
    $f->label = 'Code to execute';
    $f->value = "<?php namespace ProcessWire;"
      ."\n".'$rf = new RockFinder2();'
      ."\n".'$rf->name = "demo";'
      ."\n".'$rf->selector = "id>2, limit=10";'
      ."\n".'return $rf;';
    $form->add($f);

    return $form->render();
  }

  /**
   * Get data of sandbox finder
   * @return string
   */
  public function executeGetdata() {
    if(!$this->user->isSuperuser()) throw new WireException("Only SuperUsers have access");
    
    // get code from input and write it to a temp file
    $tmp = $this->files->tempDir($this->className);
    $file = $tmp.uniqid().".php";
    file_put_contents($file, $this->input->post('code'));

    // now render this file and get returned rockfinder
    try {
      $rf = $this->files->render($file);
      if(!$rf instanceof RockFinder2) {
        throw new WireException("Your code must return a RockFinder2 instance!");
      }
      $rf->getGzip();
    } catch (\Throwable $th) {
      echo $th->getMessage();
      exit();
    }
  }

}

