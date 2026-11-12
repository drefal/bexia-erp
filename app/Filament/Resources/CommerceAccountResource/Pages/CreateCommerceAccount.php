<?php

namespace App\Filament\Resources\CommerceAccountResource\Pages;

use App\Filament\Resources\CommerceAccountResource;
use App\Models\CommerceChannel;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateCommerceAccount extends CreateRecord
{
    protected static string $resource =
        CommerceAccountResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        if (! CommerceAccountResource::canManageCompany()) {
            abort(403);
        }

        $companyId =
            CommerceAccountResource::currentCompanyId();

        if (! $companyId) {
            abort(403);
        }

        $channelId = (int) (
            $data['commerce_channel_id'] ?? 0
        );

        if (! CommerceChannel::query()
            ->whereKey($channelId)
            ->exists()) {
            throw ValidationException::withMessages([
                'data.commerce_channel_id' =>
                    'Selecciona un marketplace válido.',
            ]);
        }

        // Nunca tomar empresa, estado activo o estatus
        // desde campos enviados por el navegador.
        $data['company_id'] = $companyId;
        $data['status'] = 'draft';
        $data['is_active'] = false;

        return $data;
    }
}
