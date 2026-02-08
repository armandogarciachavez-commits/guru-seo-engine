<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    // Cambiamos el icono para asegurarnos de que se actualice visualmente
    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch'; 

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Proyecto')
                    ->description('Información principal para la generación de contenido.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Proyecto')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('domain_url')
                            ->label('URL del Sitio Web')
                            ->url()
                            ->required()
                            ->prefix('https://')
                            ->maxLength(255),

                        Forms\Components\Select::make('cms_type')
                            ->label('Plataforma (CMS)')
                            ->options([
                                'wordpress' => 'WordPress',
                                'static' => 'Sitio Estático / HTML',
                                'custom_html' => 'Custom HTML',
                            ])
                            ->required(),
                            
                        Forms\Components\Select::make('posting_frequency')
                            ->label('Frecuencia de Publicación')
                            ->options([
                                'daily' => 'Diario',
                                'weekly' => 'Semanal',
                                'monthly' => 'Mensual',
                            ])
                            ->default('weekly')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Identidad y Configuración')
                    ->schema([
                        Forms\Components\TextInput::make('target_language')
                            ->label('Idioma Objetivo')
                            ->default('es-MX')
                            ->required(),
                            
                        Forms\Components\TextInput::make('target_city')
                            ->label('Ciudad Objetivo (Local SEO)')
                            ->placeholder('Ej: Manzanillo'),

                        Forms\Components\Textarea::make('brand_voice')
                            ->label('Voz de Marca')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Describe cómo debe hablar la IA (Ej: Profesional, divertido, experto...)'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Proyecto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('domain_url')
                    ->label('Sitio Web')
                    ->icon('heroicon-m-link')
                    ->url(fn ($record) => $record->domain_url, true) // Abre en nueva pestaña
                    ->color('primary'),

                Tables\Columns\TextColumn::make('cms_type')
                    ->label('CMS')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'wordpress' => 'success',
                        'static' => 'warning',
                        'custom_html' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('posting_frequency')
                    ->label('Frecuencia')
                    ->icon('heroicon-m-clock')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(), // <--- ¡AQUÍ ESTÁ EL BOTÓN DE EDITAR!
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}