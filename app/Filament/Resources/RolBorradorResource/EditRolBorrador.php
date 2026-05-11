<?php

namespace App\Filament\Resources\RolBorradorResource\Pages;

use App\Filament\Resources\RolBorradorResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class EditRolBorrador extends EditRecord
{
    protected static string $resource = RolBorradorResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill([
            'name' => $data['name'],
            'company_id' => $data['company_id'],
        ])->save();

        $permissions = Permission::whereIn('id', $data['permission_ids'] ?? [])->get();

        $record->syncPermissions($permissions);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $record;
    }
}