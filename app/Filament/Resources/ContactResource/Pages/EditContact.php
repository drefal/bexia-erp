<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Models\Contact;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\ContactResource\Pages\Concerns\ImportsSatConstancia;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class EditContact extends EditRecord
{
    use ImportsSatConstancia;

    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previous_contact')
                ->label('Anterior')
                ->icon('heroicon-o-chevron-left')
                ->color('gray')
                ->url(fn (): ?string => $this->adjacentContactUrl('previous'))
                ->disabled(fn (): bool => blank($this->adjacentContactUrl('previous'))),

            Action::make('next_contact')
                ->label('Siguiente')
                ->icon('heroicon-o-chevron-right')
                ->iconPosition('after')
                ->color('gray')
                ->url(fn (): ?string => $this->adjacentContactUrl('next'))
                ->disabled(fn (): bool => blank($this->adjacentContactUrl('next'))),

            $this->viewSatConstanciaAction(),
            $this->downloadSatConstanciaAction(),
            $this->importSatConstanciaAction(),

            Actions\RestoreAction::make()
                ->label('Desarchivar')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->modalHeading('Desarchivar contacto')
                ->modalDescription('El contacto volverá a estar activo y visible en la lista principal.')
                ->modalSubmitActionLabel('Desarchivar')
                ->successNotificationTitle('Contacto desarchivado')
                ->visible(fn (): bool => method_exists($this->record, 'trashed') && $this->record->trashed()),

            Actions\DeleteAction::make()
                ->label('Archivar')
                ->icon('heroicon-o-archive-box')
                ->modalHeading('Archivar contacto')
                ->modalDescription('El contacto no se eliminará físicamente; quedará archivado para conservar historial.')
                ->modalSubmitActionLabel('Archivar')
                ->successNotificationTitle('Contacto archivado')
                ->visible(fn (): bool => ! method_exists($this->record, 'trashed') || ! $this->record->trashed()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data);
        $record->save();

        return $record;
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Guardar cambios');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Deshacer cambios');
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

    protected function adjacentContactUrl(string $direction): ?string
    {
        $record = $this->record;

        if (! $record) {
            return null;
        }

        $adjacentId = $this->adjacentContactId($direction);

        if (! $adjacentId) {
            return null;
        }

        $params = [
            'record' => $adjacentId,
        ];

        $search = $this->contactNavigationSearch();

        if (filled($search)) {
            $params['contact_nav_search'] = $search;
        }

        return static::getResource()::getUrl('edit', $params);
    }



    protected function adjacentContactId(string $direction): ?int
    {
        $record = $this->record;

        if (! $record) {
            return null;
        }

        $name = trim((string) ($record->name ?? ''));
        $id = (int) $record->getKey();
        $isArchived = method_exists($record, 'trashed') && $record->trashed();

        $query = Contact::query()
            ->withoutGlobalScopes()
            ->whereNull('parent_contact_id')
            ->where('address_type', 'main');

        if ($record->company_id) {
            $query->where('company_id', $record->company_id);
        } else {
            $query->whereNull('company_id');
        }

        if (Schema::hasColumn('contacts', 'deleted_at')) {
            if ($isArchived) {
                $query->whereNotNull('deleted_at');
            } else {
                $query->whereNull('deleted_at');
            }
        }

        $search = $this->contactNavigationSearch();

        if (filled($search)) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';

            $query->where(function ($query) use ($like): void {
                $query
                    ->where('name', 'ilike', $like)
                    ->orWhere('rfc', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('commercial_name', 'ilike', $like)
                    ->orWhere('fiscal_zip', 'ilike', $like);
            });
        }

        if ($direction === 'previous') {
            $query
                ->where(function ($query) use ($name, $id): void {
                    $query
                        ->where('name', '<', $name)
                        ->orWhere(function ($query) use ($name, $id): void {
                            $query
                                ->where('name', $name)
                                ->where('id', '<', $id);
                        });
                })
                ->orderByDesc('name')
                ->orderByDesc('id');
        } else {
            $query
                ->where(function ($query) use ($name, $id): void {
                    $query
                        ->where('name', '>', $name)
                        ->orWhere(function ($query) use ($name, $id): void {
                            $query
                                ->where('name', $name)
                                ->where('id', '>', $id);
                        });
                })
                ->orderBy('name')
                ->orderBy('id');
        }

        $adjacent = $query->first(['id']);

        return $adjacent ? (int) $adjacent->id : null;
    }





    protected function contactNavigationSearch(): ?string
    {
        // 1) Si ya viene en la URL del editor, usarlo.
        $search = request()->query('contact_nav_search');

        // 2) Si Filament dejó tableSearch en la URL actual.
        if (! filled($search)) {
            $search = request()->query('tableSearch');
        }

        // 3) Si entramos desde la lista, normalmente la búsqueda viene en el referer:
        // /contacts?tableSearch=rit
        if (! filled($search)) {
            $referer = (string) request()->headers->get('referer');

            if ($referer !== '') {
                $query = parse_url($referer, PHP_URL_QUERY);

                if (is_string($query) && $query !== '') {
                    parse_str($query, $params);

                    $search = $params['contact_nav_search']
                        ?? $params['tableSearch']
                        ?? null;
                }
            }
        }

        $search = trim((string) $search);

        return $search !== '' ? $search : null;
    }




}
