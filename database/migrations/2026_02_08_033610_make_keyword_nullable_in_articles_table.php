<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Hacemos que la columna 'keyword' acepte valores vacíos (NULL)
            // Usamos ->change() para modificar la columna que ya existe
            $table->string('keyword')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Si deshacemos la migración, vuelve a ser obligatoria
            $table->string('keyword')->nullable(false)->change();
        });
    }
};