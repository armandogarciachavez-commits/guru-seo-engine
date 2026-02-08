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
        // INTENCIONALMENTE VACÍO.
        // Las columnas ya existen en tu base de datos.
        // Lo dejamos así para que Laravel no intente crearlas de nuevo y no de error.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // También vacío por seguridad.
    }
};