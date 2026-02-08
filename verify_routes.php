<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;

echo "Registered Routes:\n";
$routes = Route::getRoutes();
foreach ($routes as $route) {
    if (str_contains($route->uri(), 'feed')) {
        echo $route->methods()[0] . " " . $route->uri() . "\n";
        echo "Action: " . $route->getActionName() . "\n";
    }
}
