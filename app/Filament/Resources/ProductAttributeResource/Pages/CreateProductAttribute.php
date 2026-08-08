<?php

namespace App\Filament\Resources\ProductAttributeResource\Pages;

use App\Filament\Resources\ProductAttributeResource;
use App\Models\ProductAttribute;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProductAttribute extends CreateRecord
{
    protected static string $resource = ProductAttributeResource::class;

    /*
     * BEXIA_V5_83_P14B_DUPLICATE_ATTRIBUTE_MODAL
     *
     * Acción oculta que se monta programáticamente cuando se detecta
     * un atributo duplicado. Evita un fallo silencioso y explica al
     * usuario por qué no se creó el registro.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('duplicateAttributeWarning')
                ->label('Aviso de atributo duplicado')
                ->extraAttributes([
                    'class' => 'hidden',
                ])
                ->modalHeading('Atributo ya existente')
                ->modalDescription(function (array $arguments): string {
                    $name = trim((string) ($arguments['name'] ?? ''));

                    return $name !== ''
                        ? 'No se creó el atributo "' . $name . '" porque ya existe uno con el mismo nombre en esta empresa.'
                        : 'No se creó el atributo porque ya existe uno con el mismo nombre en esta empresa.';
                })
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Entendido')
                ->action(fn () => null),
        ];
    }

    protected function showDuplicateAttributeModal(string $name): void
    {
        $this->mountAction(
            'duplicateAttributeWarning',
            ['name' => $name],
        );

        $this->halt();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $companyId = Filament::getTenant()?->getKey();
        $name = trim((string) ($data['name'] ?? ''));

        if (! $companyId) {
            throw ValidationException::withMessages([
                'name' => 'No fue posible identificar la empresa activa.',
            ]);
        }

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Captura el nombre del atributo.',
            ]);
        }

        $normalizedName = mb_strtolower($name, 'UTF-8');

        $nameExists = ProductAttribute::query()
            ->where('company_id', $companyId)
            ->whereRaw(
                'LOWER(TRIM(name)) = ?',
                [$normalizedName]
            )
            ->exists();

        if ($nameExists) {
            $this->showDuplicateAttributeModal($name);
        }

        $code = Str::of($name)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->toString();

        if ($code === '') {
            throw ValidationException::withMessages([
                'name' => 'No fue posible generar un código válido con este nombre.',
            ]);
        }

        $codeExists = ProductAttribute::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();

        if ($codeExists) {
            $this->showDuplicateAttributeModal($name);
        }

        $data['company_id'] = $companyId;
        $data['name'] = $name;
        $data['code'] = $code;
        $data['sort_order'] = 0;
        $data['is_variant'] = true;
        $data['is_active'] = true;
        $data['is_system'] = false;

        return $data;
    }
}
