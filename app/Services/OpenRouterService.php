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
                'Authorization' => 'Bearer '.$apiKey,
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name', 'Guru SEO'),
                'Content-Type' => 'application/json',
            ])
                ->connectTimeout(15)
                ->timeout($opts['timeout'] ?? 120)
                // Reintentos ante lentitud/caída transitoria de la API (p.ej. cURL 28
                // timeout): hasta 3 intentos con 2s de espera. Evita que un timeout deje
                // sin optimizar justo las oportunidades de mayor prioridad.
                ->retry(3, 2000, function (\Throwable $e) {
                    $retry = $e instanceof \Illuminate\Http\Client\ConnectionException;
                    if ($retry) {
                        Log::warning('[OpenRouter] Timeout/conexión; reintentando: '.$e->getMessage());
                    }

                    return $retry;
                }, throw: false)
                ->post($this->endpoint, $payload);

            if ($response->failed()) {
                Log::error('[OpenRouter] HTTP '.$response->status().': '.$response->body());

                return null;
            }

            $content = data_get($response->json(), 'choices.0.message.content');

            if (data_get($response->json(), 'choices.0.finish_reason') === 'length') {
                Log::warning('[OpenRouter] Respuesta truncada por límite de tokens (finish_reason=length).');
            }

            if (empty($content)) {
                Log::error('[OpenRouter] Respuesta vacía: '.$response->body());

                return null;
            }

            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

            return trim($content);
        } catch (\Throwable $e) {
            Log::error('[OpenRouter] Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Igual que generate() pero devuelve un array decodificado desde un JSON
     * embebido en la respuesta (tolerante a ```json y texto alrededor).
     *
     * $opts['expect']: 'list' garantiza un arreglo de elementos (desenvuelve
     * {"x":[...]} y un objeto suelto {...} → [{...}]); 'object' prioriza {...}.
     */
    public function generateJson(string $prompt, array $opts = []): ?array
    {
        $raw = $this->generate($prompt, $opts);
        if ($raw === null) {
            return null;
        }

        $decoded = $this->extractJson($raw, $opts['expect'] ?? null);

        if ($decoded !== null && ($opts['expect'] ?? null) === 'list') {
            return $this->asList($decoded);
        }

        return $decoded;
    }

    public function extractJson(string $raw, ?string $expect = null): ?array
    {
        $clean = trim(str_replace(['```json', '```'], '', $raw));

        // Respuesta completa válida: respetar su estructura tal cual.
        $whole = json_decode($clean, true);
        if (is_array($whole)) {
            return $whole;
        }

        $pairs = [['{', '}'], ['[', ']']];
        if ($expect === 'list') {
            $pairs = [['[', ']'], ['{', '}']];
        } elseif ($expect === null) {
            $obj = strpos($clean, '{');
            $arr = strpos($clean, '[');
            if ($arr !== false && ($obj === false || $arr < $obj)) {
                $pairs = [['[', ']'], ['{', '}']];
            }
        }

        foreach ($pairs as [$open, $close]) {
            $start = strpos($clean, $open);
            $end = strrpos($clean, $close);
            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    protected function asList(array $data): array
    {
        if (array_is_list($data)) {
            return $data;
        }

        // Objeto envoltorio: {"articles":[...]} o {"warnings":[],"articles":[...]}.
        $isItemList = fn ($v) => is_array($v) && $v !== [] && array_is_list($v) && is_array($v[0]);

        if (isset($data['articles']) && $isItemList($data['articles'])) {
            return $data['articles'];
        }

        $lists = array_values(array_filter($data, $isItemList));
        if (count($lists) === 1) {
            return $lists[0];
        }

        if (count($data) === 1 && is_array(reset($data)) && array_is_list(reset($data))) {
            return reset($data);
        }

        return [$data];
    }
}
