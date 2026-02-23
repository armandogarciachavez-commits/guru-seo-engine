<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Agregamos las dos columnas para Facebook
            if (!Schema::hasColumn('projects', 'facebook_page_id')) {
                $table->string('facebook_page_id')->nullable()->after('address');
            }
            if (!Schema::hasColumn('projects', 'facebook_access_token')) {
                $table->text('facebook_access_token')->nullable()->after('facebook_page_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['facebook_page_id', 'facebook_access_token']);
        });
    }
};