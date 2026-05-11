<?php
require __DIR__."/../vendor/autoload.php";
$app = require __DIR__."/../bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
header("Content-Type: text/plain");
foreach (\Illuminate\Support\Facades\Route::getRoutes() as $r) {
  echo $r->uri()."\\n";
}
