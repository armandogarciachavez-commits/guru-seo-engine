<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
// Importamos el Job
use App\Jobs\WriteArticleJob;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    protected static ?string $title = 'Calendario Editorial & Redacción';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->label('Titulo del Articulo'),

                Forms\Components\TextInput::make('keyword')
                    ->label('Palabra Clave'),

                Forms\Components\DatePicker::make('scheduled_date')
                    ->label('Fecha de Publicacion')
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pendiente',
                        'generated' => 'Redactado',
                        'published' => 'Publicado',
                    ])
                    ->default('pending'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('scheduled_date', 'asc') 
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Programado')
                    ->date('d M, Y')
                    ->sortable()
                    ->icon('heroicon-m-calendar'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Titulo')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'generated' => 'warning',
                        'published' => 'success',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Programar Nuevo Post'),
            ])
            ->actions([
                // --- BOTON IA (AHORA ASINCRONO) ---
                Tables\Actions\Action::make('write_article')
                    ->label('IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Redactar con IA')
                    ->modalDescription('La IA escribirá este artículo en segundo plano. Te avisaremos cuando esté listo.')
                    ->action(function (Article $record) {
                        try {
                            // Enviamos al Job
                            WriteArticleJob::dispatch($record);

                            Notification::make()
                                ->title('Redacción Iniciada ✍️')
                                ->body('El artículo se está escribiendo en segundo plano. Revisa en unos minutos.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                // ----------------------------------

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}