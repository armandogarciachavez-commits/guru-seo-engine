<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\CompetitorAnalyzer;
use App\Services\GeminiContentService;
use App\Models\Project;

echo "Checking CompetitorAnalyzer...\n";
if (class_exists(CompetitorAnalyzer::class)) {
    echo "CompetitorAnalyzer class exists.\n";
    $analyzer = new CompetitorAnalyzer();
    $data = $analyzer->analyze('test keyword', 'en-US');
    if (is_array($data)) {
        echo "CompetitorAnalyzer returned array data.\n";
    } else {
        echo "CompetitorAnalyzer did NOT return array data.\n";
    }
} else {
    echo "CompetitorAnalyzer class NOT found.\n";
}

echo "\nChecking GeminiContentService...\n";
if (class_exists(GeminiContentService::class)) {
    echo "GeminiContentService class exists.\n";
    // We won't call generateArticle here to avoid making actual API calls or needing a Project instance in DB
} else {
    echo "GeminiContentService class NOT found.\n";
}
