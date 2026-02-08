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
                    ->color('warning') // Color Ámbar (Premium)
                    ->form([
                        Forms\Components\TextInput::make('topic')
                            ->label('¿Sobre qué quieres escribir?')
                            ->placeholder('Ej: Guía definitiva de Link Building 2026')
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
                            ->title('Gemini Pro trabajando...')
                            ->body('Generando contenido extenso de alta calidad. Espera unos segundos...')
                            ->warning()
                            ->send();

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            if (empty($apiKey)) {
                                throw new \Exception('No se encontró GEMINI_API_KEY en el archivo .env');
                            }

                            // 1. Prompt "High-End" para aprovechar el plan de pago
                            $prompt = "
                                Actúa como un experto Senior en SEO y Copywriting con 10 años de experiencia.
                                
                                TEMA: '{$data['topic']}'.
                                TONO: {$data['tone']}.
                                
                                INSTRUCCIONES AVANZADAS:
                                1. Escribe un artículo EXTENSO y exhaustivo (mínimo 1000 palabras).
                                2. Usa formato HTML semántico estricto: <h2>, <h3>, <p>, <ul>, <li>, <strong>.
                                3. NO uses <h1> (el título ya existe en mi CMS).
                                4. Estructura: 
                                   - Introducción que enganche (método AIDA).
                                   - 4 a 6 secciones de desarrollo profundo.
                                   - Conclusión con llamada a la acción.
                                5. Optimización: Usa palabras clave semánticas relacionadas con el tema de forma natural.
                                
                                IMPORTANTE: Devuelve SOLO el código HTML limpio.
                            ";

                            // 2. URL del Modelo 1.5 Pro (Versión estable)
                            // Usamos concatenación simple para evitar errores de cURL
                            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=" . $apiKey;

                            // 3. Configuración del Payload (Aquí está la magia del plan de pago)
                            $payload = [
                                'contents' => [
                                    [
                                        'parts' => [
                                            ['text' => $prompt]
                                        ]
                                    ]
                                ],
                                // Configuración para sacar el jugo al modelo
                                'generationConfig' => [
                                    'temperature' => 0.8,      // Creatividad alta
                                    'topK' => 40,
                                    'topP' => 0.95,
                                    'maxOutputTokens' => 8192, // ¡CAPACIDAD MÁXIMA DE SALIDA!
                                    'responseMimeType' => 'text/plain',
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
                                ->title('¡Artículo Premium Creado!')
                                ->body('Se ha generado un contenido extenso con éxito.')
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