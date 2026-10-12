<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega a cada proyecto los datos de su propiedad de Google Search Console.
 *
 * gsc_property: identificador de la propiedad en GSC. Dos formatos posibles:
 *   - Propiedad de dominio:  "sc-domain:gurumkt.com.mx"
 *   - Propiedad de prefijo:  "https://gurumkt.com.mx/"
 * gsc_enabled: si el monitoreo SEO automático está activo para este proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'gsc_property')) {
                $table->string('gsc_property')->nullable()->after('domain_url');
            }
            if (! Schema::hasColumn('projects', 'gsc_enabled')) {
                $table->boolean('gsc_enabled')->default(false)->after('gsc_property');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            foreach (['gsc_property', 'gsc_enabled'] as $col) {
                if (Schema::hasColumn('projects', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
