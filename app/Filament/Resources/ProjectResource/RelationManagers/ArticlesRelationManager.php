<?php
protected static ?string $title = 'PRUEBA FINAL ZOMBI 💀';
namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use App\Models\Article; 

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    protected static ?string $title = 'Artículos Generados';
    protected static ?string $icon = 'heroicon-m-document-text';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\RichEditor::make('content')
                    ->label('Contenido')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_date')->label('Fecha')->date()->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Título')->limit(30),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'generated' => 'warning',
                        'published' => 'success',
                        default => 'gray',
                    }),
            ])
           ->actions([
                // Botón de prueba simple
                Tables\Actions\Action::make('test_button')
                    ->label('BOTÓN DE PRUEBA')
                    ->icon('heroicon-o-check')
                    ->action(fn() => \Filament\Notifications\Notification::make()->title('Funciona')->send()),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])

                            $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
                            if (!$content) throw new \Exception("IA vacía");
                            
                            $record->update(['content' => str_replace(['```html', '```'], '', $content), 'status' => 'generated']);
                            
                            Notification::make()->title('Redactado con éxito')->success()->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                // ---------------------------------------------------

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
