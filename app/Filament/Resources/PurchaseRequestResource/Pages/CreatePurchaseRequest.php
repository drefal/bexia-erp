<?php

namespace App\Filament\Resources\PurchaseRequestResource\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreatePurchaseRequest extends CreateRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $data['company_id'] ?? $this->currentCompanyId();
        $data['number'] = $data['number'] ?? $this->makePurchaseRequestNumber();
        $data['status'] = $data['status'] ?? 'draft';
        $data['source'] = 'manual';
        $data['requested_by_user_id'] = auth()->id();
        $data['requested_at'] = $data['requested_at'] ?? now();

        if (empty($data['supplier_name']) && ! empty($data['supplier_id'])) {
            $data['supplier_name'] = DB::table('contacts')
                ->where('id', $data['supplier_id'])
                ->value('commercial_name')
                ?: DB::table('contacts')->where('id', $data['supplier_id'])->value('name')
                ?: 'Proveedor #' . $data['supplier_id'];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        PurchaseRequestResource::recalculateTotals($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', [
            'record' => $this->record,
        ]);
    }

    protected function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }

    protected function makePurchaseRequestNumber(): string
    {
        $nextId = ((int) (DB::table('purchase_requests')->max('id') ?? 0)) + 1;

        return 'SC-' . now()->format('Ymd') . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }
}
