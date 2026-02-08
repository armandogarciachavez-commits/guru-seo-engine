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
                    ->label('Generar con Gemini 2.5 Flash') // <--- Cambio a FLASH
                    ->icon('heroicon-o-bolt') // Icono de rayo (Velocidad)
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('topic')
                            ->label('¿Sobre qué quieres escribir?')
                            ->placeholder('Ej: Guía completa de Link Building 2026')
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
                            ->title('Gemini 2.5 Flash trabajando...')
                            ->body('Generando contenido a máxima velocidad...')
                            ->warning()
                            ->send();

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            if (empty($apiKey)) {
                                throw new \Exception('No se encontró GEMINI_API_KEY en el archivo .env');
                            }

                            // 1. Prompt Optimizado
                            $prompt = "
                                Actúa como un experto mundial en SEO y Copywriting.
                                TEMA: '{$data['topic']}'.
                                TONO: {$data['tone']}.
                                
                                INSTRUCCIONES:
                                1. Escribe un artículo EXTENSO y PROFUNDO (+1000 palabras).
                                2. Usa formato HTML semántico impecable (h2, h3, p, ul, li, strong, blockquote). NO uses h1.
                                3. Estructura: 
                                   - Introducción impactante.
                                   - Desarrollo exhaustivo con datos y ejemplos.
                                   - Conclusión accionable.
                                4. Estilo: Fluido, humano, sin repeticiones robóticas.
                                
                                IMPORTANTE: Devuelve SOLO el código HTML limpio.
                            ";

                            // 2. URL DEL MODELO GEMINI 2.5 FLASH (Velocidad Extrema)
                            // Usamos el nombre exacto de tu lista: models/gemini-2.5-flash
                            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

                            // 3. Payload
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
                                    'maxOutputTokens' => 8192,
                                ]
                            ];

                            // 4. Llamada HTTP (Mantenemos timeout alto por si acaso, pero Flash debería tardar <20s)
                            $response = Http::withHeaders([
                                'Content-Type' => 'application/json',
                            ])
                            ->timeout(60)
                            ->post($url, $payload);

                            if ($response->failed()) {
                                $errorBody = $response->json();
                                $msg = $errorBody['error']['message'] ?? $response->body();
                                throw new \Exception('Google API Error: ' . $msg);
                            }

                            $json = $response->json();
                            $aiContent = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                            if (empty($aiContent)) {
                                throw new \Exception('Gemini respondió vacío.');
                            }

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
                                ->title('¡Artículo Creado (Flash)!')
                                ->body('Generado exitosamente a alta velocidad.')
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