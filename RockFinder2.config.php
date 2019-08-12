<?php namespace ProcessWire;
class RockFinder2Config extends ModuleConfig {
  public function getDefaults() {
    return array(
      'url' => '',
      'numBakFiles' => 20,
    );
  }
  public function getInputfields() {
    $inputfields = parent::getInputfields();

    $url = uniqid();

    $f = $this->modules->get('InputfieldText');
    $f->attr('name', 'url');
    $f->label = 'API Endpoint URL';
    $f->required = true;
    $f->notes = "Use custom or random string, eg: $url".
      "\nFull url will be ".$this->pages->get(1)->httpUrl.$url."/";
    $inputfields->add($f);
    
    $f = $this->modules->get('InputfieldInteger');
    $f->attr('name', 'numBakFiles');
    $f->label = 'Number of Backup-Files to keep';
    $f->description = "Whenever you save code in the sandbox it will save a copy in /site/assets/RockFinder2/bak";
    $f->notes = "0 = no limit";
    $inputfields->add($f);

    return $inputfields;
  }
}