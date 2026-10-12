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
use App\Jobs\WriteArticleJob;
use App\Jobs\GenerateArticlesJob;

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
                    ->label('Título del Artículo'),

                Forms\Components\TextInput::make('keyword')
                    ->label('Palabra Clave'),

                Forms\Components\DatePicker::make('scheduled_date')
                    ->label('Fecha de Publicación')
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pendiente',
                        'draft' => 'Borrador',
                        'generated' => 'Redactado',
                        'needs_review' => 'Requiere Revisión',
                        'approved' => 'Aprobado',
                        'published' => 'Publicado',
                    ])
                    ->default('pending'),

                Forms\Components\RichEditor::make('content')
                    ->label('Contenido (IA)')
                    ->columnSpanFull(),
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
                    ->label('Título')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'generated' => 'warning',
                        'needs_review' => 'danger',
                        'approved' => 'info',
                        'published' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('quality_issues')
                    ->label('Calidad')
                    ->badge()
                    ->state(function (Article $record): string {
                        $issues = $record->quality_issues;
                        if ($issues === null) return 'Sin revisar';
                        $criticals = count($issues['critical'] ?? []);
                        $warnings = count($issues['warnings'] ?? []);
                        if ($criticals > 0) return "{$criticals} críticos";
                        if ($warnings > 0) return "{$warnings} avisos";
                        return 'OK';
                    })
                    ->color(function (Article $record): string {
                        $issues = $record->quality_issues;
                        if ($issues === null) return 'gray';
                        if (!empty($issues['critical'])) return 'danger';
                        if (!empty($issues['warnings'])) return 'warning';
                        return 'success';
                    })
                    ->tooltip(function (Article $record): ?string {
                        $issues = $record->quality_issues;
                        if ($issues === null) return null;
                        $all = array_merge($issues['critical'] ?? [], $issues['warnings'] ?? []);
                        return $all === [] ? 'Cumple todas las reglas del prompt' : implode("\n", $all);
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Programar Nuevo Post'),
                
                // BOTÓN GENERADOR MASIVO (El botón verde)
                Tables\Actions\Action::make('generate_calendar_db')
                    ->label('2. Programar Artículos')
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generación Masiva')
                    ->form([
                        Forms\Components\Select::make('frequency')
                            ->label('Frecuencia')
                            ->options(['1'=>'1/semana', '2'=>'2/sem', '3'=>'3/sem', '5'=>'Diario'])
                            ->default('1')
                            ->required()
                            ->helperText('1/semana publica siempre el mismo día de la semana que la fecha de inicio.'),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Inicio (fecha del primer artículo)')
                            ->default(now()->addDay())
                            ->required(),
                    ])
                    ->action(function ($data, $livewire) {
                        $project = $livewire->getOwnerRecord();
                        
                        if (empty($project->seo_strategy)) {
                            Notification::make()->title('Error')->body('Falta la radiografía.')->danger()->send();
                            return;
                        }

                        try {
                            GenerateArticlesJob::dispatch(
    							$project, 
    							(int) $data['frequency'], 
    							$data['start_date']
								)->onConnection('database'); // <--- EL MARTILLAZO 🔨

                            Notification::make()
                                ->title("Programación Masiva Iniciada")
                                ->body("Ejecuta el worker en la consola.")
                                ->success()
                                ->persistent()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->actions([
                // BOTÓN IA INDIVIDUAL (El de la varita mágica)
                Tables\Actions\Action::make('write_article')
                    ->label('IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Redactar con IA')
                    ->modalDescription('La IA escribirá este artículo en segundo plano.')
                    ->action(function (Article $record) {
                        try {
                            // LLAMADA LIMPIA (Sin ->onConnection, ya lo definimos en .env)
                            WriteArticleJob::dispatch($record)->onConnection('database');

                            Notification::make()
                                ->title('Redacción Iniciada ✍️')
                                ->body('Ejecuta el worker en la consola.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Article $record): bool => in_array($record->status, ['generated', 'needs_review']))
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Artículo')
                    ->modalDescription('El artículo se publicará automáticamente cuando llegue su fecha programada.')
                    ->action(function (Article $record) {
                        $record->update(['status' => 'approved']);
                        Notification::make()->title('Artículo aprobado')->success()->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}