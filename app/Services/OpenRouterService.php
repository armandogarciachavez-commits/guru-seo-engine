<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Punto único de acceso a la IA vía OpenRouter.
 *
 * Todos los flujos (generación de artículos, radiografía, redacción, acción de
 * Filament, scheduler y el optimizador SEO) usan este servicio. Así, cambiar de
 * modelo o de proveedor se hace en un solo lugar.
 *
 * Config: services.openrouter.api_key / services.openrouter.model
 * (.env: OPENROUTER_API_KEY, OPENROUTER_MODEL).
 */
class OpenRouterService
{
    protected string $endpoint = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Genera texto. Devuelve el contenido (trim) o null si falla.
     *
     * @param  string  $prompt  Prompt del usuario.
     * @param  array{system?:string,temperature?:float,max_tokens?:int,model?:string,timeout?:int}  $opts
     */
    public function generate(string $prompt, array $opts = []): ?string
    {
        $apiKey = config('services.openrouter.api_key');
        $model = $opts['model'] ?? config('services.openrouter.model', 'openrouter/auto');

        if (empty($apiKey)) {
            Log::error('[OpenRouter] OPENROUTER_API_KEY no está configurada.');
            return null;
        }

        $messages = [];
        if (! empty($opts['system'])) {
            $messages[] = ['role' => 'system', 'content' => $opts['system']];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $opts['temperature'] ?? 0.7,
        ];
        if (! empty($opts['max_tokens'])) {
            $payload['max_tokens'] = (int) $opts['max_tokens'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name', 'Guru SEO'),
                'Content-Type' => 'application/json',
            ])
                ->timeout($opts['timeout'] ?? 120)
                ->post($this->endpoint, $payload);

            if ($response->failed()) {
                Log::error('[OpenRouter] HTTP ' . $response->status() . ': ' . $response->body());
                return null;
            }

            $content = data_get($response->json(), 'choices.0.message.content');

            if (empty($content)) {
                Log::error('[OpenRouter] Respuesta vacía: ' . $response->body());
                return null;
            }

            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

            return trim($content);
        } catch (\Throwable $e) {
            Log::error('[OpenRouter] Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Igual que generate() pero devuelve un array decodificado desde un JSON
     * embebido en la respuesta (tolerante a ```json y texto alrededor).
     */
    public function generateJson(string $prompt, array $opts = []): ?array
    {
        $raw = $this->generate($prompt, $opts);
        if ($raw === null) {
            return null;
        }

        $clean = str_replace(['```json', '```'], '', $raw);

        // Intento como objeto {...} o arreglo [...], lo que aparezca.
        foreach ([['{', '}'], ['[', ']']] as [$open, $close]) {
            $start = strpos($clean, $open);
            $end = strrpos($clean, $close);
            if ($start !== false && $end !== false && $end > $start) {
                $slice = substr($clean, $start, $end - $start + 1);
                $decoded = json_decode($slice, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }
}
