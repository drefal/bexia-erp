<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Filament\Resources\ContactResource\Pages\Concerns\ImportsSatConstancia;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateContact extends CreateRecord
{
    use ImportsSatConstancia;

    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importSatConstanciaAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
                // BEXIA_V5523J2_SAT_COUNTRY_MIRROR
        $countryValue = $data['country_code']
            ?? $data['country']
            ?? $data['sat_country_code']
            ?? null;

        if (($countryValue === null || $countryValue === '') && isset($data['country_id']) && ! is_numeric($data['country_id'])) {
            $countryValue = $data['country_id'];
        }

        $countryUpper = strtr(mb_strtoupper(trim((string) $countryValue)), [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        $data['sat_country_code'] = match ($countryUpper) {
            '', 'MX', 'MEXICO' => 'MEX',
            default => substr($countryUpper, 0, 3),
        };

        // BEXIA_V5523I2_FISCAL_ZIP_MIRROR
        $data['fiscal_zip'] = $data['postal_code'] ?? null;

return $this->normalizeContactData($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $modelClass = static::getModel();

        $record = new $modelClass();
        $record->forceFill($data);
        $record->save();

        return $record;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Guardar contacto');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Guardar y crear otro contacto');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Cancelar');
    }

    protected function normalizeContactData(array $data): array
    {
        foreach (['rfc', 'curp', 'sat_country_code'] as $field) {
            if (isset($data[$field]) && filled($data[$field])) {
                $data[$field] = strtoupper(trim((string) $data[$field]));
            }
        }

        if (blank($data['fiscal_name'] ?? null)) {
            $data['fiscal_name'] = $data['name'] ?? null;
        }

        if (blank($data['fiscal_zip'] ?? null)) {
            $data['fiscal_zip'] = $data['postal_code'] ?? null;
        }

        return $data;
    }
}
