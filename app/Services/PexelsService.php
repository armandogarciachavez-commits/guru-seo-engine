<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PexelsService
{
    public static function search($query)
    {
        $apiKey = env('PEXELS_API_KEY');

        // Si no hay API Key, devolvemos NULL (el sistema usará una por defecto después)
        if (!$apiKey) {
            Log::warning('Falta PEXELS_API_KEY en el archivo .env');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey
            ])->get('https://api.pexels.com/v1/search', [
                'query'       => $query,
                'per_page'    => 1,
                'orientation' => 'landscape', // Horizontal para FB
                'locale'      => 'es-ES',     // Buscar en español
                'size'        => 'medium'     // Tamaño optimizado
            ]);

            if ($response->successful()) {
                $data = $response->json();
                // Devolver la primera foto encontrada
                return $data['photos'][0]['src']['landscape'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error("Error Pexels: " . $e->getMessage());
        }

        return null;
    }
}