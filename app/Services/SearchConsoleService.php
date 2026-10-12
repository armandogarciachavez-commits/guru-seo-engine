<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Conexión a Google Search Console vía service account.
 *
 * Requiere el JSON del service account (config services.google.service_account_json)
 * y que ese service account esté agregado como usuario de lectura en cada propiedad
 * de GSC que se quiera consultar.
 */
class SearchConsoleService
{
    public const MAX_ROWS_PER_REQUEST = 25000;

    protected SearchConsole $service;

    public function __construct(?SearchConsole $service = null)
    {
        if ($service) {
            $this->service = $service;

            return;
        }

        $jsonPath = config('services.google.service_account_json');

        if (! $jsonPath || ! is_file($jsonPath)) {
            throw new \RuntimeException(
                "No se encontró el JSON del service account de Google en: {$jsonPath}. "
                .'Configura GOOGLE_SERVICE_ACCOUNT_JSON o coloca el archivo en storage/app/google/service-account.json.'
            );
        }

        $client = new GoogleClient;
        $client->setAuthConfig($jsonPath);
        $client->setScopes([SearchConsole::WEBMASTERS_READONLY]);
        $client->setApplicationName('Guru SEO Monitor');

        $this->service = new SearchConsole($client);
    }

    /**
     * Ventana de análisis: por defecto 28 días con desfase de 3
     * (GSC tiene lag de datos de ~2-3 días).
     *
     * @return array{from:string,to:string}
     */
    public static function defaultWindow(int $days = 28, int $lag = 3): array
    {
        $to = Carbon::today()->subDays($lag);
        $from = (clone $to)->subDays($days - 1);

        return ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    }

    /**
     * Consulta Search Analytics para una propiedad.
     *
     * @param  string  $property  "sc-domain:ejemplo.com" o "https://ejemplo.com/"
     * @param  array<int,string>  $dimensions  ['query'], ['page'] o ['query','page']
     * @return array<int,array{keys:array,clicks:int,impressions:int,ctr:float,position:float}>
     */
    public function fetchSearchAnalytics(
        string $property,
        string $from,
        string $to,
        array $dimensions = ['query', 'page'],
        int $rowLimit = 5000
    ): array {
        $rows = [];
        $startRow = 0;

        // GSC devuelve máx. 25 000 filas por request ordenadas por clics desc;
        // se pagina con startRow hasta $rowLimit para no perder las de pocos clics.
        while ($startRow < $rowLimit) {
            $pageSize = min(self::MAX_ROWS_PER_REQUEST, $rowLimit - $startRow);

            $request = new SearchAnalyticsQueryRequest;
            $request->setStartDate($from);
            $request->setEndDate($to);
            $request->setDimensions($dimensions);
            $request->setRowLimit($pageSize);
            $request->setStartRow($startRow);
            $request->setDataState('final'); // solo datos consolidados

            try {
                $response = $this->service->searchanalytics->query($property, $request);
            } catch (\Throwable $e) {
                Log::error("[GSC] Error consultando {$property}: ".$e->getMessage());
                throw $e;
            }

            $pageRows = $response->getRows() ?? [];
            foreach ($pageRows as $row) {
                $rows[] = [
                    'keys' => $row->getKeys(),
                    'clicks' => (int) $row->getClicks(),
                    'impressions' => (int) $row->getImpressions(),
                    'ctr' => (float) $row->getCtr(),
                    'position' => (float) $row->getPosition(),
                ];
            }

            if (count($pageRows) < $pageSize) {
                break;
            }

            $startRow += $pageSize;
        }

        return $rows;
    }

    /**
     * Lista las propiedades a las que el service account tiene acceso.
     * Útil para verificar la conexión y el onboarding de cada sitio.
     *
     * @return array<int,array{url:string,permission:string}>
     */
    public function listProperties(): array
    {
        $list = $this->service->sites->listSites();

        $out = [];
        foreach ($list->getSiteEntry() ?? [] as $site) {
            $out[] = [
                'url' => $site->getSiteUrl(),
                'permission' => $site->getPermissionLevel(),
            ];
        }

        return $out;
    }
}
