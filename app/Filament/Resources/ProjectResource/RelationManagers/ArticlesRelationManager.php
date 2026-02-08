<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\Article;
use Illuminate\Support\Facades\Http; // <--- Usamos el cliente HTTP nativo de Laravel

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
                    ->label('Generar con Gemini')
                    ->icon('heroicon-o-sparkles')
                    ->color('info') // Color Azul (Gemini)
                    ->form([
                        Forms\Components\TextInput::make('topic')
                            ->label('¿Sobre qué quieres escribir?')
                            ->placeholder('Ej: 5 Ventajas del SEO Local')
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
                            ->title('Gemini está pensando...')
                            ->body('Redactando tu contenido...')
                            ->info()
                            ->send();

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            // Validación básica
                            if (empty($apiKey)) {
                                throw new \Exception('No se encontró GEMINI_API_KEY en el archivo .env');
                            }

                            // 1. Construir el Prompt
                            $prompt = "
                                Actúa como un experto en SEO y Copywriting.
                                Escribe un artículo detallado sobre: '{$data['topic']}'.
                                
                                Requisitos:
                                - Tono: {$data['tone']}.
                                - Idioma: Español.
                                - Formato: HTML puro (usa h2, h3, p, ul, li, strong). NO uses h1.
                                - Estructura: Introducción, Desarrollo (con subtítulos), Conclusión.
                                - Longitud: Aprox 600 palabras.
                                - IMPORTANTE: Solo devuelve el código HTML del contenido, sin ```html ni explicaciones extra.
                            ";

                            // 2. Definir la URL limpia (sin basura de markdown)
                            $url = "[https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=)" . $apiKey;

                            // 3. Llamada a la API
                            $response = Http::withHeaders([
                                'Content-Type' => 'application/json',
                            ])->post($url, [
                                'contents' => [
                                    [
                                        'parts' => [
                                            ['text' => $prompt]
                                        ]
                                    ]
                                ]
                            ]);

                            // 4. Verificar errores de la API
                            if ($response->failed()) {
                                throw new \Exception('Error de Google Gemini: ' . $response->body());
                            }

                            // 5. Extraer y limpiar respuesta
                            $json = $response->json();
                            $aiContent = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                            if (empty($aiContent)) {
                                throw new \Exception('Gemini respondió vacío.');
                            }

                            // Limpiar markdown si Gemini lo pone
                            $aiContent = str_replace(['```html', '```'], '', $aiContent);

                            // 6. Guardar en Base de Datos
                            Article::create([
                                'project_id' => $project->id,
                                'title' => $data['topic'],
                                'content' => $aiContent,
                                'status' => 'draft',
                                'is_published' => false,
                            ]);

                            Notification::make()
                                ->title('¡Artículo Gemini Generado!')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error de IA')
                                ->body($e->getMessage())
                                ->danger()
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