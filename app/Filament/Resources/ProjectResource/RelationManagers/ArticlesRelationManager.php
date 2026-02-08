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
                    ->label('Generar Artículo SEO')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('topic')
                            ->label('¿Sobre qué quieres escribir?')
                            ->placeholder('Ej: 5 Tips para mejorar tus ventas')
                            ->required(),
                        
                        Forms\Components\Select::make('tone')
                            ->label('Tono de Voz')
                            ->options([
                                'expert' => 'Experto y Técnico (Para B2B)',
                                'friendly' => 'Amigable y Cercano (Para B2C)',
                                'persuasive' => 'Persuasivo y Vendedor',
                                'informative' => 'Educativo y Neutral',
                            ])
                            ->default('expert'),
                            
                        Forms\Components\Textarea::make('context_extra')
                            ->label('Contexto Extra (Opcional)')
                            ->placeholder('Ej: Enfócate en vender el servicio de auditoría...')
                            ->rows(2),
                    ])
                    ->action(function (array $data, $livewire) {
                        // Aumentar tiempo de ejecución para evitar timeouts
                        set_time_limit(300);
                        ini_set('max_execution_time', 300); 

                        // 1. OBTENER DATOS DEL PROYECTO (EL CLIENTE)
                        $project = $livewire->getOwnerRecord();
                        $clienteNombre = $project->title ?? 'Nuestra Marca';
                        $clienteUrl = $project->url ?? '#'; // Si tienes campo URL en la tabla projects
                        $contextoExtra = $data['context_extra'] ?? '';

                        Notification::make()
                            ->title("Escribiendo para {$clienteNombre}...")
                            ->body('Gemini está analizando tu marca y redactando...')
                            ->info()
                            ->persistent()
                            ->send();

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            if (empty($apiKey)) {
                                throw new \Exception('Falta la API KEY en .env');
                            }

                            // 2. EL PROMPT DINÁMICO (LA CLAVE DEL ÉXITO)
                            $prompt = "
                                Actúa como el Redactor Oficial del blog de la marca: '{$clienteNombre}'.
                                
                                TUS OBJETIVOS:
                                1. Escribir un artículo sobre: '{$data['topic']}'.
                                2. Adaptar tu personalidad al nombre del negocio ('{$clienteNombre}'). Si suena a restaurante, sé apetitoso. Si suena a abogado, sé formal.
                                3. Tono: {$data['tone']}.
                                4. Contexto Adicional: {$contextoExtra}.

                                REQUISITOS DE FORMATO:
                                - Longitud: 700-900 palabras.
                                - Formato: HTML limpio (h2, h3, p, ul, li, strong). NO uses h1.
                                - Menciones: Menciona a '{$clienteNombre}' naturalmente en el texto.
                                - Cierre: Termina con un párrafo invitando a visitar el sitio o contactar a '{$clienteNombre}'.

                                IMPORTANTE:
                                - No inventes URL falsas, usa el nombre de la marca.
                                - Devuelve SOLO el código HTML.
                            ";

                            // Usamos Gemini 2.5 Flash para velocidad y calidad
                            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(120)
                                ->post($url, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => [
                                        'temperature' => 0.75, // Un poco más creativo
                                        'maxOutputTokens' => 8192,
                                    ]
                                ]);

                            if ($response->failed()) {
                                throw new \Exception('Error Google: ' . $response->body());
                            }

                            $json = $response->json();
                            $aiContent = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                            if (empty($aiContent)) {
                                throw new \Exception('Respuesta vacía de Gemini');
                            }

                            $aiContent = str_replace(['```html', '```'], '', $aiContent);

                            Article::create([
                                'project_id' => $project->id,
                                'title' => $data['topic'],
                                'content' => $aiContent,
                                'status' => 'draft',
                                'is_published' => false,
                            ]);

                            Notification::make()
                                ->title('¡Artículo Generado!')
                                ->body("Se ha creado contenido personalizado para {$clienteNombre}.")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
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