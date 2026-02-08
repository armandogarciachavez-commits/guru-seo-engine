<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth; // Importante para obtener tu ID

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    // MAGIA AQUÍ: Antes de crear, asignamos el usuario actual
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id(); // Asigna tu ID (1) al proyecto
        
        return $data;
    }
    
    // Opcional: Redirigir al índice después de crear
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}