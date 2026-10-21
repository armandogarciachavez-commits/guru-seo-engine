<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\SeoOpportunity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Pestaña "Último escaneo SEO" dentro del proyecto: muestra las oportunidades
 * (quick-wins) detectadas en la corrida más reciente, por prioridad, con las
 * sugerencias de title/meta de la IA.
 */
class SeoOpportunitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'seoOpportunities';

    protected static ?string $title = 'Último escaneo SEO';

    protected static ?string $icon = 'heroicon-m-magnifying-glass-circle';

    protected static ?string $recordTitleAttribute = 'query';

    /**
     * Limita la tabla al último lote: oportunidades con detected_at dentro de
     * los 5 min del máximo del proyecto (todas las de una corrida lo comparten).
     */
    protected function scopeToLatestBatch(Builder $query): Builder
    {
        $latest = $this->getOwnerRecord()->seoOpportunities()->max('detected_at');

        if (! $latest) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('detected_at', '>=', Carbon::parse($latest)->subMinutes(5));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Último escaneo SEO')
            ->description(fn () => ($at = $this->getOwnerRecord()->latestScanAt())
                ? 'Corrida del '.$at->format('d/m/Y H:i').' · ordenadas por prioridad'
                : 'Aún sin escaneos. Se actualiza cada lunes (o al correr el monitor).')
            ->modifyQueryUsing(fn (Builder $query) => $this->scopeToLatestBatch($query))
            ->defaultSort('priority_score', 'desc')
            ->emptyStateHeading('Sin oportunidades en el último escaneo')
            ->emptyStateDescription('Cuando el agente detecte quick-wins para este proyecto, aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-magnifying-glass')
            ->columns([
                Tables\Columns\TextColumn::make('query')
                    ->label('Keyword')->weight('bold')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('position')
                    ->label('Pos.')->formatStateUsing(fn ($state) => round((float) $state, 1))->sortable(),
                Tables\Columns\TextColumn::make('impressions')
                    ->label('Impr.')->sortable(),
                Tables\Columns\TextColumn::make('ctr')
                    ->label('CTR')->formatStateUsing(fn ($state) => round((float) $state * 100, 1).'%'),
                Tables\Columns\TextColumn::make('potential_clicks')
                    ->label('Clics pot.')->formatStateUsing(fn ($state) => '+'.(int) $state)->sortable(),
                Tables\Columns\TextColumn::make('priority_score')
                    ->label('Prioridad')->badge()->color('warning')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        SeoOpportunity::STATUS_NEW => 'Nueva',
                        SeoOpportunity::STATUS_IN_PROGRESS => 'En progreso',
                        SeoOpportunity::STATUS_APPLIED => 'Aplicada',
                        SeoOpportunity::STATUS_DISMISSED => 'Descartada',
                        default => (string) $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        SeoOpportunity::STATUS_APPLIED => 'success',
                        SeoOpportunity::STATUS_DISMISSED => 'gray',
                        SeoOpportunity::STATUS_IN_PROGRESS => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('ai_title')
                    ->label('Title sugerido (IA)')
                    ->getStateUsing(fn (SeoOpportunity $record) => $record->ai_suggestions['title'] ?? null)
                    ->placeholder('— sin sugerencia —')
                    ->limit(40)->tooltip(fn (SeoOpportunity $record) => $record->ai_suggestions['title'] ?? null)
                    ->copyable()->copyMessage('Title copiado'),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_sugerencia')
                    ->label('Ver sugerencia IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->modalHeading(fn (SeoOpportunity $record) => 'Sugerencia IA · '.$record->query)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->infolist(fn ($infolist, SeoOpportunity $record) => $infolist
                        ->record($record)
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('ai_title')
                                ->label('Title')
                                ->state(fn (SeoOpportunity $r) => $r->ai_suggestions['title'] ?? '—')
                                ->copyable(),
                            \Filament\Infolists\Components\TextEntry::make('ai_meta')
                                ->label('Meta description')
                                ->state(fn (SeoOpportunity $r) => $r->ai_suggestions['meta_description'] ?? '—')
                                ->copyable(),
                            \Filament\Infolists\Components\TextEntry::make('ai_notes')
                                ->label('Notas')
                                ->state(fn (SeoOpportunity $r) => $r->ai_suggestions['notes'] ?? '—')
                                ->color('gray'),
                            \Filament\Infolists\Components\TextEntry::make('page')
                                ->label('Página')->state(fn (SeoOpportunity $r) => $r->page)
                                ->url(fn (SeoOpportunity $r) => $r->page)->openUrlInNewTab(),
                        ]))
                    ->visible(fn (SeoOpportunity $record) => ! empty($record->ai_suggestions)),
            ]);
    }
}
