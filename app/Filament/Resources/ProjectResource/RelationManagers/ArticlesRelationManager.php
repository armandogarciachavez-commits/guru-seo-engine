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
                    ->color('danger') // Color Rojo (Potencia Máxima)
                    ->form([
                        Forms\Components\TextInput::make('topic')
                            ->label('¿Sobre qué quieres escribir?')
                            ->placeholder('Ej: Estrategias de SEO Técnico 2026')
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
                            ->title('Gemini 1.5 Pro (001) trabajando...')
                            ->body('Usando versión estable de alta capacidad...')
                            ->warning()
                            ->send();

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            if (empty($apiKey)) {
                                throw new \Exception('No se encontró GEMINI_API_KEY en el archivo .env');
                            }

                            // 1. Prompt Maestro para aprovechar el modelo 1.5
                            $prompt = "
                                Eres un Redactor SEO Senior.
                                OBJETIVO: Escribir el mejor artículo posible sobre '{$data['topic']}'.
                                
                                CONFIGURACIÓN:
                                - Modelo: Gemini 1.5 Pro.
                                - Tono: {$data['tone']}.
                                - Formato: HTML Semántico (h2, h3, p, ul, li, strong). NO uses h1.
                                - Longitud: Extensa (+1000 palabras).
                                
                                ESTRUCTURA:
                                1. Intro poderosa (Pain-Agitate-Solution).
                                2. Desarrollo profundo del tema.
                                3. Ejemplos prácticos o listas.
                                4. Conclusión.
                                
                                IMPORTANTE: Solo devuelve el código HTML limpio.
                            ";

                            // 2. URL DEL MODELO EXACTO (Versión 001 - Producción Estable)
                            // Si esta falla, tu cuenta no tiene acceso a la serie 1.5 en absoluto.
                            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-001:generateContent?key=" . $apiKey;

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
                                    'temperature' => 0.8,
                                    'maxOutputTokens' => 8192, // Alta capacidad
                                ]
                            ];

                            // 4. Llamada HTTP
                            $response = Http::withHeaders([
                                'Content-Type' => 'application/json',
                            ])->post($url, $payload);

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
                                ->title('¡Contenido Premium Generado!')
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