<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles'; // Asegúrate de que tu modelo Project tenga esta relación hasMany
    protected static ?string $title = 'Artículos Generados';
    protected static ?string $icon = 'heroicon-m-document-text';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'published' => 'Publicado',
                        'scheduled' => 'Programado',
                    ])
                    ->required(),

                Forms\Components\RichEditor::make('content')
                    ->label('Contenido Generado')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título del Artículo')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'scheduled' => 'warning',
                        'published' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('¿Publicado?')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Generado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Crear Manualmente'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Revisar'),
                Tables\Actions\DeleteAction::make(),
                
                // ESTE ES EL BOTÓN MÁGICO PARA PUBLICAR
                Tables\Actions\Action::make('publish')
                    ->label('Publicar Ahora')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->action(function (Model $record) {
                        // Aquí iría tu lógica de publicación real
                        $record->update(['status' => 'published', 'is_published' => true]);
                    })
                    ->requiresConfirmation()
                    ->visible(fn (Model $record) => $record->status !== 'published'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}