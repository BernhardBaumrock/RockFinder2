<?php namespace ProcessWire;
$js = "<script>var finder = new _RockFinder2('$json');</script>";

if($input->requestMethod() == 'POST') {
  header('Content-Type: application/json');
  echo json_encode([
    'html' => $dump.$js
      ."<div class='tracy-inner'>"
      ."<small>A JavaScript instance of RockFinder2 with data of this finder"
      ." is available in the console as 'finder' .</small>"
      ."</div>",
    'finder' => $finder,
  ]);
  die();
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>RockFinder2 Debug Screen</title>
  <script type='text/javascript' src='/wire/modules/Jquery/JqueryCore/JqueryCore.js'></script>
  <script type='text/javascript' src='/site/modules/RockFinder2/RockFinder2.js'></script>
  <?= $tag ?>
  <style>
    .tracy-inner {display: none;}
  </style>
</head>
<body>
  <?= $dump ?>
  <?= $js ?>
  <script>
  setTimeout(function() {
    $('.tracy-inner').fadeIn();
  }, 500);
  </script>
</body>
</html>