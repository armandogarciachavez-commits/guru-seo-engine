<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\Article;
use Illuminate\Support\Facades\Http; 

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    protected static ?string $recordTitleAttribute = 'title';
    
    protected static ?string $title = 'Artículos Generados';
    protected static ?string $icon = 'heroicon-m-document-text';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->label('Título'),
                
                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Borrador',
                        'published' => 'Publicado',
                    ])
                    ->required()
                    ->label('Estado'),

                Forms\Components\RichEditor::make('content')
                    ->columnSpanFull()
                    ->label('Contenido'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Fecha'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Crear Manualmente'),

                Tables\Actions\Action::make('generate_ai')
                    ->label('Generar con Gemini 1.5 PRO')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning') // Color Premium
                    ->form([
                        Forms\Components\TextInput::make('topic')
                            ->label('¿Sobre qué quieres escribir?')
                            ->placeholder('Ej: Estrategias avanzadas de Inbound Marketing')
                            ->required(),
                        
                        Forms\Components\Select::make('tone')
                            ->label('Tono')
                            ->options([
                                'professional' => 'Profesional y Autoritativo',
                                'casual' => 'Amigable y Casual',
                                'persuasive' => 'Persuasivo (Ventas)',
                                'informative' => 'Informativo y Educativo',
                            ])
                            ->default('professional'),
                    ])
                    ->action(function (array $data, $livewire) {
                        $project = $livewire->getOwnerRecord();
                        
                        Notification::make()
                            ->title('Gemini 1.5 Pro trabajando...')
                            ->body('Redactando contenido extenso (esto tomará unos segundos)...')
                            ->warning()
                            ->send();

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            if (empty($apiKey)) {
                                throw new \Exception('No se encontró GEMINI_API_KEY en el archivo .env');
                            }

                            // 1. Prompt de Nivel Experto (Aprovechando la ventana de contexto de 1M tokens)
                            $prompt = "
                                Actúa como un Senior SEO Specialist y Copywriter de clase mundial.
                                Tarea: Escribir un artículo PROFUNDO y EXTENSO sobre '{$data['topic']}'.
                                
                                Configuración:
                                - Modelo: Gemini 1.5 Pro (Latest).
                                - Tono: {$data['tone']}.
                                - Idioma: Español Neutro.
                                - Formato: HTML semántico (h2, h3, p, ul, li, strong). NO uses h1.
                                - Estructura Obligatoria:
                                   1. Introducción con gancho emocional o dato estadístico.
                                   2. Desarrollo exhaustivo (mínimo 5 subtemas detallados).
                                   3. Uso de listas y negritas para mejorar la legibilidad.
                                   4. Conclusión accionable.
                                - Longitud: +1000 palabras.
                                
                                IMPORTANTE: Entrega SOLO el código HTML limpio.
                            ";

                            // 2. URL FORZADA a la versión "Latest" para evitar errores 404
                            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:generateContent?key=" . $apiKey;

                            // 3. Configuración de parámetros al máximo
                            $payload = [
                                'contents' => [
                                    [
                                        'parts' => [
                                            ['text' => $prompt]
                                        ]
                                    ]
                                ],
                                'generationConfig' => [
                                    'temperature' => 0.85, // Alta creatividad
                                    'topK' => 40,
                                    'topP' => 0.95,
                                    'maxOutputTokens' => 8192, // Máxima salida permitida
                                ]
                            ];

                            // 4. Llamada HTTP
                            $response = Http::withHeaders([
                                'Content-Type' => 'application/json',
                            ])->post($url, $payload);

                            // Manejo detallado del error para saber qué pasa si falla
                            if ($response->failed()) {
                                $errorBody = $response->json();
                                $errorMessage = $errorBody['error']['message'] ?? $response->body();
                                throw new \Exception('Error de Google API: ' . $errorMessage);
                            }

                            $json = $response->json();
                            $aiContent = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                            if (empty($aiContent)) {
                                throw new \Exception('Gemini respondió vacío.');
                            }

                            // Limpieza final
                            $aiContent = str_replace(['```html', '```'], '', $aiContent);

                            // 5. Guardar
                            Article::create([
                                'project_id' => $project->id,
                                'title' => $data['topic'],
                                'content' => $aiContent,
                                'status' => 'draft',
                                'is_published' => false,
                            ]);

                            Notification::make()
                                ->title('¡Artículo Premium Generado!')
                                ->body('Contenido extenso creado con Gemini 1.5 Pro.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error de IA')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                
                Tables\Actions\Action::make('publish')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->action(function (Article $record) {
                        $record->update(['status' => 'published']);
                        Notification::make()->title('Publicado')->success()->send();
                    })
                    ->visible(fn (Article $record) => $record->status !== 'published'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}