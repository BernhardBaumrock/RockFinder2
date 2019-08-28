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
   * RockFinder2 Setup Page
   */
  public function execute() {
    $this->headline('Rockfinder2 Setup Page');
    $this->browserTitle('Rockfinder2 Setup Page');
    /** @var InputfieldForm $form */
    $form = $this->modules->get('InputfieldForm');

    // load vex
    $this->wire('modules')->get('JqueryUI')->use('vex');

    // actions
    if($this->input->post('newFinder', 'string')) $this->createNewFinder();
    if($this->input->get('del', 'string')) $this->deleteFinder();
    
    // available finders
      $out = '';
      $out .= '<table class="uk-table uk-table-divider uk-table-striped">';
      $out .= '<thead><tr><th>Name</th><th class="uk-width-expand">Description</th><th class="uk-width-auto">Delete</th></tr></thead>';
      $out .= '<tbody>';
      $path = $this->getPath();
      $files = $this->files->find($path, [
        'extensions' => ['php'],
        'excludeDirNames' => ['bak'],
      ]);
      foreach($files as $file) {
        $info = (object)pathinfo($file);
        $desc = '';
        $line2 = file($file)[1];
        if(strpos($line2, '// ') === 0) $desc = str_replace('// ', '', $line2);
        $del = "<td class='uk-text-center'><a href='./?del={$info->filename}' data-name='{$info->filename}' class='delFinder'><i class='fa fa-trash'></i></a></td>";
        $out .= "<tr><td class='uk-text-nowrap'><a href='./sandbox/?name={$info->filename}'>{$info->filename}</a></td><td>$desc</td>$del</tr>";
      }
      $out .= '</tbody>';
      $out .= '</table>';

      $b = $this->modules->get('InputfieldButton');
      $b->href = './sandbox';
      $b->value = 'Open new sandbox';
      $b->icon = 'file-o';
      $desc = $b->render();

      $form->add([
        'type' => 'markup',
        'label' => 'Available Finders',
        'description' => $desc,
        'entityEncodeText' => false,
        'value' => $out,
      ]);

    // actions
      $out = '';
      
      $f = $this->modules->get('InputfieldText');
      $f->name = 'newFinder';
      $f->attr('placeholder', 'Finder name');
      $f->attr('style', 'width: 250px;');

      $d = $this->modules->get('InputfieldText');
      $d->name = 'newFinderDesc';
      $d->attr('placeholder', 'Finder description');
      $d->attr('style', 'width: 350px;');

      $b = $this->modules->get('InputfieldSubmit');
      $b->name = 'submit_newFinder';
      $b->icon = 'plus';
      $b->value = 'Create new Finder';

      $form->add([
        'type' => 'markup',
        'label' => 'Actions',
        'value' => $f->render() . $d->render() . $b->render(),
      ]);
    
    return $form->render();
  }

  /**
   * Create new finder
   */
  public function createNewFinder($name = null) {
    if(!$name) $name = $this->input->post('newFinder', 'string');
    if(!$name) throw new WireException("No valid name set for finder");
    
    // check if finder already exists
    if($this->rf->findByName($name)) return $this->error("Finder $name already exists");

    // is directory writeable?
    $path = $this->getPath();
    if(!is_writable($path)) return $this->error("Directory $path is not writeable");

    // copy demo finder to path
    $content = file(__DIR__ . '/includes/demo.php');
    $desc = $this->input->post('newFinderDesc', 'string');
    if($desc) $content[1] = "// $desc\n";
    file_put_contents($path . $name . '.php', $content);

    // redirect to this finder
    $this->session->redirect("./sandbox/?name=$name");
  }

  /**
   * Delete a finder
   */
  public function deleteFinder($name = null) {
    if(!$name) $name = $this->input->get('del', 'string');
    if(!$name) throw new WireException("No valid name set");
    
    $file = $this->rf->getFile($name);
    if($file) $this->files->unlink($file, $this->getPath());

    $this->session->redirect('./');
  }

  /**
   * Get path of assets files
   * @return string
   */
  public function getPath() {
    return $this->config->paths->assets . "RockFinder2/";
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
      $apiUrl = $this->rf->url . '?name=' . $name . '&type=debug';
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
    if(!$ace) $f->notes .= "\nYou can install 'InputfieldAceExtended' for better code editing";
    $f->description = "The code must return a RockFinder2 instance!";

    // get code
    $code = file_get_contents(__DIR__ . '/includes/demo.php');
    if($name) {
      $file = $this->rf->getFiles($name);
      if(!$file) throw new WireException("Finder for $name not found");
      $code = file_get_contents($file);
      
      // add link to file
      $f->entityEncodeText = false;
      $link = $this->getIdeLink($file);
      $f->description .= " <a href='$link'>Open file in IDE</a>";
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
      'notes' => "An instance of this finder is available in the console as 'finder'",
    ]);
    
    // add tabulator field
    if($this->modules->isInstalled('RockTabulator')) {
      $form->add([
        'type' => 'RockTabulator',
        'name' => 'rockfinder2_sandbox',
        'label' => 'RockTabulator',
        'icon' => 'table',
        'notes' => "An instance of this tabulator is available in the console as 'grid'",
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

    try {
      // check access
      if(!$this->user->isSuperuser()) throw new WireException("No access!");
      if(!$name) throw new WireException("Saving disabled in sandbox");

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

  /**
   * Get ide link from file and line
   * @param string $file
   * @param int $line
   * @return string
   */
  public function getIdeLink($file, $line = 0) {
    if($this->modules->isInstalled('TracyDebugger')) {
      $tracy = $this->modules->get('TracyDebugger');
      $link = str_replace('%file', $file, $tracy->editor);
      $link = str_replace('%line', $line, $link);
      return $link;
    }
    return false;
  }
}

