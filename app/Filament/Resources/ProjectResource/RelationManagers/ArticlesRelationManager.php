<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\Article;
use OpenAI; // <--- IMPORTANTE: Usamos el Cliente Universal

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
                    ->label('Generar con IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
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
                        
                        // 1. Notificación de inicio
                        Notification::make()
                            ->title('Escribiendo artículo...')
                            ->body('Esto puede tardar unos 10-20 segundos.')
                            ->info()
                            ->send();

                        try {
                            // 2. Obtener API Key y Configurar Cliente
                            $apiKey = env('OPENAI_API_KEY');
                            
                            if (empty($apiKey)) {
                                throw new \Exception('No se encontró la API KEY de OpenAI en el archivo .env');
                            }

                            $client = OpenAI::client($apiKey);

                            // 3. Crear el Prompt
                            $prompt = "
                                Actúa como un experto en SEO y Copywriting.
                                Escribe un artículo detallado sobre: '{$data['topic']}'.
                                
                                Requisitos:
                                - Tono: {$data['tone']}.
                                - Idioma: Español.
                                - Formato: HTML puro (usa h2, h3, p, ul, li, strong). NO uses h1 (ya lo tengo).
                                - Estructura: Introducción, 3-5 puntos clave, Conclusión.
                                - Longitud: Aprox 500-800 palabras.
                                - NO incluyas etiquetas <html>, <head> o markdown (```html). Solo el cuerpo del texto.
                            ";

                            // 4. Llamar a la IA (GPT-4o-mini)
                            $response = $client->chat()->create([
                                'model' => 'gpt-4o-mini',
                                'messages' => [
                                    ['role' => 'system', 'content' => 'Eres un redactor de contenidos experto en SEO.'],
                                    ['role' => 'user', 'content' => $prompt],
                                ],
                                'temperature' => 0.7,
                            ]);

                            $aiContent = $response->choices[0]->message->content;

                            // Limpiar si la IA pone bloques de código markdown
                            $aiContent = str_replace(['```html', '```'], '', $aiContent);

                            // 5. Guardar en Base de Datos
                            Article::create([
                                'project_id' => $project->id,
                                'title' => $data['topic'],
                                'content' => $aiContent,
                                'status' => 'draft',
                                'is_published' => false,
                            ]);

                            Notification::make()
                                ->title('¡Artículo Terminado!')
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