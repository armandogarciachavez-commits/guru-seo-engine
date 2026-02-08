<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification; // Importante para las alertas
use App\Models\Article; // Importante para crear el artículo

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
                // 1. Botón Manual (El que ya tenías)
                Tables\Actions\CreateAction::make()
                    ->label('Crear Manualmente'),

                // 2. ¡NUEVO! Botón de IA ?
                Tables\Actions\Action::make('generate_ai')
                    ->label('Generar con IA')
                    ->icon('heroicon-o-sparkles') // Icono de brillitos
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('topic')
                            ->label('¿Sobre qué quieres escribir?')
                            ->placeholder('Ej: 5 Ventajas del SEO Local')
                            ->required(),
                        
                        Forms\Components\Select::make('tone')
                            ->label('Tono')
                            ->options([
                                'professional' => 'Profesional',
                                'casual' => 'Casual / Divertido',
                                'informative' => 'Informativo',
                            ])
                            ->default('professional'),
                    ])
                    ->action(function (array $data, $livewire) {
                        // AQUÍ OCURRE LA MAGIA
                        $project = $livewire->getOwnerRecord();
                        
                        // NOTA: Aquí simulamos la generación rápida para que veas que funciona.
                        // Luego conectaremos tu servicio de OpenAI/Gemini real.
                        
                        $mockContent = "<h1>{$data['topic']}</h1><p>Este es un artículo generado automáticamente sobre <strong>{$data['topic']}</strong> usando el tono {$data['tone']}.</p><p>Aquí iría el contenido real de la IA...</p>";

                        Article::create([
                            'project_id' => $project->id,
                            'title' => $data['topic'],
                            'content' => $mockContent,
                            'status' => 'draft',
                            'is_published' => false,
                        ]);

                        Notification::make()
                            ->title('¡Artículo Generado!')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                
                // Botón de Publicar Rápido
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