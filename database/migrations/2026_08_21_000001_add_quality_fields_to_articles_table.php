<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (! Schema::hasColumn('articles', 'quality_issues')) {
                $table->json('quality_issues')->nullable();
            }
            if (! Schema::hasColumn('articles', 'published_url')) {
                $table->string('published_url')->nullable();
            }
        });

        // Sincroniza datos históricos entre 'content' y 'html_content'
        DB::table('articles')
            ->whereNull('content')
            ->whereNotNull('html_content')
            ->update(['content' => DB::raw('html_content')]);

        DB::table('articles')
            ->whereNull('html_content')
            ->whereNotNull('content')
            ->update(['html_content' => DB::raw('content')]);
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['quality_issues', 'published_url']);
        });
    }
};
