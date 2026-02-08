<?php

namespace App\Filament\Resources;

use App\Services\SeoCrawler; // <--- Importante: El cerebro del crawler
use Spatie\Crawler\Crawler;  // <--- Importante: El motor
use Spatie\Crawler\CrawlProfiles\CrawlInternalUrls; // <--- Importante: Perfil
use App\Models\CrawlResult;  // <--- Importante: Donde guardamos datos
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification; // <--- Para avisar cuando termine

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';
    protected static ?string $navigationLabel = 'Mis Proyectos';
    protected static ?string $modelLabel = 'Proyecto';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Proyecto')
                    ->description('Configuración principal.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nombre del Proyecto'),
                        
                        Forms\Components\TextInput::make('domain_url')
                            ->url()
                            ->required()
                            ->prefix('https://')
                            ->maxLength(255)
                            ->label('URL del Sitio'),

                        Forms\Components\Select::make('cms_type')
                            ->options([
                                'wordpress' => 'WordPress',
                                'static' => 'Sitio Estático / HTML',
                                'custom_html' => 'Custom HTML',
                            ])
                            ->required()
                            ->label('CMS'),
                            
                        Forms\Components\Select::make('posting_frequency')
                            ->options([
                                'daily' => 'Diario',
                                'weekly' => 'Semanal',
                                'monthly' => 'Mensual',
                            ])
                            ->default('weekly')
                            ->required()
                            ->label('Frecuencia'),
                    ])->columns(2),

                Forms\Components\Section::make('Configuración de Contenido')
                    ->schema([
                        Forms\Components\TextInput::make('target_language')
                            ->label('Idioma Objetivo')
                            ->default('es-MX')
                            ->required()
                            ->maxLength(10),

                        Forms\Components\TextInput::make('target_city')
                            ->label('Ciudad Objetivo (Local SEO)')
                            ->placeholder('Ej: Manzanillo, Colima')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('brand_voice')
                            ->label('Voz de Marca')
                            ->rows(3)
                            ->placeholder('Ej: Profesional, amigable, experto