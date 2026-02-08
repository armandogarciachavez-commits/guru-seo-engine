<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use App\Models\CrawlResult;
use Illuminate\Support\HtmlString;

class CrawlResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'crawlResults';

    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $title = 'Análisis de Sitio (Crawler)';
    protected static ?string $icon = 'heroicon-m-magnifying-glass-circle';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('url')->required(),
            Forms\Components\TextInput::make('title'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status_code')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state == 200 => 'success',
                        $state >= 400 => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->url),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título Detectado')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('h1')
                    ->label('H1 (Tema Principal)')
                    ->limit(40)
                    ->description(fn (CrawlResult $record) => empty($record->h1) ? 'Sin tema definido' : ''),
            ])
            ->headerActions([
                Tables\Actions\Action::make('analyze_master_strategy')
                    ->label('Consultoría Estratégica (Master)')
                    ->icon('heroicon-o-presentation-chart-bar')
                    ->color('success')
                    ->modalHeading('Diagnóstico Estratégico de Mercado y Contenidos')
                    ->modalWidth('7xl') // Ventana MUY ancha para que quepan las tablas
                    ->modalSubmitAction(false)
                    ->modalContent(function ($livewire) {
                        // 1. Validar que existan datos
                        $project = $livewire->getOwnerRecord();
                        $results = $project->crawlResults;

                        if ($results->isEmpty()) {
                            return new HtmlString('<p class="text-danger font-bold">⚠️ Primero debes Rastrear el Sitio.</p>');
                        }

                        // 2. Preparar la "Foto Actual" del sitio
                        // Tomamos hasta 50 URLs para dar un contexto profundo
                        $siteMapData = $results->where('status_code', 200)->take(50)->map(function($r) {
                            $h1 = $r->h1 ?: '(Sin H1)';
                            $title = $r->title ?: '(Sin Título)';
                            return "URL: {$r->url} | H1: {$h1} | TITLE: {$title}";
                        })->implode("\n");

                        $ciudad = $project->target_city ?? 'Ubicación del cliente';
                        $idioma = $project->target_language ?? 'Español';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            // 3. EL PROMPT MAESTRO (Tu versión ajustada)
                            $prompt = "
                                ROL: Actúa como consultor senior en SEO estratégico y análisis de mercado digital.
                                TAREA: Analiza la estructura actual del sitio web y entrega un diagnóstico claro y accionable para dominar el mercado.

                                --- DATOS DE ENTRADA ---
                                SITIO WEB (Estructura Rastreada):
                                {$siteMapData}

                                UBICACIÓN / MERCADO OBJETIVO:
                                {$ciudad} ({$idioma})

                                --- INSTRUCCIONES DE ANÁLISIS ---
                                1. Identifica: Servicios principales, Propuesta de valor y Público objetivo probable basándote en los H1 y Títulos actuales.
                                2. Detecta: Nichos explotables y servicios relacionados que NO están en la lista de arriba.
                                3. Analiza brechas: Compara mentalmente contra los LÍDERES TÍPICOS DE ESTE SECTOR en {$ciudad} y dime qué tienen ellos que este sitio no.
                                4. Encuentra oportunidades: Nuevas páginas, clusters y preguntas frecuentes.

                                --- FORMATO DE SALIDA (ESTRICTO HTML) ---
                                Genera el reporte usando tablas HTML (<table class='w-full border-collapse border border-gray-300'>) para la información densa y listas (<ul>) para ideas.

                                A. SERVICIOS ACTUALES DETECTADOS (Tabla: URL | Servicio Identificado | Calidad del Enfoque)
                                B. NICHOS Y OPORTUNIDADES (Lista detallada de subnichos no explotados)
                                C. BRECHAS FRENTE A COMPETIDORES (Tabla: Qué tiene la competencia | Por qué es importante | Falta en este sitio (Sí/No))
                                D. PÁGINAS NUEVAS RECOMENDADAS (Tabla: Título Propuesto (H1) | Intención de Búsqueda | Objetivo)
                                E. 10 IDEAS DE CONTENIDO PRIORITARIO (Lista numerada con títulos gancho)
                                F. RECOMENDACIONES ESTRATÉGICAS INMEDIATAS (Prioriza acciones < 90 días para tráfico/clientes)

                                NOTA FINAL: Sé brutalmente honesto. Si el sitio está vacío o mal enfocado, dilo. Tu objetivo es hacer que el cliente gane dinero.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.5] // Balance entre creatividad y análisis
                                ]);

                            $aiReport = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Error generando estrategia.';
                            
                            // Limpieza básica de Markdown a HTML si la IA se confunde
                            $aiReport = str_replace(['```html', '```'], '', $aiReport);
                            
                            // Estilos básicos para las tablas dentro del modal (Tailwind)
                            $aiReport = str_replace('<table>', '<table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 border border-gray-200">', $aiReport);
                            $aiReport = str_replace('<th>', '<th class="px-6 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200">', $aiReport);
                            $aiReport = str_replace('<td>', '<td class="px-6 py-4 border-b border-gray-200">', $aiReport);

                            return new HtmlString("<div class='prose dark:prose-invert max-w-none space-y-4'>{$aiReport}</div>");

                        } catch (\Exception $e) {
                            return new HtmlString("<p>Error IA: {$e->getMessage()}</p>");
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}