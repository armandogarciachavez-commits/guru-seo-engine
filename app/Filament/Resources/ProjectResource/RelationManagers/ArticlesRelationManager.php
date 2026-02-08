<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str; // Importante para el slug
use App\Services\ArticleGeneratorService; // Importamos el cerebro

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'published' => 'Published',
                        'completed' => 'Completed',
                        'draft' => 'Draft',
                    ])
                    ->required(),
                Forms\Components\RichEditor::make('html_content')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('thumbnail_url')
                    ->url()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'published' => 'success',
                        'completed' => 'info',
                        'draft' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // --- BOTÓN DE IA MEJORADO (ANTI-GATOS 🐱🚫) ---
                Tables\Actions\Action::make('generate_ai')
                    ->label('✨ Generar con IA')
                    ->color('primary')
                    ->icon('heroicon-o-sparkles')
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Título del Artículo')
                            ->placeholder('Ej: Por que necesitas un community manager')
                            ->required(),
                        Forms\Components\TextInput::make('keyword')
                            ->label('Palabra Clave (Opcional)')
                            ->placeholder('Ej: marketing, social media')
                            ->helperText('Si lo dejas vacío, usaremos fotos de oficina genéricas.'),
                    ])
                    ->action(function (array $data, $livewire) {
                        $project = $livewire->getOwnerRecord();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Trabajando...')
                            ->body('Redactando texto y buscando la mejor foto...')
                            ->info()
                            ->send();

                        try {
                            // 1. Generamos el Texto con la IA
                            $generator = new ArticleGeneratorService();
                            $keyword = $data['keyword'] ?? $data['title'];
                            
                            $htmlContent = $generator->generate($project, $keyword);

                            // 2. Generamos la URL de la FOTO REAL 📸
                            // LÓGICA MEJORADA:
                            // Si el usuario puso keyword, la usamos.
                            // Si NO puso keyword, usamos "business,office,technology" por defecto.
                            if (!empty($data['keyword'])) {
                                $imageTags = str_replace(' ', ',', $data['keyword']); 
                            } else {
                                $imageTags = 'business,office,work,meeting'; // Respaldo seguro profesional
                            }
                            
                            // Agregamos un número aleatorio al final para que la foto cambie siempre
                            $randomizer = rand(1, 10000);
                            $imageUrl = "https://loremflickr.com/800/400/{$imageTags}/all?lock={$randomizer}";

                            // 3. Guardamos todo en la base de datos
                            \App\Models\Article::create([
                                'project_id' => $project->id,
                                'title' => $data['title'],
                                'slug' => Str::slug($data['title']),
                                'keyword' => $data['keyword'],
                                'html_content' => $htmlContent,
                                'thumbnail_url' => $imageUrl, // <--- FOTO GARANTIZADA
                                'status' => 'draft',
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('¡Artículo Listo!')
                                ->body('Texto e imagen generados con éxito.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body('Algo falló: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                // ------------------

                Tables\Actions\CreateAction::make()->label('Crear Manualmente'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                
                // --- BOTÓN INTELIGENTE DE PUBLICAR ---
                Tables\Actions\Action::make('publish')
                    ->label('Publicar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('¿Publicar este artículo?')
                    ->modalDescription(fn($livewire) => 
                        $livewire->getOwnerRecord()->integration_type === 'wordpress' 
                            ? 'Esto enviará el artículo a tu sitio WordPress.' 
                            : 'Esto hará que el artículo sea visible en tu sitio web HTML.'
                    )
                    ->action(function (\App\Models\Article $record, $livewire) {
                        $project = $livewire->getOwnerRecord();

                        // OPCIÓN A: INTEGRACIÓN WORDPRESS
                        if ($project->integration_type === 'wordpress') {
                            $wpService = app(\App\Services\WordPressService::class);
                            $result = $wpService->publish($record, $project);
                            
                            if ($result) {
                                $record->update(['status' => 'published']);
                                \Filament\Notifications\Notification::make()->title('¡Publicado en WordPress!')->success()->send();
                            } else {
                                \Filament\Notifications\Notification::make()->title('Error de conexión con WP')->danger()->send();
                            }
                        } 
                        // OPCIÓN B: HTML / LOCAL
                        else {
                            $record->update(['status' => 'published']);
                            \Filament\Notifications\Notification::make()
                                ->title('¡Artículo Visible!')
                                ->body('El artículo ahora está marcado como publicado en tu sitio HTML.')
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn(\App\Models\Article $record) => $record->status !== 'published'),
            ]);
    }
}