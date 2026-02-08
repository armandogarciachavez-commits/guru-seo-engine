<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "Projects Table Columns:\n";
print_r(Schema::getColumnListing('projects'));

echo "\nArticles Table Columns:\n";
print_r(Schema::getColumnListing('articles'));
