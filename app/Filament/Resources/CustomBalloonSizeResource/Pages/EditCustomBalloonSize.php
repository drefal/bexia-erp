<?php

namespace App\Filament\Resources\CustomBalloonSizeResource\Pages;

use App\Filament\Resources\CustomBalloonSizeResource;
use App\Models\CustomBalloonPrice;
use App\Support\CustomBalloonsAccess;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditCustomBalloonSize extends EditRecord
{
    protected static string $resource =
        CustomBalloonSizeResource::class;

    protected ?float $pendingPrice = null;

    protected function mutateFormDataBeforeFill(
        array $data
    ): array {
        $data['current_price'] =
            CustomBalloonPrice::query()
                ->where(
                    'company_id',
                    CustomBalloonsAccess::COMPANY_ID
                )
                ->where('size_id', $this->record->id)
                ->whereNull('valid_to')
                ->value('price');

        return $data;
    }

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        $this->pendingPrice =
            isset($data['current_price'])
                ? (float) $data['current_price']
                : null;

        unset($data['current_price']);

        $data['company_id'] =
            CustomBalloonsAccess::COMPANY_ID;

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->pendingPrice === null) {
            return;
        }

        DB::transaction(function (): void {
            $current = CustomBalloonPrice::query()
                ->where(
                    'company_id',
                    CustomBalloonsAccess::COMPANY_ID
                )
                ->where('size_id', $this->record->id)
                ->whereNull('valid_to')
                ->lockForUpdate()
                ->first();

            if (
                $current
                && (float) $current->price ===
                    (float) $this->pendingPrice
            ) {
                return;
            }

            $now = now();

            if ($current) {
                $current->update([
                    'valid_to' => $now,
                ]);
            }

            CustomBalloonPrice::create([
                'company_id' =>
                    CustomBalloonsAccess::COMPANY_ID,
                'size_id' => $this->record->id,
                'price' => $this->pendingPrice,
                'valid_from' => $now,
                'valid_to' => null,
                'created_by_user_id' => auth()->id(),
                'notes' =>
                    'Cambio de precio desde configuración de globos',
            ]);
        });
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
