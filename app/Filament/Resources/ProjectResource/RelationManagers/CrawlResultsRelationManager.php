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
    protected static ?string $title = 'Auditoría Técnica (Resultados)';
    protected static ?string $icon = 'heroicon-m-clipboard-document-check';

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
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state == 200 => 'success',
                        $state >= 500 => 'danger',
                        $state >= 400 => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->url),

                Tables\Columns\TextColumn::make('title')
                    ->label('Meta Title')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('h1')
                    ->label('H1 Principal')
                    ->limit(30)
                    ->description(fn (CrawlResult $record) => empty($record->h1) ? '⚠️ Falta H1' : ''),
                
                Tables\Columns\TextColumn::make('word_count')
                    ->label('Palabras')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('analyze_audit')
                    ->label('Analizar Identidad y SEO')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('primary')
                    ->modalHeading('Diagnóstico Inteligente de Sitio')
                    ->modalSubmitAction(false)
                    ->modalContent(function ($livewire) {
                        // 1. Datos del Proyecto
                        $project = $livewire->getOwnerRecord();
                        $results = $project->crawlResults;

                        if ($results->isEmpty()) {
                            return new HtmlString('<p class="text-danger font-bold">⚠️ Primero debes ir a la pestaña anterior y pulsar "Rastrear Sitio".</p>');
                        }

                        // 2. Extraer "ADN" de la marca desde lo rastreado
                        // Tomamos 10 ejemplos de títulos y descripciones para que la IA entienda el giro del negocio
                        $brandContext = $results->whereNotNull('title')->take(10)->map(function($r) {
                            return "- Título: {$r->title} | H1: {$r->h1} | Meta: {$r->meta_description}";
                        })->implode("\n");

                        // 3. Estadísticas Técnicas
                        $total = $results->count();
                        $errors404 = $results->where('status_code', 404)->count();
                        $missingH1 = $results->where('h1', null)->count();
                        $missingMeta = $results->where('meta_description', null)->count();
                        $thinContent = $results->where('word_count', '<', 300)->count();
                        
                        $brokenLinksList = $results->where('status_code', 404)->take(3)->pluck('url')->implode(', ');

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            // 4. PROMPT DE APROPIACIÓN DE MARCA
                            $prompt = "
                                TAREA: Actúa como un Analista de Estrategia Digital y SEO Senior.
                                
                                1. ANÁLISIS DE IDENTIDAD:
                                Lee los siguientes fragmentos extraídos del sitio web de '{$project->name}' ({$project->domain_url}) y determina de qué trata el negocio, su tono de voz y su propuesta de valor.
                                
                                MUESTRA DE CONTENIDO RASTREADO:
                                {$brandContext}

                                2. AUDITORÍA TÉCNICA:
                                Analiza los siguientes datos duros encontrados en el rastreo:
                                - Total URLs: {$total}
                                - Errores 404 (Enlaces rotos): {$errors404} (Ejemplos: {$brokenLinksList})
                                - Páginas sin H1: {$missingH1}
                                - Páginas sin Meta Descripción: {$missingMeta}
                                - Páginas con poco texto: {$thinContent}

                                3. GENERACIÓN DEL REPORTE:
                                Escribe un reporte dirigido directamente a los dueños de '{$project->name}'.
                                - Usa el nombre del negocio ({$project->name}) naturalmente en el texto.
                                - Adapta tu tono al giro del negocio que detectaste en el paso 1 (Ej: Si es médico, sé formal; si es restaurante, sé apetecible pero profesional).
                                - NO uses rellenos genéricos. Ve al grano sobre cómo los errores técnicos (404, falta de H1) están afectando SU negocio específico.
                                - Estructura HTML: <h3>Diagnóstico de Identidad</h3>, <h3>Estado de Salud SEO</h3>, <ul>Problemas Críticos</ul>, <h3>Recomendaciones Estratégicas</h3>.
                                
                                IMPORTANTE: Haz que parezca que conoces el negocio de toda la vida gracias a lo que leíste.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.5]
                                ]);

                            $aiReport = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Error generando reporte.';
                            
                            $aiReport = str_replace(['```html', '```'], '', $aiReport);

                            return new HtmlString("<div class='prose dark:prose-invert max-w-none'>{$aiReport}</div>");

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