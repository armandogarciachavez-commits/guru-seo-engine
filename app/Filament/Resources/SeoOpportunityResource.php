<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeoOpportunityResource\Pages;
use App\Models\SeoOpportunity;
use App\Services\OpportunityOptimizerService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class SeoOpportunityResource extends Resource
{
    protected static ?string $model = SeoOpportunity::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';
    protected static ?string $navigationLabel = 'Oportunidades SEO';
    protected static ?string $modelLabel = 'Oportunidad SEO';
    protected static ?string $pluralModelLabel = 'Oportunidades SEO';
    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority_score', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Proyecto')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('query')
                    ->label('Keyword')
                    ->weight('bold')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('position')
                    ->label('Pos.')
                    ->formatStateUsing(fn ($state) => round($state, 1))
                    ->sortable(),

                Tables\Columns\TextColumn::make('impressions')
                    ->label('Impr.')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ctr')
                    ->label('CTR')
                    ->formatStateUsing(fn ($state) => round($state * 100, 1) . '%'),

                Tables\Columns\TextColumn::make('potential_clicks')
                    ->label('Clics pot.')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => '+' . $state),

                Tables\Columns\TextColumn::make('priority_score')
                    ->label('Prioridad')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'gray',
                        'in_progress' => 'warning',
                        'applied' => 'success',
                        'dismissed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('detected_at')
                    ->label('Detectada')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'new' => 'Nueva',
                        'in_progress' => 'En progreso',
                        'applied' => 'Aplicada',
                        'dismissed' => 'Descartada',
                    ]),
                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Proyecto')
                    ->relationship('project', 'name'),
            ])
            ->actions([
                // Ver / generar la sugerencia de la IA.
                Tables\Actions\Action::make('ver_sugerencia')
                    ->label('Sugerencia IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->modalHeading('Metadatos sugeridos por la IA')
                    ->modalContent(function (SeoOpportunity $record) {
                        $s = $record->ai_suggestions;
                        if (empty($s)) {
                            return new HtmlString('<div class="p-4 text-sm text-gray-500">Aún no hay sugerencia. Usa "Generar con IA".</div>');
                        }

                        $title = e($s['title'] ?? '');
                        $meta = e($s['meta_description'] ?? '');
                        $notes = e($s['notes'] ?? '');

                        return new HtmlString("
                            <div class='space-y-3 p-2 text-sm'>
                                <div><span class='font-semibold'>Title:</span><br>{$title}</div>
                                <div><span class='font-semibold'>Meta description:</span><br>{$meta}</div>
                                " . ($notes ? "<div class='text-gray-500'><span class='font-semibold'>Por qué:</span> {$notes}</div>" : '') . "
                            </div>
                        ");
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),

                Tables\Actions\Action::make('generar_ia')
                    ->label('Generar con IA')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (SeoOpportunity $record) {
                        $sug = (new OpportunityOptimizerService)->optimize($record);
                        if ($sug) {
                            Notification::make()->title('Sugerencia generada')->success()->send();
                        } else {
                            Notification::make()->title('No se pudo generar')->body('Reintenta o revisa OPENROUTER_API_KEY.')->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('aplicada')
                    ->label('Marcar aplicada')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (SeoOpportunity $record) => $record->update(['status' => 'applied'])),

                Tables\Actions\Action::make('descartar')
                    ->label('Descartar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (SeoOpportunity $record) => $record->update(['status' => 'dismissed'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSeoOpportunities::route('/'),
        ];
    }
}
