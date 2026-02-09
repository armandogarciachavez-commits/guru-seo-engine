<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log; // <--- Nuevo: Para ver errores en consola
use App\Models\CrawlResult;
use App\Models\Article;
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
                
                // --- BOTÓN 1: GENERAR (Lógica movida a action() para estabilidad) ---
                Tables\Actions\Action::make('analyze_save_strategy')
                    ->label('1. Crear Radiografía Maestra')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('danger')
                    ->modalHeading('Generar Estrategia con IA')
                    ->modalDescription('Esto puede tardar hasta 60 segundos. Por favor espera a la notificación verde.')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Textarea::make('competitors')
                            ->label('Competidores (Opcional)')
                            ->placeholder('Ej: competencia1.com, elgigante.mx')
                            ->rows(2),
                    ])
                    ->action(function ($data, $livewire) {
                        // 1. AUMENTAR TIEMPO DE ESPERA
                        set_time_limit(300);
                        ini_set('max_execution_time', 300);
                        
                        Log::info('Inicio de generación de estrategia SEO...'); // Log para depurar

                        $project = $livewire->getOwnerRecord();
                        $results = $project->crawlResults;

                        if ($results->isEmpty()) {
                            Notification::make()->title('Error: Sin datos')->body('Primero rastrea el sitio.')->danger()->send();
                            return;
                        }

                        // 2. PREPARAR DATOS (Max 30 URLs para velocidad)
                        $siteMapData = $results->where('status_code', 200)->take(30)->map(fn($r) => "URL: {$r->url} | H1: {$r->h1} | Title: {$r->title}")->implode("\n");
                        $ciudad = $project->target_city ?? 'Local';
                        $competidores = $data['competitors'] ?? 'N/A';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            $prompt = "
                                ROL: Estratega SEO Senior.
                                TAREA: Generar la 'Radiografía Maestra' del sitio {$project->domain_url} en {$ciudad}.
                                COMPETENCIA: {$competidores}.
                                DATOS: {$siteMapData}

                                ESTRUCTURA HTML (Usa Tailwind classes):
                                <div class='space-y-4'>
                                    <h2 class='text-xl font-bold text-blue-600'>1. Diagnóstico de Identidad</h2>
                                    <p>...</p>
                                    
                                    <h2 class='text-xl font-bold text-blue-600'>2. Análisis de Brechas</h2>
                                    <p>...</p>

                                    <h2 class='text-xl font-bold text-blue-600'>3. Oportunidades de Keywords</h2>
                                    <ul class='list-disc pl-5'>...</ul>

                                    <h2 class='text-xl font-bold text-blue-600'>4. Pilares de Contenido</h2>
                                    <ul class='list-disc pl-5'>...</ul>
                                </div>
                                
                                IMPORTANTE: Sé estratégico.
                            ";

                            Log::info('Enviando petición a Gemini...');

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(120)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.4]
                                ]);

                            $aiReport = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

                            if (!$aiReport) {
                                throw new \Exception("La IA respondió vacío.");
                            }

                            $aiReport = str_replace(['```html', '```'], '', $aiReport);

                            // 3. GUARDAR EN BASE DE DATOS
                            $project->update(['seo_strategy' => $aiReport]);
                            
                            Log::info('Estrategia guardada correctamente.');

                            // 4. NOTIFICAR ÉXITO
                            Notification::make()
                                ->title('¡Estrategia Guardada!')
                                ->body('Ahora puedes usar el botón GRIS para verla o el VERDE para programar.')
                                ->success()
                                ->persistent() // Se queda en pantalla hasta que lo cierras
                                ->send();

                        } catch (\Exception $e) {
                            Log::error('Error en estrategia SEO: ' . $e->getMessage());
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // --- BOTÓN 2: VER (Siempre visible) ---
                Tables\Actions\Action::make('view_strategy')
                    ->label('Ver Radiografía Guardada')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Estrategia Actual Guardada')
                    ->modalWidth('7xl')
                    ->action(function ($livewire) {
                        // Acción vacía para que no se queje Filament, lo importante es el modalContent
                        if (empty($livewire->getOwnerRecord()->seo_strategy)) {
                             Notification::make()->title('Vacío')->body('Primero genera la estrategia con el botón Rojo.')->warning()->send();
                        }
                    })
                    ->modalContent(function ($livewire) {
                        $strategy = $livewire->getOwnerRecord()->seo_strategy;
                        if (empty($strategy)) {
                            return new HtmlString('<div class="text-center p-4 text-gray-500">⚠️ No hay estrategia guardada. Ejecuta el botón Rojo primero.</div>');
                        }
                        return new HtmlString("<div class='prose dark:prose-invert max-w-none'>{$strategy}</div>");
                    }),

                // --- BOTÓN 3: PROGRAMAR (Usa lo guardado) ---
                Tables\Actions\Action::make('generate_calendar_db')
                    ->label('2. Programar Artículos (Auto)')
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generación Masiva de Artículos')
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
                        set_time_limit(300);
                        ini_set('max_execution_time', 300);

                        $project = $livewire->getOwnerRecord();
                        
                        if (empty($project->seo_strategy)) {
                            Notification::make()->title('Error')->body('Primero genera la radiografía (Botón Rojo).')->danger()->send();
                            return;
                        }

                        $frecuencia = (int) $data['frequency'];
                        $postsA_Generar = $frecuencia * 4; 
                        $fechaInicio = Carbon::parse($data['start_date']);

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            $prompt = "
                                ACTÚA COMO: API JSON de Planificación de Contenidos.
                                CONTEXTO: {$project->seo_strategy}
                                TAREA: Genera un JSON array con {$postsA_Generar} ideas.
                                FORMATO JSON: [{\"title\": \"...\", \"keyword\": \"...\", \"intention\": \"...\"}]
                                SIN MARKDOWN.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(60)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.5]
                                ]);

                            $jsonText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
                            $jsonText = str_replace(['```json', '```', '```'], '', $jsonText); 
                            
                            $plan = json_decode($jsonText, true);

                            if (!is_array($plan)) throw new \Exception("Formato inválido de IA");

                            $currentDate = $fechaInicio->copy();
                            $count = 0;

                            foreach ($plan as $item) {
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

                            Notification::make()->title("Se programaron {$count} artículos")->success()->send();

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
