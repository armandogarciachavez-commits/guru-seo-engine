<?php

namespace App\Console\Commands;

use App\Services\SearchConsoleService;
use Illuminate\Console\Command;

/**
 * Verifica la conexión a Google Search Console.
 *
 *   php artisan seo:gsc-test
 *       → lista las propiedades a las que el service account tiene acceso.
 *
 *   php artisan seo:gsc-test "sc-domain:gurumkt.com.mx"
 *       → además trae una muestra de datos (top 10 queries de los últimos 28 días).
 */
class TestSearchConsole extends Command
{
    protected $signature = 'seo:gsc-test {property? : Propiedad GSC a muestrear (sc-domain:... o https://...)}';

    protected $description = 'Prueba la conexión a Google Search Console y muestra datos de ejemplo';

    public function handle(): int
    {
        try {
            $gsc = new SearchConsoleService();
        } catch (\Throwable $e) {
            $this->error('No se pudo iniciar la conexión: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('🔌 Conectado. Propiedades visibles para el service account:');
        try {
            $props = $gsc->listProperties();
        } catch (\Throwable $e) {
            $this->error('Error listando propiedades: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($props)) {
            $this->warn('El service account no tiene acceso a ninguna propiedad todavía.');
            $this->line('→ Agrega su email como usuario en Search Console (Configuración → Usuarios y permisos).');
            return self::SUCCESS;
        }

        $this->table(['Propiedad', 'Permiso'], array_map(
            fn ($p) => [$p['url'], $p['permission']],
            $props
        ));

        $property = $this->argument('property');
        if (! $property) {
            $this->line('');
            $this->line('Para ver datos de ejemplo: php artisan seo:gsc-test "' . ($props[0]['url'] ?? 'sc-domain:tu-dominio.com') . '"');
            return self::SUCCESS;
        }

        $window = SearchConsoleService::defaultWindow();
        $this->info("📊 Muestra de {$property} — ventana {$window['from']} a {$window['to']}:");

        try {
            $rows = $gsc->fetchSearchAnalytics($property, $window['from'], $window['to'], ['query'], 10);
        } catch (\Throwable $e) {
            $this->error('Error consultando datos: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($rows)) {
            $this->warn('Sin datos para esa ventana (¿propiedad correcta? ¿tiene tráfico?).');
            return self::SUCCESS;
        }

        $this->table(
            ['Keyword', 'Clics', 'Impr.', 'CTR', 'Posición'],
            array_map(fn ($r) => [
                $r['keys'][0] ?? '',
                $r['clicks'],
                $r['impressions'],
                round($r['ctr'] * 100, 2) . '%',
                round($r['position'], 1),
            ], $rows)
        );

        return self::SUCCESS;
    }
}
