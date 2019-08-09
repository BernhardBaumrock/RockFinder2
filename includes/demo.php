<?php namespace ProcessWire;
// demo finder
$rf = new RockFinder2();
$rf->name = "demo";
$rf->selector = "id>2, limit=10";
return $rf;