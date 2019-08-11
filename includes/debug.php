<?php namespace ProcessWire;
$js = "<script>var finder = new _RockFinder2('$json');</script>";

if($input->requestMethod() == 'POST') {
  echo $dump;
  echo $js;
  echo "<div class='tracy-inner'>"
    ."<small>A JavaScript instance of RockFinder2 with data of this finder"
    ." is available in the console as 'finder' .</small>"
    ."</div>";
  return;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>RockFinder2 Debug Screen</title>
  <script type='text/javascript' src='/site/modules/RockFinder2/RockFinder2.js'></script>
  <?= $tag ?>
</head>
<body>
  <?= $dump ?>
  <?= $js ?>
</body>
</html>