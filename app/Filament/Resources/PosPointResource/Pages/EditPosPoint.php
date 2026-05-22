<?php

namespace App\Filament\Resources\PosPointResource\Pages;

use App\Filament\Resources\PosPointResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosPoint extends EditRecord
{
    protected static string $resource = PosPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_pos')
                ->label('Abrir sesión')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => url('/pos/' . $this->record->id . '/open'))
                ->openUrlInNewTab(),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // V5.61.3c: normalizar arreglos JSON de configuracion de pagos.
        foreach ([
            'payment_method_ids',
            'currency_ids',
            'cash_denomination_ids',
            'available_price_list_ids',
            'allowed_category_ids',
        ] as $jsonField) {
            $value = $data[$jsonField] ?? [];

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : [];
            }

            if (! is_array($value)) {
                $value = [];
            }

            $data[$jsonField] = array_values(array_unique(array_map('strval', array_filter($value, fn ($item) => $item !== null && $item !== ''))));
        }

        if (empty($data['currency_ids']) && ! empty($data['default_currency_id'])) {
            $data['currency_ids'] = [(string) $data['default_currency_id']];
        }


        return $data;
    }

}
