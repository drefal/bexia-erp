<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Filament\Resources\ContactResource\RelationManagers\ChildContactsRelationManager;
use App\Models\Contact;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class ContactResource extends Resource
{
    public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->can('contacts.view') ?? false;
}

public static function canViewAny(): bool
{
    return auth()->user()?->can('contacts.view') ?? false;
}
    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Contactos';
    protected static ?string $navigationLabel = 'Contactos';
    protected static ?string $modelLabel = 'Contacto';
    protected static ?string $pluralModelLabel = 'Contactos';
    protected static ?int $navigationSort = 10;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            return (int) $tenant->getKey();
        }

        $user = Filament::auth()->user();

        return $user && isset($user->company_id)
            ? (int) $user->company_id
            : null;
    }


    protected static function contactBillingCatalogOptions(string $catalogKey, int $limit = 500): array
    {
        if (! Schema::hasTable('sat_billing_catalog_items')) {
            return [];
        }

        $query = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', $catalogKey);

        $hasActive = Schema::hasColumn('sat_billing_catalog_items', 'is_active');

        if ($hasActive) {
            $activeCount = (clone $query)
                ->where('is_active', true)
                ->count();

            if ($activeCount > 0) {
                $query->where('is_active', true);
            }
        }

        return $query
            ->orderBy('code')
            ->limit($limit)
            ->get(['code', 'name', 'description'])
            ->mapWithKeys(function ($item): array {
                $code = trim((string) $item->code);
                $label = trim((string) ($item->name ?: $item->description));

                if ($label === '' || $label === $code) {
                    $label = $code;
                } else {
                    $label = $code . ' - ' . $label;
                }

                return [$code => $label];
            })
            ->all();
    }



    protected static function contactUserOptions(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $query = DB::table('users');

        if (Schema::hasColumn('users', 'name')) {
            $query->orderBy('name');
        }

        return $query
            ->limit(500)
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(function ($user): array {
                $name = trim((string) ($user->name ?? ''));
                $email = trim((string) ($user->email ?? ''));

                $label = $name !== '' ? $name : $email;

                if ($name !== '' && $email !== '') {
                    $label = $name . ' <' . $email . '>';
                }

                return [(int) $user->id => $label ?: ('Usuario #' . $user->id)];
            })
            ->all();
    }


    protected static function contactCfdiUseOptions(): array
    {
        $options = static::contactBillingCatalogOptions('uso_c_f_d_i');

        if ($options === []) {
            $options = static::contactBillingCatalogOptions('uso_cfdi');
        }

        return $options;
    }

    protected static function contactPaymentTermOptions(): array
    {
        if (! Schema::hasTable('payment_terms')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        return DB::table('payment_terms')
            ->where('is_active', true)
            ->where(function ($query) use ($companyId): void {
                $query->whereNull('company_id');

                if ($companyId) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->orderBy('days')
            ->orderBy('name')
            ->get(['id', 'name', 'days'])
            ->mapWithKeys(function ($term): array {
                $label = trim((string) $term->name);

                if ((int) $term->days > 0 && ! str_contains(strtolower($label), 'día')) {
                    $label .= ' (' . (int) $term->days . ' días)';
                }

                return [(int) $term->id => $label];
            })
            ->all();
    }

    public static function getEloquentQuery(): Builder
    {
        // Se quita el scope de SoftDeletes para que el filtro Archivados
        // pueda mostrar activos, archivados o ambos.
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        // Lista principal: solo contactos padre principales.
        // Direcciones/contactos hijos se ven dentro del contacto padre.
        $query
            ->whereNull('parent_contact_id')
            ->where('address_type', 'main');

        return $query;
    }

    protected static function satItemsTableExists(): bool
    {
        return Schema::hasTable('sat_billing_catalog_items');
    }

    protected static function satExtraColumn(): ?string
    {
        if (! static::satItemsTableExists()) {
            return null;
        }

        foreach (['extra_attributes', 'extra_data', 'metadata', 'additional_data', 'meta'] as $col) {
            if (Schema::hasColumn('sat_billing_catalog_items', $col)) {
                return $col;
            }
        }

        return null;
    }

    protected static function satCatalogKeys(string $catalogKey): array
    {
        return match ($catalogKey) {
            'uso_cfdi' => ['uso_cfdi', 'uso_c_f_d_i'],
            default => [$catalogKey],
        };
    }

    protected static function satCatalogOptions(string $catalogKey, ?string $search = null, int $limit = 80): array
    {
        if (! static::satItemsTableExists()) {
            return [];
        }

        $keys = static::satCatalogKeys($catalogKey);
        $search = trim((string) $search);

        $query = DB::table('sat_billing_catalog_items')
            ->whereIn('catalog_key', $keys)
            ->where('is_active', true)
            ->select(['code', 'name', 'description']);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $prefix = $search . '%';

            $query->where(function ($query) use ($search, $like, $prefix): void {
                $query
                    ->where('code', $search)
                    ->orWhere('code', 'like', $prefix)
                    ->orWhere('name', 'ilike', $like)
                    ->orWhere('description', 'ilike', $like);
            });

            $query->orderByRaw(
                "CASE WHEN code = ? THEN 0 WHEN code LIKE ? THEN 1 ELSE 2 END",
                [$search, $prefix]
            );
        }

        return $query
            ->orderBy('code')
            ->limit($limit)
            ->get()
            ->mapWithKeys(function ($row): array {
                $label = trim((string) $row->code . ' - ' . (string) ($row->name ?: $row->description));

                return [(string) $row->code => $label];
            })
            ->all();
    }

    protected static function satCatalogLabel(string $catalogKey, $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || ! static::satItemsTableExists()) {
            return $value ?: null;
        }

        $row = DB::table('sat_billing_catalog_items')
            ->whereIn('catalog_key', static::satCatalogKeys($catalogKey))
            ->where('code', $value)
            ->first(['code', 'name', 'description']);

        if (! $row) {
            return $value;
        }

        return trim((string) $row->code . ' - ' . (string) ($row->name ?: $row->description));
    }

    protected static function parentContactOptions(?string $search = null): array
    {
        $companyId = static::currentCompanyId();
        $search = trim((string) $search);

        return Contact::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('contact_type', 'company')
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

                $query->where(function ($query) use ($like): void {
                    $query
                        ->where('name', 'ilike', $like)
                        ->orWhere('commercial_name', 'ilike', $like)
                        ->orWhere('rfc', 'ilike', $like);
                });
            })
            ->orderBy('name')
            ->limit(80)
            ->get()
            ->mapWithKeys(fn (Contact $contact): array => [
                $contact->id => trim($contact->name . ($contact->rfc ? ' / ' . $contact->rfc : '')),
            ])
            ->all();
    }

    protected static function parentContactLabel($value): ?string
    {
        if (! $value) {
            return null;
        }

        $contact = Contact::find($value);

        if (! $contact) {
            return null;
        }

        return trim($contact->name . ($contact->rfc ? ' / ' . $contact->rfc : ''));
    }

    protected static function decodeExtra(mixed $extra): array
    {
        if (is_array($extra)) {
            return $extra;
        }

        if (is_object($extra)) {
            return (array) $extra;
        }

        if (is_string($extra) && trim($extra) !== '') {
            $decoded = json_decode($extra, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected static function arrayValueByAliases(array $data, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $data) && filled($data[$alias])) {
                return trim((string) $data[$alias]);
            }
        }

        return null;
    }

    protected static function findPostalCodeRow(?string $postalCode): ?object
    {
        $postalCode = trim((string) $postalCode);

        if ($postalCode === '' || ! static::satItemsTableExists()) {
            return null;
        }

        $select = ['code', 'name', 'description'];
        $extraCol = static::satExtraColumn();

        if ($extraCol) {
            $select[] = $extraCol;
        }

        return DB::table('sat_billing_catalog_items')
            ->where('catalog_key', 'codigo_postal')
            ->where('is_active', true)
            ->where('code', $postalCode)
            ->select($select)
            ->first();
    }



    protected static function normalizeSatExtraKey(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $value);
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value);

        return trim($value, '_');
    }

    protected static function codeFromPostalDescription(?string $description, string $label): ?string
    {
        $description = (string) $description;

        if ($description === '') {
            return null;
        }

        $pattern = '/'.$label.'\s+([^\/]+)/iu';

        if (preg_match($pattern, $description, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }

        return null;
    }

    protected static function mexicoStateNameByCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return [
            'AGU' => 'Aguascalientes',
            'BCN' => 'Baja California',
            'BCS' => 'Baja California Sur',
            'CAM' => 'Campeche',
            'CHP' => 'Chiapas',
            'CHH' => 'Chihuahua',
            'CMX' => 'Ciudad de México',
            'COA' => 'Coahuila',
            'COL' => 'Colima',
            'DUR' => 'Durango',
            'GUA' => 'Guanajuato',
            'GRO' => 'Guerrero',
            'HID' => 'Hidalgo',
            'JAL' => 'Jalisco',
            'MEX' => 'México',
            'MIC' => 'Michoacán',
            'MOR' => 'Morelos',
            'NAY' => 'Nayarit',
            'NLE' => 'Nuevo León',
            'OAX' => 'Oaxaca',
            'PUE' => 'Puebla',
            'QUE' => 'Querétaro',
            'ROO' => 'Quintana Roo',
            'SLP' => 'San Luis Potosí',
            'SIN' => 'Sinaloa',
            'SON' => 'Sonora',
            'TAB' => 'Tabasco',
            'TAM' => 'Tamaulipas',
            'TLA' => 'Tlaxcala',
            'VER' => 'Veracruz',
            'YUC' => 'Yucatán',
            'ZAC' => 'Zacatecas',
        ][$code] ?? null;
    }

    protected static function valueFromExtraAttributes(mixed $extra, array $aliases): ?string
    {
        $data = static::decodeExtra($extra);

        $headers = $data['headers'] ?? [];
        $values = $data['values'] ?? [];

        if (is_array($headers) && is_array($values)) {
            foreach ($headers as $column => $header) {
                $normalizedHeader = static::normalizeSatExtraKey((string) $header);

                foreach ($aliases as $alias) {
                    $normalizedAlias = static::normalizeSatExtraKey((string) $alias);

                    if (str_contains($normalizedHeader, $normalizedAlias)) {
                        $value = $values[$column] ?? null;

                        if (filled($value)) {
                            return trim((string) $value);
                        }
                    }
                }
            }
        }

        return null;
    }

    protected static function satItemNameByCode(string $catalogKey, ?string $code, ?string $relatedCode = null): ?string
    {
        $code = trim((string) $code);

        if ($code === '' || ! static::satItemsTableExists()) {
            return null;
        }

        $query = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', $catalogKey)
            ->where('code', $code)
            ->where('is_active', true);

        $extraCol = static::satExtraColumn();

        if ($relatedCode && $extraCol) {
            $query->orderByRaw(
                "CASE WHEN {$extraCol}::text ILIKE ? THEN 0 ELSE 1 END",
                ['%' . $relatedCode . '%']
            );
        }

        $row = $query->first(['code', 'name', 'description']);

        if (! $row) {
            return null;
        }

        $name = trim((string) ($row->name ?: $row->description));

        return $name !== '' ? $name : $code;
    }



    protected static function valueFromSatItem(object $row, array $aliases): ?string
    {
        $extraCol = static::satExtraColumn();
        $extra = $extraCol ? static::decodeExtra($row->{$extraCol} ?? null) : [];

        $headers = $extra['headers'] ?? [];
        $values = $extra['values'] ?? [];

        if (is_array($headers) && is_array($values)) {
            foreach ($headers as $column => $header) {
                $normalizedHeader = static::normalizeSatExtraKey((string) $header);

                foreach ($aliases as $alias) {
                    $normalizedAlias = static::normalizeSatExtraKey((string) $alias);

                    if (
                        $normalizedHeader === $normalizedAlias ||
                        str_contains($normalizedHeader, $normalizedAlias)
                    ) {
                        $value = $values[$column] ?? null;

                        if (filled($value)) {
                            return trim((string) $value);
                        }
                    }
                }
            }
        }

        return null;
    }





    protected static function satExtraValuesFromRow(object $row): array
    {
        $extra = json_decode((string) ($row->extra_attributes ?? ''), true);

        if (! is_array($extra)) {
            return [];
        }

        $values = $extra['values'] ?? [];

        return is_array($values) ? $values : [];
    }


    protected static function satPostalCodeOptions(?string $search = null, int $limit = 80): array
    {
        if (! Schema::hasTable('sat_billing_catalog_items')) {
            return [];
        }

        $search = preg_replace('/\D+/', '', trim((string) $search));

        if ($search === '') {
            return [];
        }

        $limit = max(1, min($limit, 30));
        $prefix = $search . '%';

        return DB::table('sat_billing_catalog_items')
            ->where('catalog_key', 'codigo_postal')
            ->where('is_active', true)
            ->where('code', 'like', $prefix)
            ->orderBy('code')
            ->limit($limit)
            ->pluck('code', 'code')
            ->all();
    }


    protected static function satPostalCodeLabel($value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected static function satItemNameByCodeV3(string $catalogKey, ?string $code, ?string $stateCode = null): ?string
    {
        $code = trim((string) $code);

        if ($code === '' || ! static::satItemsTableExists()) {
            return null;
        }

        $select = ['code', 'name', 'description'];
        $extraCol = static::satExtraColumn();

        if ($extraCol) {
            $select[] = $extraCol;
        }

        $query = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', $catalogKey)
            ->where('code', $code)
            ->where('is_active', true);

        if ($stateCode && $extraCol) {
            $query->orderByRaw(
                "CASE WHEN {$extraCol}::text ILIKE ? THEN 0 ELSE 1 END",
                ['%' . $stateCode . '%']
            );
        }

        $row = $query->first($select);

        if (! $row) {
            return null;
        }

        $fromExtra = static::valueFromSatItem($row, [
            'descripcion',
            'descripción',
            'nombre',
            'nombre estado',
            'nombre municipio',
            'nombre localidad',
            'estado',
            'municipio',
            'localidad',
        ]);

        if (filled($fromExtra) && $fromExtra !== $code) {
            return $fromExtra;
        }

        $name = trim((string) ($row->name ?: $row->description));

        if ($name !== '' && $name !== $code) {
            return $name;
        }

        return null;
    }



    protected static function coloniaLabelFromRow(object $row): string
    {
        $label = static::satExtraValueByColumn($row, 'C');

        if (filled($label)) {
            return trim((string) $label);
        }

        $label = static::satRowDescriptionName($row);

        if (filled($label)) {
            return trim((string) $label);
        }

        return trim((string) ($row->name ?: $row->description ?: $row->code));
    }

    protected static function satRowExtraValue(object $row, array $exactAliases = [], array $containsAliases = []): ?string
    {
        $extraCol = static::satExtraColumn();

        if (! $extraCol) {
            return null;
        }

        $extra = static::decodeExtra($row->{$extraCol} ?? null);

        $headers = $extra['headers'] ?? [];
        $values = $extra['values'] ?? [];

        if (! is_array($headers) || ! is_array($values)) {
            return null;
        }

        $normalizedExact = collect($exactAliases)
            ->map(fn ($alias) => static::normalizeSatExtraKey((string) $alias))
            ->filter()
            ->values()
            ->all();

        $normalizedContains = collect($containsAliases)
            ->map(fn ($alias) => static::normalizeSatExtraKey((string) $alias))
            ->filter()
            ->values()
            ->all();

        foreach ($headers as $column => $header) {
            $normalizedHeader = static::normalizeSatExtraKey((string) $header);

            if (in_array($normalizedHeader, $normalizedExact, true)) {
                $value = $values[$column] ?? null;

                if (filled($value)) {
                    return trim((string) $value);
                }
            }
        }

        foreach ($headers as $column => $header) {
            $normalizedHeader = static::normalizeSatExtraKey((string) $header);

            foreach ($normalizedContains as $alias) {
                if (str_contains($normalizedHeader, $alias)) {
                    $value = $values[$column] ?? null;

                    if (filled($value)) {
                        return trim((string) $value);
                    }
                }
            }
        }

        return null;
    }


    protected static function satExtraValueByColumn(object $row, string $column): ?string
    {
        $extraCol = static::satExtraColumn();

        if (! $extraCol) {
            return null;
        }

        $extra = static::decodeExtra($row->{$extraCol} ?? null);
        $values = $extra['values'] ?? [];

        if (! is_array($values)) {
            return null;
        }

        $value = $values[$column] ?? null;

        if (! filled($value)) {
            return null;
        }

        return trim((string) $value);
    }

    protected static function satBestDescriptionFromValues(object $row): ?string
    {
        $code = trim((string) ($row->code ?? ''));

        foreach (['C', 'D', 'B'] as $column) {
            $value = static::satExtraValueByColumn($row, $column);

            if (! filled($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === $code) {
                continue;
            }

            if (preg_match('/^\d+$/', $value) && $value !== $code) {
                continue;
            }

            return $value;
        }

        return null;
    }


    protected static function satRowDescriptionName(object $row): ?string
    {
        $fromValues = static::satBestDescriptionFromValues($row);

        if (filled($fromValues)) {
            return trim((string) $fromValues);
        }

        $value = static::satRowExtraValue(
            $row,
            [
                'Descripción',
                'Descripcion',
                'Nombre',
                'Nombre del asentamiento',
                'Nombre asentamiento',
                'Asentamiento',
            ],
            [
                'descripcion',
                'nombre',
                'asentamiento',
            ]
        );

        if (filled($value)) {
            return trim((string) $value);
        }

        foreach (['name', 'description'] as $field) {
            $candidate = trim((string) ($row->{$field} ?? ''));

            if ($candidate !== '' && $candidate !== trim((string) ($row->code ?? ''))) {
                return $candidate;
            }
        }

        return null;
    }

    protected static function satRowHasRelatedCode(object $row, array $aliases, ?string $code): bool
    {
        $code = trim((string) $code);

        if ($code === '') {
            return false;
        }

        $value = static::satRowExtraValue($row, $aliases, $aliases);

        return trim((string) $value) === $code;
    }


    protected static function satItemNameByCodeV4(string $catalogKey, ?string $code, ?string $stateCode = null): ?string
    {
        $code = trim((string) $code);

        if ($code === '' || ! static::satItemsTableExists()) {
            return null;
        }

        $select = ['code', 'name', 'description'];
        $extraCol = static::satExtraColumn();

        if ($extraCol) {
            $select[] = $extraCol;
        }

        $rows = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', $catalogKey)
            ->where('code', $code)
            ->where('is_active', true)
            ->limit(100)
            ->get($select);

        if ($rows->isEmpty()) {
            return null;
        }

        if ($stateCode) {
            $matched = $rows->first(function ($row) use ($stateCode): bool {
                return static::satRowHasRelatedCode($row, ['c_Estado', 'Estado'], $stateCode);
            });

            if ($matched) {
                $name = static::satRowDescriptionName($matched);

                if (filled($name) && $name !== $code) {
                    return $name;
                }
            }
        }

        foreach ($rows as $row) {
            $name = static::satRowDescriptionName($row);

            if (filled($name) && $name !== $code) {
                return $name;
            }
        }

        return null;
    }


    protected static function satItemNameByCodeV6(string $catalogKey, ?string $code, ?string $stateCode = null): ?string
    {
        $code = trim((string) $code);
        $stateCode = strtoupper(trim((string) $stateCode));

        if ($code === '' || ! static::satItemsTableExists()) {
            return null;
        }

        $select = ['code', 'name', 'description'];
        $extraCol = static::satExtraColumn();

        if ($extraCol) {
            $select[] = $extraCol;
        }

        $baseQuery = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', $catalogKey)
            ->where('code', $code)
            ->where('is_active', true);

        $rows = collect();

        // Primero: buscar coincidencia exacta por código + estado dentro de extra_attributes.
        if ($stateCode !== '' && $extraCol) {
            $rows = (clone $baseQuery)
                ->whereRaw($extraCol . "::text ILIKE ?", ['%' . $stateCode . '%'])
                ->limit(100)
                ->get($select);
        }

        // Segundo: si no hubo coincidencia por estado, usar solo el código.
        if ($rows->isEmpty()) {
            $rows = $baseQuery
                ->limit(100)
                ->get($select);
        }

        if ($rows->isEmpty()) {
            return null;
        }

        foreach ($rows as $row) {
            $name = null;

            if (method_exists(static::class, 'satBestDescriptionFromValues')) {
                $name = static::satBestDescriptionFromValues($row);
            }

            if (! filled($name) && method_exists(static::class, 'satRowDescriptionName')) {
                $name = static::satRowDescriptionName($row);
            }

            if (! filled($name)) {
                $name = trim((string) ($row->name ?: $row->description));
            }

            $name = trim((string) $name);

            if ($name !== '' && $name !== $code && ! preg_match('/^\d+$/', $name)) {
                return $name;
            }
        }

        return null;
    }




    protected static function satNameFromCatalogByCode(string $catalogKey, ?string $code, ?string $stateCode = null): ?string
    {
        $code = trim((string) $code);
        $stateCode = strtoupper(trim((string) $stateCode));

        if ($code === '' || ! Schema::hasTable('sat_billing_catalog_items')) {
            return null;
        }

        $query = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', $catalogKey)
            ->where('code', $code)
            ->where('is_active', true);

        if ($stateCode !== '') {
            $query->whereRaw('extra_attributes::text ilike ?', ['%' . $stateCode . '%']);
        }

        $rows = $query
            ->limit(50)
            ->get(['code', 'name', 'description', 'extra_attributes']);

        if ($rows->isEmpty() && $stateCode !== '') {
            $rows = DB::table('sat_billing_catalog_items')
                ->where('catalog_key', $catalogKey)
                ->where('code', $code)
                ->where('is_active', true)
                ->limit(50)
                ->get(['code', 'name', 'description', 'extra_attributes']);
        }

        foreach ($rows as $row) {
            $values = static::satExtraValuesFromRow($row);

            foreach (['C', 'D', 'B', 'A'] as $column) {
                $candidate = trim((string) ($values[$column] ?? ''));

                if ($candidate === '') {
                    continue;
                }

                if ($candidate === $code) {
                    continue;
                }

                if ($stateCode !== '' && strtoupper($candidate) === $stateCode) {
                    continue;
                }

                if (preg_match('/^\d+$/', $candidate)) {
                    continue;
                }

                return $candidate;
            }

            $candidate = trim((string) ($row->name ?: $row->description));

            if ($candidate !== '' && $candidate !== $code && ! preg_match('/^\d+$/', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }


    protected static function applyPostalCodeData(Forms\Set $set, ?string $postalCode): void
    {
        $postalCode = trim((string) $postalCode);

        // Siempre limpiar primero para que al cambiar de CP no queden datos anteriores.
        $set('neighborhood', null);
        $set('locality', null);
        $set('municipality', null);
        $set('state', null);
        $set('city', null);
        $set('country', 'México');

        if ($postalCode === '' || ! Schema::hasTable('sat_billing_catalog_items')) {
            return;
        }

        $row = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', 'codigo_postal')
            ->where('code', $postalCode)
            ->where('is_active', true)
            ->first(['code', 'name', 'description', 'extra_attributes']);

        if (! $row) {
            return;
        }

        $description = (string) ($row->description ?: $row->name ?: '');

        $extract = function (string $label) use ($description): ?string {
            if (preg_match('/' . preg_quote($label, '/') . '\s+([^\/\s]+)/iu', $description, $matches)) {
                return trim((string) ($matches[1] ?? ''));
            }

            return null;
        };

        $stateCode = strtoupper((string) $extract('Estado'));
        $municipalityCode = (string) $extract('Municipio');
        $localityCode = (string) $extract('Localidad');

        $stateMap = [
            'AGU' => 'Aguascalientes',
            'BCN' => 'Baja California',
            'BCS' => 'Baja California Sur',
            'CAM' => 'Campeche',
            'CHP' => 'Chiapas',
            'CHH' => 'Chihuahua',
            'CMX' => 'Ciudad de México',
            'COA' => 'Coahuila',
            'COL' => 'Colima',
            'DUR' => 'Durango',
            'GUA' => 'Guanajuato',
            'GRO' => 'Guerrero',
            'HID' => 'Hidalgo',
            'JAL' => 'Jalisco',
            'MEX' => 'México',
            'MIC' => 'Michoacán',
            'MOR' => 'Morelos',
            'NAY' => 'Nayarit',
            'NLE' => 'Nuevo León',
            'OAX' => 'Oaxaca',
            'PUE' => 'Puebla',
            'QUE' => 'Querétaro',
            'ROO' => 'Quintana Roo',
            'SLP' => 'San Luis Potosí',
            'SIN' => 'Sinaloa',
            'SON' => 'Sonora',
            'TAB' => 'Tabasco',
            'TAM' => 'Tamaulipas',
            'TLA' => 'Tlaxcala',
            'VER' => 'Veracruz',
            'YUC' => 'Yucatán',
            'ZAC' => 'Zacatecas',
        ];

        $stateName = $stateCode ? ($stateMap[$stateCode] ?? $stateCode) : null;

        $municipalityName = static::satNameFromCatalogByCode('municipio', $municipalityCode, $stateCode);
        $localityName = static::satNameFromCatalogByCode('localidad', $localityCode, $stateCode);

        $set('state', $stateName);
        $set('municipality', $municipalityName ?: ($municipalityCode ?: null));
        $set('locality', $localityName ?: ($localityCode ?: null));
        $set('city', $localityName ?: ($localityCode ?: null));
        $set('country', 'México');
    }


    protected static function neighborhoodOptions(?string $postalCode, ?string $search = null, int $limit = 80): array
    {
        if (! Schema::hasTable('sat_billing_catalog_items')) {
            return [];
        }

        $postalCode = trim((string) $postalCode);
        $search = trim((string) $search);

        if ($postalCode === '') {
            return [];
        }

        $limit = max(1, min($limit, 50));

        // En el catálogo importado de colonias:
        // values.A = código colonia
        // values.B = código postal
        // values.C = nombre colonia
        $query = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', 'colonia')
            ->where('is_active', true)
            ->whereRaw('extra_attributes::text ilike ?', ['%"B":"' . $postalCode . '"%'])
            ->select(['code', 'name', 'description', 'extra_attributes']);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->whereRaw('extra_attributes::text ilike ?', [$like]);
        }

        return $query
            ->limit($limit)
            ->get()
            ->mapWithKeys(function ($row): array {
                $values = static::satExtraValuesFromRow($row);

                $name = trim((string) ($values['C'] ?? ''));

                if ($name === '') {
                    $name = trim((string) ($row->name ?: $row->description ?: $row->code));
                }

                return [$name => $name];
            })
            ->filter(fn ($label) => filled($label))
            ->sort()
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Hidden::make('csf_pdf_path'),
                Forms\Components\Hidden::make('csf_source_filename'),
                Forms\Components\Hidden::make('csf_imported_at'),
                Forms\Components\Hidden::make('csf_imported_by_user_id'),

                Forms\Components\Tabs::make('Contacto')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Información general')
                            ->schema([
                                Forms\Components\Section::make('Datos principales')
                                    ->schema([
                                        Forms\Components\Select::make('contact_type')
                                            ->label('Tipo')
                                            ->options([
                                                'company' => 'Empresa',
                                                'person' => 'Persona',
                                            ])
                                            ->default('company')
                                            ->required()
                                            ->native(false)
                                            ->live()
                                            ->columnSpan(3),

                                        Forms\Components\Select::make('address_type')
                                            ->label('Tipo de contacto')
                                            ->options([
                                                'main' => 'Principal',
                                                'invoice' => 'Facturación',
                                                'delivery' => 'Entrega',
                                                'contact' => 'Contacto',
                                                'other' => 'Otro',
                                            ])
                                            ->default('main')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(3),

                                        Forms\Components\Toggle::make('is_customer')
                                            ->label('Cliente')
                                            ->default(true)
                                            ->columnSpan(2),

                                        Forms\Components\Toggle::make('is_supplier')
                                            ->label('Proveedor')
                                            ->default(false)
                                            ->columnSpan(2),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Activo')
                                            ->default(true)
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('name')
                                            ->label(fn (Forms\Get $get): string => $get('contact_type') === 'person'
                                                ? 'Nombre de la persona'
                                                : 'Razón social / Nombre')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(8),

                                        Forms\Components\TextInput::make('commercial_name')
                                            ->label('Nombre comercial')
                                            ->maxLength(255)
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('parent_contact_id')
                                            ->label('Empresa padre')
                                            ->options(fn (): array => static::parentContactOptions())
                                            ->getSearchResultsUsing(fn (string $search): array => static::parentContactOptions($search))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::parentContactLabel($value))
                                            ->searchable()
                                            ->native(false)
                                            ->visible(fn (Forms\Get $get): bool => $get('contact_type') === 'person' || $get('address_type') !== 'main')
                                            ->helperText('Úsalo para contactos o direcciones ligadas a una empresa.')
                                            ->columnSpan(12),
                                    ])
                                    ->columns(12),

                                Forms\Components\Section::make('Contacto')
                                    ->schema([
                                        Forms\Components\TextInput::make('email')
                                            ->label('Correo electrónico')
                                            ->email()
                                            ->maxLength(255)
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('phone')
                                            ->label('Teléfono')
                                            ->maxLength(80)
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('mobile')
                                            ->label('Móvil')
                                            ->maxLength(80)
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('website')
                                            ->label('Sitio web')
                                            ->maxLength(255)
                                            ->columnSpan(6),
                                    ])
                                    ->columns(12),

                                Forms\Components\Section::make('Dirección')
                                    ->schema([
                                        Forms\Components\TextInput::make('street')
                                            ->label('Calle')
                                            ->maxLength(255)
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('exterior_number')
                                            ->label('No. exterior')
                                            ->maxLength(80)
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('interior_number')
                                            ->label('No. interior')
                                            ->maxLength(80)
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('street2')
                                            ->label('Calle 2 / referencia')
                                            ->maxLength(255)
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('postal_code')
                                            ->label('Código postal')
                                            ->options(fn (): array => [])
                                            ->getSearchResultsUsing(fn (string $search): array => static::satPostalCodeOptions($search, 30))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::satPostalCodeLabel($value))
                                            ->searchable()
                                            ->native(false)
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set, $state): void {
                                                static::applyPostalCodeData($set, $state);
                                            })
                                            ->helperText('Busca por código postal SAT.')
                                            ->columnSpan(3),

                                        Forms\Components\Select::make('neighborhood')
                                            ->label('Colonia')
                                            ->options(fn (Forms\Get $get): array => static::neighborhoodOptions($get('postal_code'), null, 40))
                                            ->getSearchResultsUsing(fn (Forms\Get $get, string $search): array => static::neighborhoodOptions($get('postal_code'), $search, 40))
                                            ->searchable()
                                            ->native(false)
                                            ->placeholder('Primero selecciona el código postal')
                                            ->helperText('Solo muestra colonias del código postal seleccionado.')
                                            ->columnSpan(5),

                                        Forms\Components\TextInput::make('locality')
                                            ->label('Localidad')
                                            ->maxLength(255)
                                            ->readOnly()
                                            ->dehydrated()
                                            ->helperText('Se llena automáticamente desde el catálogo SAT.')
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('municipality')
                                            ->label('Municipio / alcaldía')
                                            ->maxLength(255)
                                            ->readOnly()
                                            ->dehydrated()
                                            ->helperText('Se llena automáticamente desde el código postal.')
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('state')
                                            ->label('Estado')
                                            ->maxLength(255)
                                            ->readOnly()
                                            ->dehydrated()
                                            ->helperText('Se llena automáticamente desde el código postal.')
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('city')
                                            ->label('Ciudad')
                                            ->maxLength(255)
                                            ->helperText('Si aplica, se llena con la localidad; puedes ajustarla.')
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('country')
                                            ->label('País')
                                            ->default('México')
                                            ->maxLength(120)
                                            ->columnSpan(4),
                                    ])
                                    ->columns(12),
                            ]),

                        Forms\Components\Tabs\Tab::make('Facturación')
                            ->schema([
                                Forms\Components\Section::make('Información fiscal')
                                    ->schema([
                                        Forms\Components\TextInput::make('fiscal_name')
                                            ->label('Razón social fiscal')
                                            ->maxLength(255)
                                            ->helperText('Si se deja vacío, se usará el nombre principal.')
                                            ->columnSpan(6),

                                        Forms\Components\TextInput::make('rfc')
                                            ->label('RFC')
                                            ->maxLength(20)
                                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('curp')
                                            ->label('CURP')
                                            ->maxLength(30)
                                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                            ->visible(fn (Forms\Get $get): bool => $get('contact_type') === 'person')
                                            ->columnSpan(3),

                                        Forms\Components\Select::make('sat_tax_regime_code')
                                            ->label('Régimen fiscal')
                                            ->options(fn (): array => static::satCatalogOptions('regimen_fiscal', null, 30))
                                            ->getSearchResultsUsing(fn (string $search): array => static::satCatalogOptions('regimen_fiscal', $search, 80))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::satCatalogLabel('regimen_fiscal', $value))
                                            ->searchable()
                                            ->native(false)
                                            ->columnSpan(6),

                                        Forms\Components\Select::make('sat_cfdi_use_code')
                                            ->label('Uso CFDI')
                                            ->options(fn (): array => static::satCatalogOptions('uso_cfdi', null, 30))
                                            ->getSearchResultsUsing(fn (string $search): array => static::satCatalogOptions('uso_cfdi', $search, 80))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::satCatalogLabel('uso_cfdi', $value))
                                            ->searchable()
                                            ->native(false)
                                            ->columnSpan(6),

                                        Forms\Components\Hidden::make('fiscal_zip')
                                            ->dehydrated(true),

                                        Forms\Components\Hidden::make('sat_country_code')
                                            ->dehydrated(true),

                                        Forms\Components\Toggle::make('blacklisted_sat')
                                            ->label('Lista negra SAT')
                                            ->default(false)
                                            ->columnSpan(3),
                                    ])
                                    ->columns(12),


                                Forms\Components\Section::make('Constancia de Situación Fiscal')
                                    ->description('Archivo PDF usado como evidencia para cargar la información fiscal del contacto.')
                                    ->schema([
                                        Forms\Components\Placeholder::make('csf_file_info')
                                            ->label('Archivo')
                                            ->content(function (?Contact $record, Forms\Get $get): HtmlString {
                                                $path = trim((string) ($record?->csf_pdf_path ?: $get('csf_pdf_path')));
                                                $filename = trim((string) ($record?->csf_source_filename ?: $get('csf_source_filename')));
                                                $importedAt = $record?->csf_imported_at ?: $get('csf_imported_at');

                                                if ($path === '') {
                                                    return new HtmlString('<span class="text-gray-500">Sin Constancia SAT cargada.</span>');
                                                }

                                                if ($filename === '') {
                                                    $filename = basename($path);
                                                }

                                                $dateText = $importedAt
                                                    ? '<div class="text-sm text-gray-500 mt-1">Importada: ' . e((string) $importedAt) . '</div>'
                                                    : '';

                                                if (! $record) {
                                                    return new HtmlString(
                                                        '<div class="space-y-1">'
                                                        . '<div class="font-medium">' . e($filename) . '</div>'
                                                        . '<div class="text-sm text-gray-500">El archivo ya fue cargado. Podrás verlo o descargarlo después de guardar el contacto.</div>'
                                                        . $dateText
                                                        . '</div>'
                                                    );
                                                }

                                                $viewUrl = route('contacts.csf.show', ['contact' => $record]);
                                                $downloadUrl = route('contacts.csf.download', ['contact' => $record]);

                                                return new HtmlString(
                                                    '<div class="space-y-2">'
                                                    . '<div class="font-medium">' . e($filename) . '</div>'
                                                    . $dateText
                                                    . '<div class="flex gap-2 mt-2">'
                                                    . '<a class="fi-btn fi-btn-size-sm fi-btn-color-gray inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold ring-1 ring-gray-300 hover:bg-gray-50" href="' . e($viewUrl) . '" target="_blank" rel="noopener">Ver PDF</a>'
                                                    . '<a class="fi-btn fi-btn-size-sm fi-btn-color-primary inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold bg-primary-600 text-white hover:bg-primary-500" href="' . e($downloadUrl) . '">Descargar PDF</a>'
                                                    . '</div>'
                                                    . '</div>'
                                                );
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(12),

                                Forms\Components\Section::make('Pago')
                                    ->schema([
                                        Forms\Components\Select::make('payment_form_code')
                                            ->label('Forma de pago')
                                            ->options(fn (): array => static::satCatalogOptions('forma_pago', null, 30))
                                            ->getSearchResultsUsing(fn (string $search): array => static::satCatalogOptions('forma_pago', $search, 80))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::satCatalogLabel('forma_pago', $value))
                                            ->searchable()
                                            ->native(false)
                                            ->columnSpan(6),

                                        Forms\Components\Select::make('payment_method_code')
                                            ->label('Método de pago')
                                            ->options(fn (): array => static::satCatalogOptions('metodo_pago', null, 30))
                                            ->getSearchResultsUsing(fn (string $search): array => static::satCatalogOptions('metodo_pago', $search, 80))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::satCatalogLabel('metodo_pago', $value))
                                            ->searchable()
                                            ->native(false)
                                            ->columnSpan(6),
                                    ])
                                    ->columns(12),
                            ]),

                        Forms\Components\Tabs\Tab::make('Venta y compra')
                            ->schema([
                                Forms\Components\Section::make('Ventas')
                                    ->description('Valores que se usarán al crear cotizaciones, pedidos y facturas para este cliente.')
                                    ->schema([
                                        Forms\Components\Select::make('salesperson_user_id')
                                            ->label('Vendedor asignado')
                                            ->options(fn (): array => static::contactUserOptions())
                                            ->searchable()
                                            ->native(false)
                                            ->placeholder('Sin vendedor asignado')
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('customer_cfdi_use_code')
                                            ->label('Uso CFDI')
                                            ->options(fn (): array => static::contactCfdiUseOptions())
                                            ->searchable()
                                            ->native(false)
                                            ->placeholder('Seleccionar uso CFDI')
                                            ->helperText('Se sugerirá al facturar a este cliente.')
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('customer_currency_code')
                                            ->label('Moneda')
                                            ->options(fn (): array => static::contactBillingCatalogOptions('moneda'))
                                            ->searchable()
                                            ->native(false)
                                            ->default('MXN')
                                            ->placeholder('MXN')
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('customer_price_list_id')
                                            ->label('Lista de precios')
                                            ->options(fn (): array => static::v5495aSalesPriceListOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(4)
                                            ->helperText('Lista predeterminada para ventas y PDV. Si el PDV no la permite, usará la predeterminada del PDV.'),

                                        Forms\Components\Select::make('customer_payment_method_code')
                                            ->label('Método de pago SAT')
                                            ->options(fn (): array => static::contactBillingCatalogOptions('metodo_pago'))
                                            ->searchable()
                                            ->native(false)
                                            ->placeholder('Seleccionar método')
                                            ->helperText('Ejemplo: PUE o PPD.')
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('customer_payment_form_code')
                                            ->label('Forma de pago SAT')
                                            ->options(fn (): array => static::contactBillingCatalogOptions('forma_pago'))
                                            ->searchable()
                                            ->native(false)
                                            ->placeholder('Seleccionar forma')
                                            ->helperText('Ejemplo: 03 Transferencia, 99 Por definir.')
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('customer_payment_term_id')
                                            ->label('Términos de pago cliente')
                                            ->options(fn (): array => static::contactPaymentTermOptions())
                                            ->searchable()
                                            ->native(false)
                                            ->placeholder('Seleccionar términos')
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('customer_credit_limit')
                                            ->label('Límite de crédito')
                                            ->numeric()
                                            ->prefix('$')
                                            ->placeholder('0.00')
                                            ->columnSpan(4),
                                    ])
                                    ->columns(12),

                                Forms\Components\Section::make('Compras')
                                    ->description('Valores que se usarán cuando este contacto sea proveedor.')
                                    ->schema([
                                        Forms\Components\Select::make('supplier_payment_form_code')
                                            ->label('Forma de pago proveedor')
                                            ->options(fn (): array => static::contactBillingCatalogOptions('forma_pago'))
                                            ->searchable()
                                            ->native(false)
                                            ->placeholder('Seleccionar forma')
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('supplier_currency_code')
                                            ->label('Moneda proveedor')
                                            ->options(fn (): array => static::contactBillingCatalogOptions('moneda'))
                                            ->searchable()
                                            ->native(false)
                                            ->default('MXN')
                                            ->placeholder('MXN')
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('supplier_payment_term_id')
                                            ->label('Términos de pago proveedor')
                                            ->options(fn (): array => static::contactPaymentTermOptions())
                                            ->searchable()
                                            ->native(false)
                                            ->placeholder('Seleccionar términos')
                                            ->columnSpan(4),
                                    ])
                                    ->columns(12),
                            ]),

                        Forms\Components\Tabs\Tab::make('Notas internas')
                            ->schema([
                                Forms\Components\Textarea::make('internal_notes')
                                    ->label('Notas internas')
                                    ->rows(8)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('rfc')
                    ->label('RFC')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'company' => 'Empresa',
                        'person' => 'Persona',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_customer')
                    ->label('Cliente')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_supplier')
                    ->label('Proveedor')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fiscal_zip')
                    ->label('CP fiscal')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('contact_type')
                    ->label('Tipo')
                    ->options([
                        'company' => 'Empresa',
                        'person' => 'Persona',
                    ]),

                Tables\Filters\TernaryFilter::make('is_customer')
                    ->label('Cliente'),

                Tables\Filters\TernaryFilter::make('is_supplier')
                    ->label('Proveedor'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            
                Tables\Filters\TrashedFilter::make()
                    ->label('Archivados')
                    ->placeholder('Sin archivados')
                    ->trueLabel('Con archivados')
                    ->falseLabel('Solo archivados'),
])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->url(function (\App\Models\Contact $record, ?\Livewire\Component $livewire = null): string {
                        $params = [
                            'record' => $record,
                        ];

                        $search = null;

                        if ($livewire) {
                            foreach (['tableSearch', 'tableSearchQuery'] as $property) {
                                try {
                                    if (property_exists($livewire, $property) && filled($livewire->{$property})) {
                                        $search = $livewire->{$property};
                                        break;
                                    }
                                } catch (\Throwable) {
                                    // Ignorar propiedades internas no accesibles.
                                }
                            }
                        }

                        if (! filled($search)) {
                            $search = request()->query('tableSearch')
                                ?: request()->query('contact_nav_search');
                        }

                        if (filled($search)) {
                            $params['contact_nav_search'] = trim((string) $search);
                        }

                        return static::getUrl('edit', $params);
                    }),

                Tables\Actions\RestoreAction::make()
                    ->label('Desarchivar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->modalHeading('Desarchivar contacto')
                    ->modalDescription('El contacto volverá a estar activo y visible en la lista principal.')
                    ->modalSubmitActionLabel('Desarchivar')
                    ->successNotificationTitle('Contacto desarchivado')
                    ->visible(fn ($record): bool => method_exists($record, 'trashed') && $record->trashed()),

                Tables\Actions\DeleteAction::make()
                    ->label('Archivar')
                    ->icon('heroicon-o-archive-box')
                    ->modalHeading('Archivar contacto')
                    ->modalDescription('El contacto no se eliminará físicamente; quedará archivado para conservar historial.')
                    ->modalSubmitActionLabel('Archivar')
                    ->successNotificationTitle('Contacto archivado')
                    ->visible(fn ($record): bool => ! method_exists($record, 'trashed') || ! $record->trashed()),
            ])          ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\RestoreAction::make()
                    ->label('Desarchivar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->modalHeading('Desarchivar contacto')
                    ->modalDescription('El contacto volverá a estar activo y visible en la lista principal.')
                    ->modalSubmitActionLabel('Desarchivar')
                    ->successNotificationTitle('Contacto desarchivado')
                    ->visible(fn ($record): bool => method_exists($record, 'trashed') && $record->trashed()),

                Tables\Actions\DeleteAction::make()
                    ->label('Archivar')
                    ->icon('heroicon-o-archive-box')
                    ->modalHeading('Archivar contacto')
                    ->modalDescription('El contacto no se eliminará físicamente; quedará archivado para conservar historial.')
                    ->modalSubmitActionLabel('Archivar')
                    ->successNotificationTitle('Contacto archivado')
                    ->visible(fn ($record): bool => ! method_exists($record, 'trashed') || ! $record->trashed()),
            ])          ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Archivar')
                    ->modalHeading('Archivar registro')
                    ->modalSubmitActionLabel('Archivar')
                    ->modalDescription('El registro no se eliminará físicamente; quedará archivado para conservar historial.'),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\RestoreBulkAction::make()
                        ->label('Desarchivar seleccionados')
                        ->modalHeading('Desarchivar contactos seleccionados')
                        ->modalDescription('Los contactos seleccionados volverán a estar activos.')
                        ->modalSubmitActionLabel('Desarchivar')
                        ->successNotificationTitle('Contactos desarchivados'),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Archivar seleccionados')
                        ->modalHeading('Archivar contactos seleccionados')
                        ->modalDescription('Los contactos no se eliminarán físicamente; quedarán archivados.')
                        ->modalSubmitActionLabel('Archivar')
                        ->successNotificationTitle('Contactos archivados'),
                ]),
            ])          ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Archivar seleccionados')
                        ->modalHeading('Archivar registros seleccionados')
                        ->modalSubmitActionLabel('Archivar')
                        ->modalDescription('Los registros no se eliminarán físicamente; quedarán archivados para conservar historial.'),
                ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            ChildContactsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }
    protected static function v5495aSalesPriceListOptions(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_price_lists')) {
            return [];
        }

        $query = \Illuminate\Support\Facades\DB::table('sales_price_lists');

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_price_lists', 'company_id')) {
            $companyId = 0;

            try {
                $tenant = \Filament\Facades\Filament::getTenant();
                $companyId = is_object($tenant) && method_exists($tenant, 'getKey')
                    ? (int) $tenant->getKey()
                    : (is_numeric($tenant) ? (int) $tenant : 0);
            } catch (\Throwable $e) {
                $companyId = 0;
            }

            if ($companyId <= 0) {
                $companyId = (int) (auth()->user()?->company_id ?? 0);
            }

            if ($companyId > 0) {
                $query->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)
                      ->orWhereNull('company_id');
                });
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_price_lists', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
            ->all();
    }


}
