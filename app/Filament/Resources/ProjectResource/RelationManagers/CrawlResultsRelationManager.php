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
                        $state == 200 => 'success', // Verde (OK)
                        $state >= 500 => 'danger',  // Rojo (Error Servidor)
                        $state >= 400 => 'warning', // Naranja (No encontrado)
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
                // --- AQUÍ ESTÁ EL CEREBRO DE GEMINI ---
                Tables\Actions\Action::make('analyze_audit')
                    ->label('Analizar con IA')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('primary')
                    ->modalHeading('Reporte de Auditoría SEO')
                    ->modalSubmitAction(false) // Botón de "Guardar" oculto (es solo lectura)
                    ->modalContent(function ($livewire) {
                        // 1. Recopilar datos
                        $project = $livewire->getOwnerRecord();
                        $results = $project->crawlResults;

                        if ($results->isEmpty()) {
                            return new HtmlString('<p class="text-danger font-bold">⚠️ Primero debes ir a la pestaña anterior y pulsar "Rastrear Sitio".</p>');
                        }

                        // 2. Preparar resumen para ahorrar tokens
                        $total = $results->count();
                        $errors404 = $results->where('status_code', 404)->count();
                        $missingH1 = $results->where('h1', null)->count();
                        $thinContent = $results->where('word_count', '<', 300)->count();
                        
                        // Tomamos 5 URLs con error como ejemplo
                        $badUrls = $results->where('status_code', '!=', 200)->take(5)->pluck('url')->implode(', ');

                        // 3. Llamada a Gemini 2.5 Flash
                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            $prompt = "
                                Eres un Auditor Técnico SEO Senior.
                                Analiza estos datos del sitio: {$project->domain_url}
                                
                                DATOS:
                                - Total URLs rastreadas: {$total}
                                - Errores 404: {$errors404}
                                - Sin H1: {$missingH1}
                                - Contenido pobre (<300 palabras): {$thinContent}
                                - Ejemplos de errores: {$badUrls}

                                TAREA:
                                Genera un reporte HTML breve.
                                Usa <h3> para títulos y <ul> para listas.
                                1. Diagnóstico de Salud (0-100).
                                2. Errores Críticos (Prioridad Alta).
                                3. Recomendaciones rápidas.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.7]
                                ]);

                            $aiReport = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Error generando reporte.';
                            
                            // Limpiar markdown si Gemini lo pone
                            $aiReport = str_replace(['```html', '```'], '', $aiReport);

                            return new HtmlString("<div class='prose dark:prose-invert'>{$aiReport}</div>");

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