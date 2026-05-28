<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\Company;
use App\Models\CompanyGroup;
use App\Support\Sat\SatConstanciaCompanyMapper;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_from_sat_constancia')
                ->label('Crear desde Constancia SAT')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->visible(fn (): bool => CompanyResource::canCreate())
                ->form([
                    FileUpload::make('constancia_sat')
                        ->label('Constancia de situación fiscal PDF')
                        ->disk('local')
                        ->directory('companies/sat-constancias')
                        ->acceptedFileTypes(['application/pdf'])
                        ->preserveFilenames()
                        ->required()
                        ->helperText('Sube la Constancia de Situación Fiscal emitida por el SAT.'),

                    Select::make('company_group_id')
                        ->label('Grupo de empresas')
                        ->options(fn (): array => CompanyGroup::query()
                            ->where('active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Toggle::make('active')
                        ->label('Empresa activa')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $mapper = app(SatConstanciaCompanyMapper::class);
                    $storedPath = $mapper->normalizeStoredPath($data['constancia_sat'] ?? null);

                    if (! $storedPath) {
                        Notification::make()
                            ->title('No se recibió el PDF')
                            ->danger()
                            ->send();

                        return;
                    }

                    $attributes = $mapper->attributesFromStoredPath(
                        $storedPath,
                        filled($data['company_group_id'] ?? null) ? (int) $data['company_group_id'] : null,
                    );

                    if (! $mapper->requiredDataIsPresent($attributes)) {
                        Notification::make()
                            ->title('No se pudo leer toda la información fiscal')
                            ->body('Revisa que el PDF corresponda a una Constancia de Situación Fiscal válida.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if (Company::query()->where('tax_id', $attributes['tax_id'])->exists()) {
                        Notification::make()
                            ->title('Ya existe una empresa con ese RFC')
                            ->body('Usa la acción Actualizar desde Constancia SAT dentro de la empresa existente.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $attributes['active'] = (bool) ($data['active'] ?? true);
                    $attributes['slug'] = $mapper->uniqueSlug($attributes['business_name'] ?: $attributes['name']);

                    $company = Company::query()->create($attributes);

                    Notification::make()
                        ->title('Empresa creada desde Constancia SAT')
                        ->body($company->name . ' / RFC: ' . $company->tax_id)
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Nueva empresa'),
        ];
    }
}
