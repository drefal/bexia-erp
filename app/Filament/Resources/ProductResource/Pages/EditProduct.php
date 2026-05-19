<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;


    protected function mutateFormDataBeforeSave(array $data): array
    {
        // BEXIA_V5550E_EDIT_INTERNAL_REFERENCE_MUTATE_BEFORE_SAVE
        $reference = trim((string) ($data['internal_reference'] ?? ''));

        if ($reference === '') {
            $data['internal_reference'] = null;

            return $data;
        }

        $companyId = (int) (
            ($this->record?->company_id ?? null)
            ?: ($data['company_id'] ?? 0)
            ?: (\Filament\Facades\Filament::getTenant()?->getKey() ?: 0)
        );

        $query = \App\Models\Product::query()
            ->whereRaw('LOWER(TRIM(internal_reference)) = ?', [mb_strtolower($reference, 'UTF-8')]);

        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'company_id')) {
            if ($companyId > 0) {
                $query->where('company_id', $companyId);
            } else {
                $query->whereNull('company_id');
            }
        }

        if ($this->record?->getKey()) {
            $query->whereKeyNot($this->record->getKey());
        }

        if (! $query->exists()) {
            $data['internal_reference'] = $reference;

            return $data;
        }

        $previousReference = trim((string) (
            $this->record?->getOriginal('internal_reference')
            ?: $this->record?->internal_reference
            ?: ''
        ));

        $data['internal_reference'] = $previousReference !== '' ? $previousReference : null;

        if (property_exists($this, 'data') && is_array($this->data)) {
            $this->data['internal_reference'] = $data['internal_reference'];
        }

        $this->form->fill($data);

        $message = $previousReference !== ''
            ? 'La referencia interna ya existe en otro producto de esta empresa. Se regresó al valor anterior: ' . $previousReference . '.'
            : 'La referencia interna ya existe en otro producto de esta empresa. Se limpió el campo.';

        // BEXIA_V5550F_INTERNAL_REFERENCE_DUPLICATE_MODAL_DISPATCH_EDIT
        $this->dispatch(
            'bexia-internal-reference-duplicate-modal',
            title: 'Referencia interna duplicada',
            message: $message,
        );

        throw new \Filament\Support\Exceptions\Halt();
    }

    protected function getHeaderActions(): array
    {
        return [

            Actions\Action::make('internet_image_search_product_v1')
                ->label('Buscar imagen')
                ->icon('heroicon-m-magnifying-glass')
                ->color('gray')
                ->modalHeading('Buscar imagen en internet')
                ->modalDescription('Busca imágenes relacionadas al producto. Revisa derechos de uso antes de seleccionar una imagen.')
                ->form([
                    \Filament\Forms\Components\TextInput::make('query')
                        ->label('Texto de búsqueda')
                        ->default(fn (): string => $this->defaultInternetImageSearchQuery())
                        ->required()
                        ->maxLength(255)
                        ->helperText('Puedes buscar por nombre, descripción, marca, código o referencia.'),

                    \Filament\Forms\Components\Select::make('limit')
                        ->label('Cantidad de resultados')
                        ->options([
                            6 => '6 imágenes',
                            12 => '12 imágenes',
                            18 => '18 imágenes',
                            24 => '24 imágenes',
                        ])
                        ->default(12)
                        ->native(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $candidates = app(\App\Services\ProductImageSearchService::class)
                            ->search((string) $data['query'], (int) ($data['limit'] ?? 12));

                        $this->setInternetImageSearchCandidates($candidates, (string) $data['query']);

                        if (count($candidates) === 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se encontraron imágenes')
                                ->warning()
                                ->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Imágenes encontradas')
                            ->body('Se encontraron ' . count($candidates) . ' opciones. Usa el botón "Usar imagen sugerida".')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se pudo buscar imagen')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('apply_internet_image_candidate_v1')
                ->label('Usar imagen sugerida')
                ->icon('heroicon-m-photo')
                ->color('primary')
                ->modalHeading('Seleccionar imagen sugerida')
                ->modalDescription('Selecciona una imagen encontrada previamente. La imagen será copiada al almacenamiento de Bexia.')
                ->visible(fn (): bool => count($this->internetImageSearchCandidates()) > 0)
                ->form([
                    \Filament\Forms\Components\Placeholder::make('preview')
                        ->label('Vista previa')
                        ->content(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString($this->renderInternetImageCandidatesPreview()))
                        ->columnSpanFull(),

                    \Filament\Forms\Components\Select::make('image_url')
                        ->label('Imagen a usar')
                        ->options(fn (): array => $this->internetImageCandidateOptions())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->helperText('Elige el número que corresponde a la imagen de la vista previa.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $url = (string) ($data['image_url'] ?? '');

                        $path = app(\App\Services\ProductImageSearchService::class)
                            ->downloadToProductImage($url, (int) $this->record->id);

                        $extra = $this->productExtraAttributesArray();
                        $extra['internet_image_selected'] = [
                            'url' => $url,
                            'path' => $path,
                            'selected_at' => now()->toDateTimeString(),
                            'selected_by' => auth()->id(),
                        ];

                        $this->record->forceFill([
                            'image_path' => $path,
                            'extra_attributes' => $extra,
                        ])->saveQuietly();

                        \Filament\Notifications\Notification::make()
                            ->title('Imagen asignada')
                            ->body('La imagen fue copiada y asignada al producto.')
                            ->success()
                            ->send();

                        $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se pudo asignar la imagen')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('discard_changes_product_v1')
                ->label('Deshacer cambios')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->url(fn (): string => ProductResource::getUrl('edit', ['record' => $this->record])),

            Actions\DeleteAction::make()
                ->label('Eliminar'),
        ];
    }

    public function getSubheading(): string|HtmlString|null
    {
        $record = $this->getRecord();

        if (($record->is_variant ?? false) && $record->parentProduct) {
            $parent = $record->parentProduct;
            $parentUrl = ProductResource::getUrl('edit', ['record' => $parent]);

            $segments = [];

            $segments[] = '<a href="' . e($parentUrl) . '" class="text-primary-600 hover:text-primary-500 hover:underline font-medium">' . e($parent->name) . '</a>';

            if (! empty($record->variant_group) && ! empty($record->variant_value)) {
                $segments[] = '<span>' . e($record->variant_group . ': ' . $record->variant_value) . '</span>';
            } elseif (! empty($record->variant_name)) {
                $segments[] = '<span>' . e($record->variant_name) . '</span>';
            } else {
                $segments[] = '<span>' . e($record->name) . '</span>';
            }

            if (! empty($record->internal_reference)) {
                $segments[] = '<span>' . e($record->internal_reference) . '</span>';
            }

            return new HtmlString(
                '<nav class="flex items-center gap-1 text-sm text-gray-600">' .
                implode('<span class="text-gray-400 px-1">/</span>', $segments) .
                '</nav>'
            );
        }

        if (($record->has_variants ?? false) === true) {
            return 'Producto con variantes';
        }

        return null;
    }

    protected function afterSave(): void
    {
        // sync_product_taxes_after_save_v1
        $this->syncProductTaxRatesFromForm();

        $record = $this->record;

        if (! $record || ! ($record->is_variant ?? false) || ! $record->parent_product_id) {
            return;
        }

        $parent = Product::query()->find($record->parent_product_id);

        if (! $parent) {
            return;
        }

        $variantValue = trim((string) ($record->variant_value ?: $record->variant_name));

        if ($variantValue === '') {
            return;
        }

        $finalName = trim((string) $parent->name) . ' - ' . $variantValue;

        if ($record->name !== $finalName) {
            $record->forceFill([
                'name' => $finalName,
                'variant_name' => $record->variant_name ?: $variantValue,
            ])->saveQuietly();
        }
    }


    protected function getCancelFormAction(): \Filament\Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Deshacer cambios')
            ->icon('heroicon-m-arrow-uturn-left');
    }


    protected function syncProductTaxRatesFromForm(): void
    {
        $record = $this->record ?? null;

        if (! $record || ! $record->exists || ! \Illuminate\Support\Facades\Schema::hasTable('product_tax_rates')) {
            return;
        }

        $state = [];

        try {
            $rawState = $this->form->getRawState();

            if ($rawState instanceof \Illuminate\Support\Collection) {
                $state = $rawState->toArray();
            } elseif (is_array($rawState)) {
                $state = $rawState;
            }
        } catch (\Throwable $e) {
            $state = [];
        }

        if (property_exists($this, 'data') && is_array($this->data)) {
            $state = array_replace_recursive($state, $this->data);
        }

        $this->syncProductTaxRateUsage('sale', $state['sale_tax_rate_ids'] ?? []);
        $this->syncProductTaxRateUsage('purchase', $state['purchase_tax_rate_ids'] ?? []);
    }

    protected function syncProductTaxRateUsage(string $usageType, mixed $taxRateIds): void
    {
        $record = $this->record ?? null;

        if (! $record || ! $record->exists) {
            return;
        }

        if (! is_array($taxRateIds)) {
            $taxRateIds = filled($taxRateIds) ? [$taxRateIds] : [];
        }

        $taxRateIds = collect($taxRateIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        \Illuminate\Support\Facades\DB::table('product_tax_rates')
            ->where('product_id', $record->id)
            ->where('usage_type', $usageType)
            ->delete();

        foreach ($taxRateIds as $taxRateId) {
            \Illuminate\Support\Facades\DB::table('product_tax_rates')->insert([
                'company_id' => $record->company_id,
                'product_id' => $record->id,
                'tax_rate_id' => $taxRateId,
                'usage_type' => $usageType,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }


    protected function defaultInternetImageSearchQuery(): string
    {
        $parts = [
            $this->record?->name,
            $this->record?->internal_reference,
            $this->record?->description,
        ];

        return collect($parts)
            ->filter()
            ->map(fn ($value) => trim(strip_tags((string) $value)))
            ->filter()
            ->implode(' ');
    }

    protected function productExtraAttributesArray(): array
    {
        $extra = $this->record?->extra_attributes ?? [];

        if (is_string($extra)) {
            $decoded = json_decode($extra, true);
            $extra = is_array($decoded) ? $decoded : [];
        }

        return is_array($extra) ? $extra : [];
    }

    protected function setInternetImageSearchCandidates(array $candidates, string $query): void
    {
        $extra = $this->productExtraAttributesArray();

        $extra['internet_image_search'] = [
            'query' => $query,
            'searched_at' => now()->toDateTimeString(),
            'searched_by' => auth()->id(),
            'candidates' => array_values($candidates),
        ];

        $this->record->forceFill([
            'extra_attributes' => $extra,
        ])->saveQuietly();
    }

    protected function internetImageSearchCandidates(): array
    {
        $extra = $this->productExtraAttributesArray();

        $candidates = $extra['internet_image_search']['candidates'] ?? [];

        return is_array($candidates) ? $candidates : [];
    }

    protected function internetImageCandidateOptions(): array
    {
        return collect($this->internetImageSearchCandidates())
            ->mapWithKeys(function (array $candidate, int $index): array {
                $number = $index + 1;
                $title = \Illuminate\Support\Str::limit((string) ($candidate['title'] ?? 'Imagen sugerida'), 80);
                $source = parse_url((string) ($candidate['source'] ?? $candidate['link'] ?? $candidate['url'] ?? ''), PHP_URL_HOST);

                return [
                    (string) ($candidate['url'] ?? '') => '#' . $number . ' - ' . $title . ($source ? ' | ' . $source : ''),
                ];
            })
            ->filter(fn ($label, $url): bool => filled($url))
            ->all();
    }

    protected function renderInternetImageCandidatesPreview(): string
    {
        $candidates = $this->internetImageSearchCandidates();

        if ($candidates === []) {
            return '<div class="text-sm text-gray-500">No hay imágenes sugeridas. Primero ejecuta Buscar imagen.</div>';
        }

        $html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">';

        foreach ($candidates as $index => $candidate) {
            $number = $index + 1;
            $thumb = e((string) ($candidate['thumbnail'] ?? $candidate['url'] ?? ''));
            $url = e((string) ($candidate['url'] ?? ''));
            $title = e(\Illuminate\Support\Str::limit((string) ($candidate['title'] ?? 'Imagen sugerida'), 70));
            $source = e((string) ($candidate['source'] ?? ''));

            $html .= '<div style="border:1px solid #e5e7eb;border-radius:12px;padding:10px;background:#fff;">';
            $html .= '<div style="font-weight:700;margin-bottom:6px;">#' . $number . '</div>';

            if ($thumb !== '') {
                $html .= '<img src="' . $thumb . '" style="width:100%;height:120px;object-fit:contain;border-radius:8px;background:#f8fafc;" loading="lazy" />';
            }

            $html .= '<div style="font-size:12px;margin-top:8px;line-height:1.3;">' . $title . '</div>';

            if ($source !== '') {
                $html .= '<div style="font-size:11px;color:#64748b;margin-top:4px;">' . $source . '</div>';
            }

            if ($url !== '') {
                $html .= '<a href="' . $url . '" target="_blank" style="font-size:12px;text-decoration:underline;margin-top:6px;display:inline-block;">Abrir imagen</a>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<div style="font-size:12px;color:#64748b;margin-top:12px;">Las imágenes encontradas pueden estar sujetas a derechos de autor. Verifica que tengas derecho de uso antes de seleccionarlas.</div>';

        return $html;
    }

    public function getHeading(): string
    {
        $name = trim((string) ($this->record?->name ?? ''));

        if ($name === '') {
            return 'Editar producto';
        }

        return 'Editar producto: ' . $name;
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }


}
