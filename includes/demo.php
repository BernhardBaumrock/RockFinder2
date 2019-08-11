<?php namespace ProcessWire;
// demo finder
$rf = new RockFinder2();
$rf->name = "demo";
$rf->find("id>2, limit=10");
$rf->addColumns([
  'title' => 'my_title_alias', // default pw field + alias
  'modified', // demo of column in pages table
  'xxx:yyy', // demo of non existing column type (fallback)
]);
return $rf;