<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use App\Models\CrawlResult;
use App\Models\Article; // <--- Importante: Usamos el modelo Article
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class CrawlResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'crawlResults';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $title = 'Centro de Comando SEO';
    protected static ?string $icon = 'heroicon-m-cpu-chip';

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
                        $state >= 400 => 'danger',
                        default => 'gray',
                    })->sortable(),
                Tables\Columns\TextColumn::make('url')->label('URL')->limit(30),
                Tables\Columns\TextColumn::make('title')->label('Meta Título')->limit(30)->searchable(),
            ])
            ->headerActions([
                
                // 1. AUDITORÍA Y ESTRATEGIA (GUARDA EN MEMORIA)
                Tables\Actions\Action::make('analyze_save_strategy')
                    ->label('1. Crear Radiografía Maestra')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('danger')
                    ->modalHeading('Generando Estrategia Base')
                    ->modalWidth('7xl')
                    ->form([
                        Forms\Components\Textarea::make('competitors')
                            ->label('Competidores')
                            ->placeholder('Ej: competencia1.com'),
                    ])
                    ->action(function(){}) // Vacío intencional, usamos modalContent
                    ->modalContent(function ($livewire, $data) {
                        // FIX DE TIEMPO
                        set_time_limit(300); 
                        ini_set('max_execution_time', 300);

                        $project = $livewire->getOwnerRecord();
                        $results = $project->crawlResults;

                        if ($results->isEmpty()) return new HtmlString('<p class="text-red-500">Sin datos de rastreo.</p>');

                        // Preparamos datos (Max 40 URLs para agilidad)
                        $siteMapData = $results->where('status_code', 200)->take(40)->map(fn($r) => "URL: {$r->url} | H1: {$r->h1} | Title: {$r->title}")->implode("\n");
                        $ciudad = $project->target_city ?? 'Local';
                        $competidores = $data['competitors'] ?? 'N/A';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            // PROMPT MAESTRO DE ESTRATEGIA
                            $prompt = "
                                ROL: Estratega SEO Senior.
                                TAREA: Generar la 'Radiografía Maestra' del sitio {$project->domain_url} en {$ciudad}.
                                COMPETENCIA: {$competidores}.
                                DATOS: {$siteMapData}

                                ESTRUCTURA DEL REPORTE (HTML Estricto con Tailwind):
                                1. <h2>Diagnóstico de Identidad</h2> (Qué es y qué vende)
                                2. <h2>Análisis de Brechas</h2> (Qué le falta vs competencia)
                                3. <h2>Oportunidades de Keywords</h2> (Tabla con 5 keywords vitales)
                                4. <h2>Pilares de Contenido</h2> (Define 3 temáticas para el blog)
                                
                                IMPORTANTE: Sé estratégico. Este documento servirá de base para crear calendarios automáticos.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(120)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.4]
                                ]);

                            $aiReport = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Error';
                            $aiReport = str_replace(['```html', '```'], '', $aiReport);

                            // GUARDAMOS EN LA BD
                            $project->update(['seo_strategy' => $aiReport]);

                            Notification::make()->title('Estrategia Guardada')->success()->send();

                            return new HtmlString("<div class='prose dark:prose-invert max-w-none'>{$aiReport}</div>");

                        } catch (\Exception $e) {
                            return new HtmlString("<p class='text-red-500'>Error: {$e->getMessage()}</p>");
                        }
                    }),

                // 2. VER ESTRATEGIA (VISUALIZADOR)
                Tables\Actions\Action::make('view_strategy')
                    ->label('Ver Radiografía')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Estrategia Actual Guardada')
                    ->modalWidth('7xl')
                    ->disabled(fn ($livewire) => empty($livewire->getOwnerRecord()->seo_strategy))
                    ->modalContent(fn ($livewire) => new HtmlString("<div class='prose dark:prose-invert max-w-none'>" . $livewire->getOwnerRecord()->seo_strategy . "</div>")),

                // 3. GENERADOR DE CALENDARIO (AUTOMATIZADOR)
                Tables\Actions\Action::make('generate_calendar_db')
                    ->label('2. Programar Artículos (Auto)')
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generación Masiva de Artículos')
                    ->modalDescription('La IA leerá la estrategia guardada y creará los registros en la base de datos.')
                    ->form([
                        Forms\Components\Select::make('frequency')
                            ->label('Frecuencia Semanal')
                            ->options([
                                '2' => '2 artículos (Martes y Jueves)',
                                '3' => '3 artículos (Lun, Mié, Vie)',
                                '5' => 'Diario (Lun a Vie)',
                            ])
                            ->default('3')
                            ->required(),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Fecha de Inicio')
                            ->default(now()->addDay())
                            ->required(),
                    ])
                    ->action(function ($data, $livewire) {
                        // FIX DE TIEMPO
                        set_time_limit(300);
                        ini_set('max_execution_time', 300);

                        $project = $livewire->getOwnerRecord();
                        
                        // VALIDACIÓN
                        if (empty($project->seo_strategy)) {
                            Notification::make()->title('Error: No hay estrategia guardada')->danger()->send();
                            return;
                        }

                        $frecuencia = (int) $data['frequency'];
                        $postsA_Generar = $frecuencia * 4; // 1 mes
                        $fechaInicio = Carbon::parse($data['start_date']);

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            // PROMPT TÉCNICO JSON
                            $prompt = "
                                ACTÚA COMO: API JSON de Planificación de Contenidos.
                                CONTEXTO ESTRATÉGICO:
                                {$project->seo_strategy}
                                
                                TAREA: Genera un array JSON con {$postsA_Generar} ideas de artículos basados en la estrategia.
                                FORMATO JSON STRICTO:
                                [
                                  {
                                    \"title\": \"Título H1 Optimizado\",
                                    \"keyword\": \"Keyword\",
                                    \"intention\": \"Informativa o Comercial\"
                                  }
                                ]
                                NO añadas texto. Solo el array JSON.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(60)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.5]
                                ]);

                            $jsonText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
                            $jsonText = str_replace(['```json', '```'], '', $jsonText); 
                            
                            $plan = json_decode($jsonText, true);

                            if (!is_array($plan)) throw new \Exception("La IA no devolvió un formato válido.");

                            $currentDate = $fechaInicio->copy();
                            $count = 0;

                            foreach ($plan as $item) {
                                // Lógica de fechas
                                if ($frecuencia == 3) $currentDate->addDays(2); 
                                elseif ($frecuencia == 5) {
                                    $currentDate->addDay();
                                    if ($currentDate->isWeekend()) $currentDate->addDays(2);
                                } else $currentDate->addDays(3);

                                Article::create([
                                    'project_id' => $project->id,
                                    'title' => $item['title'],
                                    'keyword' => $item['keyword'] ?? 'General',
                                    'intention' => $item['intention'] ?? 'Informativa',
                                    'status' => 'pending', 
                                    'scheduled_date' => $currentDate->format('Y-m-d'),
                                ]);
                                $count++;
                            }

                            Notification::make()->title("Se han programado {$count} artículos")->success()->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
