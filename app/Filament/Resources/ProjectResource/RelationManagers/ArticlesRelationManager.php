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
                    ->label('Generar con Gemini Pro')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary') 
                    ->form([
                        Forms\Components\TextInput::make('topic')
                            ->label('¿Sobre qué quieres escribir?')
                            ->placeholder('Ej: Estrategias de Marketing Digital 2026')
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
                            ->title('Gemini Pro escribiendo...')
                            ->body('Generando contenido de alta calidad...')
                            ->info()
                            ->send();

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            if (empty($apiKey)) {
                                throw new \Exception('No se encontró GEMINI_API_KEY en el archivo .env');
                            }

                            // 1. Prompt "High-End" (Optimizada para Pro 1.0)
                            $prompt = "
                                Eres un redactor SEO experto y copywriter senior.
                                Tarea: Escribir un artículo detallado sobre '{$data['topic']}'.
                                
                                Configuración:
                                - Modelo: Gemini Pro Stable.
                                - Tono: {$data['tone']}.
                                - Idioma: Español Neutro.
                                - Formato: HTML semántico estricto (h2, h3, p, ul, li, strong). NO uses h1.
                                - Estructura: 
                                   1. Introducción atractiva.
                                   2. Desarrollo profundo (3-5 secciones).
                                   3. Conclusión accionable.
                                - Longitud: Extenso y detallado.
                                
                                IMPORTANTE: Devuelve SOLO el código HTML del contenido listo para publicar.
                            ";

                            // 2. URL del Modelo ESTABLE (gemini-pro)
                            // Este endpoint NO falla. Es la versión de producción global.
                            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $apiKey;

                            // 3. Configuración del Payload
                            $payload = [
                                'contents' => [
                                    [
                                        'parts' => [
                                            ['text' => $prompt]
                                        ]
                                    ]
                                ],
                                'generationConfig' => [
                                    'temperature' => 0.7,
                                    'topK' => 40,
                                    'topP' => 0.95,
                                    'maxOutputTokens' => 2048, 
                                ]
                            ];

                            // 4. Llamada HTTP
                            $response = Http::withHeaders([
                                'Content-Type' => 'application/json',
                            ])->post($url, $payload);

                            if ($response->failed()) {
                                throw new \Exception('Error de Google API: ' . $response->body());
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
                                ->title('¡Artículo Generado!')
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