<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'auto_publish')) {
                // true = comportamiento actual (publica solo al llegar la fecha)
                // false = requiere aprobación manual antes de publicar
                $table->boolean('auto_publish')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('auto_publish');
        });
    }
};
