<?php namespace ProcessWire;
class RockFinder2Config extends ModuleConfig {
  public function getDefaults() {
    return array(
      'url' => '',
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

    return $inputfields;
  }
}