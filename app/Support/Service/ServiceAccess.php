<?php

namespace App\Support\Service;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class ServiceAccess
{
    public static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                return (int) $tenant->getKey();
            }

            if (is_object($tenant) && isset($tenant->id)) {
                return (int) $tenant->id;
            }

            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
        } catch (\Throwable) {
            //
        }

        $routeTenant = request()?->route('tenant');

        if (is_object($routeTenant) && method_exists($routeTenant, 'getKey')) {
            return (int) $routeTenant->getKey();
        }

        if (is_object($routeTenant) && isset($routeTenant->id)) {
            return (int) $routeTenant->id;
        }

        if (is_numeric($routeTenant)) {
            return (int) $routeTenant;
        }

        $user = auth()->user();

        if ($user && isset($user->company_id) && $user->company_id) {
            return (int) $user->company_id;
        }

        return null;
    }

    public static function can(string|array $permissions): bool
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'can')) {
            return false;
        }

        self::setPermissionTeam();

        $permissions = is_array($permissions) ? $permissions : [$permissions];

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        if ($user->can('company.update')) {
            return true;
        }

        return false;
    }

    public static function setPermissionTeam(): void
    {
        if (! class_exists(PermissionRegistrar::class)) {
            return;
        }

        $companyId = self::currentCompanyId();

        if (! $companyId) {
            return;
        }

        try {
            app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
        } catch (\Throwable) {
            //
        }
    }

    public static function tableHasCompany(string $table): bool
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumn($table, 'company_id');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function contactOptions(?string $search = null): array
    {
        if (! self::tableExists('contacts')) {
            return [];
        }

        // El ticket usa contactos principales activos del tenant actual.
        // No se incluyen contactos globales ni de otras empresas.
        // No se obliga is_customer porque el catalogo actual contiene
        // contactos operativos validos sin esa marca.
        return self::contactOptionsQuery(
            search: $search,
            applyCompanyScope: true,
            applyCustomerFilter: false
        );
    }

    protected static function contactOptionsQuery(
        ?string $search = null,
        bool $applyCompanyScope = true,
        bool $applyCustomerFilter = true
    ): array {
        try {
            $query = DB::table('contacts')->select('*');

            if ($applyCompanyScope && self::hasColumn('contacts', 'company_id')) {
                $companyId = self::currentCompanyId();

                if (! $companyId) {
                    return [];
                }

                $query->where('company_id', $companyId);
            }

            if (self::hasColumn('contacts', 'parent_contact_id')) {
                $query->whereNull('parent_contact_id');
            }

            if (self::hasColumn('contacts', 'address_type')) {
                $query->where('address_type', 'main');
            }

            if (self::hasColumn('contacts', 'is_active')) {
                $query->where('is_active', true);
            }

            if (self::hasColumn('contacts', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if ($applyCustomerFilter) {
                self::applyCustomerContactFilter($query);
            }

            self::applySearch($query, 'contacts', [
                'name',
                'business_name',
                'legal_name',
                'company_name',
                'rfc',
                'tax_id',
                'email',
                'phone',
            ], $search);

            $rows = $query->orderBy('id', 'desc')->limit(50)->get();

            $options = [];

            foreach ($rows as $row) {
                $id = (int) ($row->id ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $options[$id] = self::makeLabel($row, [
                    'name',
                    'business_name',
                    'legal_name',
                    'company_name',
                    'rfc',
                    'tax_id',
                    'email',
                    'phone',
                ]);
            }

            return $options;
        } catch (\Throwable) {
            return [];
        }
    }


    public static function userOptions(?string $search = null): array
    {
        return self::optionsFromTable(
            table: 'users',
            labelColumns: ['name', 'email'],
            searchColumns: ['name', 'email'],
            search: $search,
            allowGlobal: true
        );
    }

    public static function productOptions(?string $search = null): array
    {
        if (! self::tableExists('products')) {
            return [];
        }

        try {
            $query = DB::table('products')->select('*');

            self::applyCompanyScope($query, 'products', true);
            self::applySearch(
                $query,
                'products',
                ['code', 'sku', 'name', 'description'],
                $search
            );

            $rows = $query->orderBy('id', 'desc')->limit(50)->get();

            $options = [];

            foreach ($rows as $row) {
                $id = (int) ($row->id ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $options[$id] = self::makeProductDisplayLabel($row);
            }

            return $options;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function serviceCaseOptions(?string $search = null): array
    {
        return self::optionsFromTable(
            table: 'service_cases',
            labelColumns: ['folio', 'subject', 'status'],
            searchColumns: ['folio', 'subject', 'status'],
            search: $search,
            allowGlobal: false
        );
    }

    public static function saleOrderOptions(?string $search = null): array
    {
        return self::optionsFromTable(
            table: 'sale_orders',
            labelColumns: ['number', 'folio', 'status', 'total'],
            searchColumns: ['number', 'folio', 'status'],
            search: $search,
            allowGlobal: false
        );
    }

    public static function invoiceOptions(?string $search = null): array
    {
        return self::optionsFromTable(
            table: 'invoices',
            labelColumns: ['number', 'folio', 'uuid', 'status', 'total'],
            searchColumns: ['number', 'folio', 'uuid', 'status'],
            search: $search,
            allowGlobal: false
        );
    }

    public static function serialOptions(?string $search = null): array
    {
        return self::stringOptionsFromTable(
            table: 'stock_serial_numbers',
            valueColumns: ['serial_number', 'serial', 'number', 'name'],
            labelColumns: ['serial_number', 'serial', 'number', 'name', 'product_id', 'status'],
            searchColumns: ['serial_number', 'serial', 'number', 'name', 'status'],
            search: $search,
            allowGlobal: false
        );
    }

    public static function lotOptions(?string $search = null): array
    {
        return self::stringOptionsFromTable(
            table: 'stock_lots',
            valueColumns: ['lot_number', 'number', 'name', 'code'],
            labelColumns: ['lot_number', 'number', 'name', 'code', 'product_id'],
            searchColumns: ['lot_number', 'number', 'name', 'code'],
            search: $search,
            allowGlobal: false
        );
    }

    public static function contactLabel(?int $id): ?string
    {
        return self::labelForId('contacts', $id, ['name', 'business_name', 'legal_name', 'company_name', 'rfc', 'tax_id', 'email', 'phone']);
    }

    public static function contactDetails(?int $id): array
    {
        if (! $id || ! self::tableExists('contacts')) {
            return [];
        }

        try {
            $row = DB::table('contacts')->where('id', $id)->first();

            if (! $row) {
                return [];
            }

            return [
                'contact_name' => self::firstNonEmpty($row, [
                    'contact_name',
                    'responsible_name',
                    'representative_name',
                    'attention_to',
                    'name',
                    'business_name',
                    'legal_name',
                    'company_name',
                ]),
                'contact_email' => self::firstNonEmpty($row, [
                    'email',
                    'contact_email',
                    'billing_email',
                    'work_email',
                ]),
                'contact_phone' => self::firstNonEmpty($row, [
                    'phone',
                    'mobile',
                    'contact_phone',
                    'telephone',
                    'work_phone',
                    'billing_phone',
                ]),
            ];
        } catch (\Throwable) {
            return [];
        }
    }


    public static function productLabel(?int $id): ?string
    {
        if (! $id || ! self::tableExists('products')) {
            return null;
        }

        try {
            $row = DB::table('products')->where('id', $id)->first();

            if (! $row) {
                return null;
            }

            return self::makeProductDisplayLabel($row);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function makeProductDisplayLabel(object $row): string
    {
        $rawSku = trim((string) self::firstNonEmpty($row, ['sku']));
        $rawName = trim((string) self::firstNonEmpty($row, ['name']));

        $embeddedSku = '';

        if (
            preg_match(
                '/^\s*\[([^\]]+)\]\s*/u',
                $rawName,
                $matches
            ) === 1
        ) {
            $embeddedSku = trim((string) ($matches[1] ?? ''));
        }

        $sku = $rawSku;

        if (
            $sku === ''
            || preg_match('/^ODOO-HIST-P-/i', $sku) === 1
        ) {
            $sku = '';

            if (
                $embeddedSku !== ''
                && preg_match(
                    '/^ODOO-HIST-P-/i',
                    $embeddedSku
                ) !== 1
            ) {
                $sku = $embeddedSku;
            } elseif (
                property_exists($row, 'code')
                && filled($row->code)
                && preg_match(
                    '/^ODOO-HIST-P-/i',
                    (string) $row->code
                ) !== 1
            ) {
                $sku = trim((string) $row->code);
            }
        }

        $name = (string) preg_replace(
            '/^\s*\[[^\]]+\]\s*/u',
            '',
            $rawName
        );

        $name = (string) preg_replace(
            '/^\s*ODOO-HIST-P-\d+\s*-\s*/iu',
            '',
            $name
        );

        $name = (string) preg_replace(
            '/\s*-?\s*Producto historico auto-creado desde Odoo.*$/iu',
            '',
            $name
        );

        $name = trim($name);

        $parts = array_values(array_unique(array_filter(
            [$sku, $name],
            fn (string $value): bool => $value !== ''
        )));

        if ($parts !== []) {
            return implode(' - ', $parts);
        }

        return '#' . (string) ($row->id ?? '');
    }

    public static function userLabel(?int $id): ?string
    {
        return self::labelForId('users', $id, ['name', 'email']);
    }

    public static function companyGroupCompanyIds(): array
    {
        $companyId = self::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        if (! self::tableExists('companies')) {
            return [$companyId];
        }

        try {
            $company = DB::table('companies')->where('id', $companyId)->first();

            if (! $company) {
                return [$companyId];
            }

            if (property_exists($company, 'company_group_id') && $company->company_group_id) {
                $ids = DB::table('companies')
                    ->where('company_group_id', $company->company_group_id)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all();

                if (! in_array($companyId, $ids, true)) {
                    $ids[] = $companyId;
                }

                return array_values(array_unique($ids));
            }

            return [$companyId];
        } catch (\Throwable) {
            return [$companyId];
        }
    }

    public static function technicianEmployeeOptions(?string $search = null): array
    {
        if (! self::tableExists('employees')) {
            return [];
        }

        try {
            $query = DB::table('employees')->select('*');

            $companyIds = self::companyGroupCompanyIds();

            if ($companyIds !== [] && self::hasColumn('employees', 'company_id')) {
                $query->whereIn('company_id', $companyIds);
            }

            if (self::hasColumn('employees', 'is_service_technician')) {
                $query->where('is_service_technician', true);
            }

            self::applySearch($query, 'employees', [
                'employee_number',
                'code',
                'name',
                'first_name',
                'last_name',
                'email',
                'phone',
            ], $search);

            $rows = $query->orderBy('id', 'desc')->limit(50)->get();

            $options = [];

            foreach ($rows as $row) {
                $id = (int) ($row->id ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $options[$id] = self::makeEmployeeLabel($row);
            }

            return $options;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function employeeLabel(?int $id): ?string
    {
        if (! $id || ! self::tableExists('employees')) {
            return null;
        }

        try {
            $row = DB::table('employees')->where('id', $id)->first();

            if (! $row) {
                return null;
            }

            return self::makeEmployeeLabel($row);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function makeEmployeeLabel(object $row): string
    {
        $nameParts = [];

        foreach (['name', 'first_name', 'last_name'] as $column) {
            if (property_exists($row, $column) && filled($row->{$column})) {
                $nameParts[] = (string) $row->{$column};
            }
        }

        $name = trim(implode(' ', array_unique($nameParts)));

        $extra = [];

        foreach (['employee_number', 'code', 'email'] as $column) {
            if (property_exists($row, $column) && filled($row->{$column})) {
                $extra[] = (string) $row->{$column};
            }
        }

        $label = trim($name . ($extra ? ' - ' . implode(' - ', array_unique($extra)) : ''));

        return $label !== '' ? $label : '#' . (string) ($row->id ?? '');
    }

    public static function serviceCaseLabel(?int $id): ?string
    {
        return self::labelForId('service_cases', $id, ['folio', 'subject', 'status']);
    }

    public static function serviceCaseDetails(?int $id): array
    {
        if (! $id || ! self::tableExists('service_cases')) {
            return [];
        }

        try {
            $row = DB::table('service_cases')->where('id', $id)->first();

            if (! $row) {
                return [];
            }

            $subject = property_exists($row, 'subject') ? (string) ($row->subject ?? '') : '';
            $description = property_exists($row, 'description') ? (string) ($row->description ?? '') : '';

            $initialDiagnosis = trim(implode(PHP_EOL.PHP_EOL, array_filter([
                $subject !== '' ? 'Ticket: '.$subject : null,
                $description !== '' ? $description : null,
            ])));

            $productName = property_exists($row, 'product_name') ? ($row->product_name ?? null) : null;

            if (! $productName && property_exists($row, 'product_id') && $row->product_id) {
                $productName = self::productLabel((int) $row->product_id);
            }

            return [
                'customer_id' => property_exists($row, 'customer_id') ? $row->customer_id : null,
                'assigned_employee_id' => property_exists($row, 'assigned_employee_id') ? $row->assigned_employee_id : null,
                'product_id' => property_exists($row, 'product_id') ? $row->product_id : null,
                'product_name' => $productName,
                'serial_number' => property_exists($row, 'serial_number') ? $row->serial_number : null,
                'lot_number' => property_exists($row, 'lot_number') ? $row->lot_number : null,
                'sale_id' => property_exists($row, 'sale_id') ? $row->sale_id : null,
                'sale_reference' => property_exists($row, 'sale_reference') ? $row->sale_reference : null,
                'invoice_id' => property_exists($row, 'invoice_id') ? $row->invoice_id : null,
                'invoice_reference' => property_exists($row, 'invoice_reference') ? $row->invoice_reference : null,
                'initial_diagnosis' => $initialDiagnosis !== '' ? $initialDiagnosis : null,
            ];
        } catch (\Throwable) {
            return [];
        }
    }


    public static function saleOrderLabel(?int $id): ?string
    {
        return self::labelForId('sale_orders', $id, ['number', 'folio', 'status', 'total']);
    }

    public static function invoiceLabel(?int $id): ?string
    {
        return self::labelForId('invoices', $id, ['number', 'folio', 'uuid', 'status', 'total']);
    }

    public static function saveUploadedAttachments(
        ?int $companyId,
        ?int $serviceCaseId,
        ?int $repairOrderId,
        mixed $files,
        string $stage,
        bool $isCustomerVisible = false
    ): void {
        if (! self::tableExists('service_attachments')) {
            return;
        }

        $paths = self::flattenUploadPaths($files);

        if ($paths === []) {
            return;
        }

        foreach ($paths as $path) {
            $path = trim((string) $path);

            if ($path === '') {
                continue;
            }

            $fileName = basename($path);
            $mimeType = null;
            $size = null;

            try {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($path);
                    $size = \Illuminate\Support\Facades\Storage::disk('public')->size($path);
                }
            } catch (\Throwable) {
                //
            }

            \App\Models\ServiceAttachment::updateOrCreate(
                [
                    'service_case_id' => $serviceCaseId,
                    'repair_order_id' => $repairOrderId,
                    'file_path' => $path,
                ],
                [
                    'company_id' => $companyId,
                    'uploaded_by' => auth()->id(),
                    'stage' => $stage,
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'is_customer_visible' => $isCustomerVisible,
                ]
            );
        }
    }

    protected static function flattenUploadPaths(mixed $files): array
    {
        if ($files === null || $files === '') {
            return [];
        }

        if (is_string($files)) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        $paths = [];

        foreach ($files as $file) {
            if (is_string($file)) {
                $paths[] = $file;
                continue;
            }

            if (is_array($file)) {
                $paths = array_merge($paths, self::flattenUploadPaths($file));
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    protected static function optionsFromTable(
        string $table,
        array $labelColumns,
        array $searchColumns,
        ?string $search = null,
        bool $allowGlobal = true,
        int $limit = 50
    ): array {
        if (! self::tableExists($table)) {
            return [];
        }

        try {
            $query = DB::table($table)->select('*');

            self::applyCompanyScope($query, $table, $allowGlobal);
            self::applySearch($query, $table, $searchColumns, $search);

            $rows = $query->orderBy('id', 'desc')->limit($limit)->get();

            $options = [];

            foreach ($rows as $row) {
                $id = (int) ($row->id ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $options[$id] = self::makeLabel($row, $labelColumns);
            }

            return $options;
        } catch (\Throwable) {
            return [];
        }
    }

    protected static function stringOptionsFromTable(
        string $table,
        array $valueColumns,
        array $labelColumns,
        array $searchColumns,
        ?string $search = null,
        bool $allowGlobal = true,
        int $limit = 50
    ): array {
        if (! self::tableExists($table)) {
            return [];
        }

        try {
            $query = DB::table($table)->select('*');

            self::applyCompanyScope($query, $table, $allowGlobal);
            self::applySearch($query, $table, $searchColumns, $search);

            $rows = $query->orderBy('id', 'desc')->limit($limit)->get();

            $options = [];

            foreach ($rows as $row) {
                $value = self::firstNonEmpty($row, $valueColumns);

                if (! $value) {
                    continue;
                }

                $options[(string) $value] = self::makeLabel($row, $labelColumns);
            }

            return $options;
        } catch (\Throwable) {
            return [];
        }
    }

    protected static function labelForId(string $table, ?int $id, array $labelColumns): ?string
    {
        if (! $id || ! self::tableExists($table)) {
            return null;
        }

        try {
            $row = DB::table($table)->where('id', $id)->first();

            if (! $row) {
                return null;
            }

            return self::makeLabel($row, $labelColumns);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function applyCompanyScope($query, string $table, bool $allowGlobal): void
    {
        $companyId = self::currentCompanyId();

        if (! $companyId || ! self::hasColumn($table, 'company_id')) {
            return;
        }

        $query->where(function ($q) use ($companyId, $allowGlobal): void {
            $q->where('company_id', $companyId);

            if ($allowGlobal) {
                $q->orWhereNull('company_id');
            }
        });
    }

    protected static function applySearch($query, string $table, array $columns, ?string $search): void
    {
        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        $available = array_values(array_filter(
            $columns,
            fn (string $column): bool => self::hasColumn($table, $column)
        ));

        if ($available === []) {
            return;
        }

        $query->where(function ($q) use ($available, $search): void {
            foreach ($available as $column) {
                $q->orWhere($column, 'ilike', '%' . $search . '%');
            }
        });
    }

    protected static function makeLabel(object $row, array $columns): string
    {
        $parts = [];

        foreach ($columns as $column) {
            if (! property_exists($row, $column)) {
                continue;
            }

            $value = $row->{$column};

            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = (string) $value;
        }

        $label = trim(implode(' - ', array_unique($parts)));

        if ($label !== '') {
            return $label;
        }

        return '#' . (string) ($row->id ?? '');
    }

    protected static function firstNonEmpty(object $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (! property_exists($row, $column)) {
                continue;
            }

            $value = $row->{$column};

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    public static function productPricingDetails(?int $id): array
    {
        if (! $id || ! self::tableExists('products')) {
            return [];
        }

        try {
            $row = DB::table('products')->where('id', $id)->first();

            if (! $row) {
                return [];
            }

            return [
                'product_name' => self::makeLabel($row, ['name', 'sku', 'barcode', 'code']),
                'unit_cost' => self::firstNonEmpty($row, [
                    'standard_cost',
                    'cost',
                    'average_cost',
                    'last_cost',
                    'purchase_price',
                    'unit_cost',
                ]),
                'unit_price' => self::firstNonEmpty($row, [
                    'sale_price',
                    'price',
                    'list_price',
                    'public_price',
                    'unit_price',
                ]),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function approvalFlowOptions(?string $search = null): array
    {
        $candidates = [
            'approval_flows' => ['name', 'label', 'code', 'document_type'],
            'approval_workflows' => ['name', 'label', 'code', 'document_type'],
            'approval_document_types' => ['name', 'label', 'code', 'document_type'],
            'document_approval_types' => ['name', 'label', 'code', 'document_type'],
            'approval_types' => ['name', 'label', 'code', 'document_type'],
            'approval_rules' => ['name', 'label', 'code', 'document_type'],
        ];

        foreach ($candidates as $table => $columns) {
            if (! self::tableExists($table)) {
                continue;
            }

            try {
                $query = DB::table($table)->select('*');

                self::applySearch($query, $table, $columns, $search);

                $rows = $query->limit(50)->get();
                $options = [];

                foreach ($rows as $row) {
                    $id = (int) ($row->id ?? 0);

                    if ($id <= 0) {
                        continue;
                    }

                    $options[$id] = '['.$table.'] '.self::makeLabel($row, $columns);
                }

                if ($options !== []) {
                    return $options;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }



    public static function serviceApprovalDocumentTypeOptions(): array
    {
        return [
            'service_repair_parts_request' => 'Solicitud de refacciones/materiales para reparación',
            'service_repair_quote_internal' => 'Aprobacion interna de presupuesto de reparacion',
            'service_repair_warranty' => 'Validacion interna de garantia',
            'service_repair_delivery' => 'Autorizacion de entrega',
        ];
    }

    public static function serviceApprovalDocumentTypeLabel(?string $documentType): string
    {
        $options = self::serviceApprovalDocumentTypeOptions();

        return $options[$documentType] ?? ($documentType ?: 'Aprobacion de servicio');
    }

    public static function ensureServiceApprovalWorkflows(?int $companyId = null): array
    {
        if (! self::tableExists('approval_workflows') || ! self::tableExists('approval_workflow_steps')) {
            return [];
        }

        $companyId = $companyId ?: self::currentCompanyId() ?: 1;
        $created = [];

        foreach (self::serviceApprovalDocumentTypeOptions() as $documentType => $label) {
            $workflow = DB::table('approval_workflows')
                ->where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->first();

            if (! $workflow) {
                $workflowId = DB::table('approval_workflows')->insertGetId([
                    'company_id' => $companyId,
                    'name' => $label,
                    'document_type' => $documentType,
                    'is_active' => true,
                    'priority' => 100,
                    'amount_min' => 0,
                    'amount_max' => null,
                    'applies_to_user_id' => null,
                    'applies_to_role_name' => null,
                    'applies_to_warehouse_id' => null,
                    'notes' => 'Flujo base generado para Atencion y Servicio.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $approverUserId = null;

                if (self::tableExists('users')) {
                    $approverUserId = DB::table('users')->where('id', 1)->value('id')
                        ?: DB::table('users')->orderBy('id')->value('id');
                }

                DB::table('approval_workflow_steps')->insert([
                    'approval_workflow_id' => $workflowId,
                    'sort_order' => 1,
                    'name' => 'Aprobacion interna servicio',
                    'is_active' => true,
                    'approver_type' => $approverUserId ? 'specific_user' : 'role',
                    'approver_user_id' => $approverUserId,
                    'approver_role_name' => $approverUserId ? null : 'Super Admin',
                    'require_all' => false,
                    'amount_min' => 0,
                    'amount_max' => null,
                    'notes' => 'Paso base. Ajustar aprobador desde el modulo de flujos.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created[$documentType] = $workflowId;

                continue;
            }

            $hasStep = DB::table('approval_workflow_steps')
                ->where('approval_workflow_id', $workflow->id)
                ->exists();

            if (! $hasStep) {
                $approverUserId = null;

                if (self::tableExists('users')) {
                    $approverUserId = DB::table('users')->where('id', 1)->value('id')
                        ?: DB::table('users')->orderBy('id')->value('id');
                }

                DB::table('approval_workflow_steps')->insert([
                    'approval_workflow_id' => $workflow->id,
                    'sort_order' => 1,
                    'name' => 'Aprobacion interna servicio',
                    'is_active' => true,
                    'approver_type' => $approverUserId ? 'specific_user' : 'role',
                    'approver_user_id' => $approverUserId,
                    'approver_role_name' => $approverUserId ? null : 'Super Admin',
                    'require_all' => false,
                    'amount_min' => 0,
                    'amount_max' => null,
                    'notes' => 'Paso base. Ajustar aprobador desde el modulo de flujos.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $created[$documentType] = (int) $workflow->id;
        }

        return $created;
    }

    public static function createInternalApprovalRequestForRepair(
        object $repairOrder,
        string $documentType,
        ?string $notes = null
    ): ?int {
        if (! self::tableExists('approval_requests') || ! self::tableExists('approval_request_steps')) {
            return null;
        }

        $get = function (string $key, $default = null) use ($repairOrder) {
            if (method_exists($repairOrder, 'getAttribute')) {
                return $repairOrder->getAttribute($key) ?? $default;
            }

            return $repairOrder->{$key} ?? $default;
        };

        $repairOrderId = (int) ($get('id') ?: 0);

        if ($repairOrderId <= 0) {
            return null;
        }

        $companyId = (int) ($get('company_id') ?: self::currentCompanyId() ?: 1);
        $serviceCaseId = $get('service_case_id');
        $documentNumber = (string) ($get('folio') ?: ('RO-'.$repairOrderId));
        $amountTotal = self::repairOrderApprovalAmount($repairOrderId, $get('quote_total'));

        $existing = DB::table('approval_requests')
            ->where('approvable_type', \App\Models\RepairOrder::class)
            ->where('approvable_id', $repairOrderId)
            ->where('document_type', $documentType)
            ->whereIn('status', ['pending', 'sent', 'in_progress'])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $workflow = self::findServiceApprovalWorkflow(
            companyId: $companyId,
            documentType: $documentType,
            amountTotal: $amountTotal,
            preferredWorkflowId: $get('internal_approval_flow_id')
        );

        if (! $workflow) {
            return null;
        }

        $requesterId = auth()->id();
        $requesterName = auth()->user()->name ?? null;

        if (! $requesterName && $requesterId && self::tableExists('users')) {
            $requesterName = DB::table('users')->where('id', $requesterId)->value('name');
        }

        $requesterName = $requesterName ?: 'Sistema';

        return DB::transaction(function () use (
            $repairOrderId,
            $companyId,
            $serviceCaseId,
            $documentType,
            $documentNumber,
            $amountTotal,
            $workflow,
            $requesterId,
            $requesterName,
            $notes
        ): ?int {
            $approvalRequestId = DB::table('approval_requests')->insertGetId([
                'company_id' => $companyId,
                'approval_workflow_id' => $workflow->id,
                'approvable_type' => \App\Models\RepairOrder::class,
                'approvable_id' => $repairOrderId,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'requester_user_id' => $requesterId,
                'requester_name' => $requesterName,
                'status' => 'pending',
                'current_step_order' => 1,
                'amount_total' => $amountTotal,
                'sent_at' => now(),
                'completed_at' => null,
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $steps = DB::table('approval_workflow_steps')
                ->where('approval_workflow_id', $workflow->id)
                ->where('is_active', true)
                ->where(function ($query) use ($amountTotal): void {
                    $query->whereNull('amount_min')->orWhere('amount_min', '<=', $amountTotal);
                })
                ->where(function ($query) use ($amountTotal): void {
                    $query->whereNull('amount_max')->orWhere('amount_max', '>=', $amountTotal);
                })
                ->orderBy('sort_order')
                ->get();

            if ($steps->isEmpty()) {
                $steps = DB::table('approval_workflow_steps')
                    ->where('approval_workflow_id', $workflow->id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();
            }

            $firstOrder = $steps->min('sort_order') ?: 1;

            foreach ($steps as $step) {
                DB::table('approval_request_steps')->insert([
                    'approval_request_id' => $approvalRequestId,
                    'approval_workflow_step_id' => $step->id,
                    'step_order' => $step->sort_order,
                    'step_name' => $step->name,
                    'approver_type' => $step->approver_type,
                    'approver_user_id' => $step->approver_user_id,
                    'approver_role_name' => $step->approver_role_name,
                    'status' => ((int) $step->sort_order === (int) $firstOrder) ? 'pending' : 'waiting',
                    'acted_by_user_id' => null,
                    'acted_by_name' => null,
                    'acted_at' => null,
                    'comments' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (self::tableExists('repair_orders')) {
                $update = [
                    'requires_internal_approval' => true,
                    'quote_status' => 'pending_internal',
                    'internal_approval_flow_id' => $workflow->id,
                    'internal_approval_document_type' => $documentType,
                    'updated_at' => now(),
                ];

                DB::table('repair_orders')
                    ->where('id', $repairOrderId)
                    ->update($update);
            }

            self::createRepairOrderApprovalMirror(
                companyId: $companyId,
                serviceCaseId: $serviceCaseId ? (int) $serviceCaseId : null,
                repairOrderId: $repairOrderId,
                documentType: $documentType,
                amountTotal: $amountTotal,
                requesterId: $requesterId,
                notes: $notes,
                approvalRequestId: $approvalRequestId
            );

            self::logServiceApprovalEvent(
                companyId: $companyId,
                serviceCaseId: $serviceCaseId ? (int) $serviceCaseId : null,
                repairOrderId: $repairOrderId,
                documentType: $documentType,
                amountTotal: $amountTotal,
                approvalRequestId: $approvalRequestId,
                notes: $notes
            );

            return (int) $approvalRequestId;
        });
    }

    protected static function repairOrderApprovalAmount(int $repairOrderId, $quoteTotal = null): float
    {
        $amount = (float) ($quoteTotal ?: 0);

        if ($amount > 0) {
            return round($amount, 2);
        }

        if (self::tableExists('repair_order_parts') && self::hasColumn('repair_order_parts', 'total_price')) {
            $amount = (float) DB::table('repair_order_parts')
                ->where('repair_order_id', $repairOrderId)
                ->sum('total_price');
        }

        return round($amount, 2);
    }

    protected static function findServiceApprovalWorkflow(
        int $companyId,
        string $documentType,
        float $amountTotal,
        $preferredWorkflowId = null
    ): ?object {
        if (! self::tableExists('approval_workflows')) {
            return null;
        }

        $requesterUserId = auth()->id();
        $roleNames = [];

        try {
            if (auth()->check() && method_exists(auth()->user(), 'getRoleNames')) {
                $roleNames = auth()->user()->getRoleNames()->map(fn ($role): string => (string) $role)->values()->all();
            }
        } catch (\Throwable) {
            $roleNames = [];
        }

        if ($preferredWorkflowId) {
            $preferred = DB::table('approval_workflows')
                ->where('id', $preferredWorkflowId)
                ->where('is_active', true)
                ->first();

            if ($preferred) {
                return $preferred;
            }
        }

        $query = DB::table('approval_workflows')
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->where(function ($query) use ($amountTotal): void {
                $query->whereNull('amount_min')->orWhere('amount_min', '<=', $amountTotal);
            })
            ->where(function ($query) use ($amountTotal): void {
                $query->whereNull('amount_max')->orWhere('amount_max', '>=', $amountTotal);
            });

        if ($requesterUserId) {
            $query->where(function ($query) use ($requesterUserId): void {
                $query->whereNull('applies_to_user_id')
                    ->orWhere('applies_to_user_id', $requesterUserId);
            });
        } else {
            $query->whereNull('applies_to_user_id');
        }

        if ($roleNames !== []) {
            $query->where(function ($query) use ($roleNames): void {
                $query->whereNull('applies_to_role_name')
                    ->orWhereIn('applies_to_role_name', $roleNames);
            });
        } else {
            $query->whereNull('applies_to_role_name');
        }

        return $query
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->orderByRaw('CASE WHEN applies_to_user_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN applies_to_role_name IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();
    }

    protected static function createRepairOrderApprovalMirror(
        int $companyId,
        ?int $serviceCaseId,
        int $repairOrderId,
        string $documentType,
        float $amountTotal,
        ?int $requesterId,
        ?string $notes,
        int $approvalRequestId
    ): void {
        if (! self::tableExists('repair_order_approvals')) {
            return;
        }

        $existing = DB::table('repair_order_approvals')
            ->where('repair_order_id', $repairOrderId)
            ->where('approval_type', $documentType)
            ->whereIn('status', ['pending', 'sent', 'in_progress'])
            ->first();

        if ($existing) {
            return;
        }

        $data = [
            'company_id' => $companyId,
            'service_case_id' => $serviceCaseId,
            'repair_order_id' => $repairOrderId,
            'approval_type' => $documentType,
            'status' => 'pending',
            'requested_by' => $requesterId,
            'requested_at' => now(),
            'amount' => $amountTotal,
            'reason' => self::serviceApprovalDocumentTypeLabel($documentType),
            'requested_reason' => $notes,
            'comments' => $notes,
            'metadata' => json_encode([
                'approval_request_id' => $approvalRequestId,
                'document_type' => $documentType,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $insert = [];

        foreach ($data as $column => $value) {
            if (self::hasColumn('repair_order_approvals', $column)) {
                $insert[$column] = $value;
            }
        }

        if ($insert !== []) {
            DB::table('repair_order_approvals')->insert($insert);
        }
    }

    protected static function logServiceApprovalEvent(
        int $companyId,
        ?int $serviceCaseId,
        int $repairOrderId,
        string $documentType,
        float $amountTotal,
        int $approvalRequestId,
        ?string $notes
    ): void {
        if (! self::tableExists('service_case_events')) {
            return;
        }

        $data = [
            'company_id' => $companyId,
            'service_case_id' => $serviceCaseId,
            'repair_order_id' => $repairOrderId,
            'event_type' => 'approval_requested',
            'type' => 'approval_requested',
            'action' => 'approval_requested',
            'title' => 'Solicitud de aprobacion interna',
            'description' => self::serviceApprovalDocumentTypeLabel($documentType).' por $'.number_format($amountTotal, 2),
            'notes' => $notes,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'metadata' => json_encode([
                'approval_request_id' => $approvalRequestId,
                'document_type' => $documentType,
                'amount_total' => $amountTotal,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $insert = [];

        foreach ($data as $column => $value) {
            if (self::hasColumn('service_case_events', $column)) {
                $insert[$column] = $value;
            }
        }

        if ($insert !== []) {
            DB::table('service_case_events')->insert($insert);
        }
    }



    public static function employeeLaborHourRate(?int $employeeId): array
    {
        if (! $employeeId || ! self::tableExists('employees')) {
            return [
                'rate' => null,
                'source' => null,
            ];
        }

        $employeeColumns = [
            'service_labor_hour_rate',
            'service_hourly_cost',
            'labor_hour_rate',
            'labor_cost_hour',
            'cost_per_hour',
            'hourly_cost',
            'hourly_rate',
            'standard_hour_cost',
            'accounting_hour_cost',
        ];

        try {
            $row = DB::table('employees')->where('id', $employeeId)->first();

            if (! $row) {
                return [
                    'rate' => null,
                    'source' => null,
                ];
            }

            foreach ($employeeColumns as $column) {
                if (! self::hasColumn('employees', $column)) {
                    continue;
                }

                $value = $row->{$column} ?? null;

                if ($value !== null && $value !== '' && is_numeric($value) && (float) $value > 0) {
                    return [
                        'rate' => round((float) $value, 2),
                        'source' => 'employees.'.$column,
                    ];
                }
            }

            return [
                'rate' => null,
                'source' => 'manual',
            ];
        } catch (\Throwable) {
            return [
                'rate' => null,
                'source' => null,
            ];
        }
    }



    public static function hasActiveServiceApprovalWorkflowForRepair(
        object $repairOrder,
        string $documentType = 'service_repair_quote_internal'
    ): bool {
        if (! self::tableExists('approval_workflows')) {
            return false;
        }

        $get = function (string $key, $default = null) use ($repairOrder) {
            if (method_exists($repairOrder, 'getAttribute')) {
                return $repairOrder->getAttribute($key) ?? $default;
            }

            return $repairOrder->{$key} ?? $default;
        };

        $companyId = (int) ($get('company_id') ?: self::currentCompanyId() ?: 1);
        $repairOrderId = (int) ($get('id') ?: 0);
        $amountTotal = (float) ($get('quote_total') ?: 0);

        if ($amountTotal <= 0 && $repairOrderId > 0 && self::tableExists('repair_order_parts')) {
            $amountTotal = (float) DB::table('repair_order_parts')
                ->where('repair_order_id', $repairOrderId)
                ->sum('total_price');
        }

        $query = DB::table('approval_workflows')
            ->where('document_type', $documentType);

        if (self::hasColumn('approval_workflows', 'is_active')) {
            $query->where('is_active', true);
        }

        if (self::hasColumn('approval_workflows', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        if (self::hasColumn('approval_workflows', 'amount_min')) {
            $query->where(function ($query) use ($amountTotal): void {
                $query->whereNull('amount_min')->orWhere('amount_min', '<=', $amountTotal);
            });
        }

        if (self::hasColumn('approval_workflows', 'amount_max')) {
            $query->where(function ($query) use ($amountTotal): void {
                $query->whereNull('amount_max')->orWhere('amount_max', '>=', $amountTotal);
            });
        }

        if (self::tableExists('approval_workflow_steps')) {
            $query->whereExists(function ($subQuery): void {
                $subQuery->from('approval_workflow_steps')
                    ->selectRaw('1')
                    ->whereColumn('approval_workflow_steps.approval_workflow_id', 'approval_workflows.id');

                if (self::hasColumn('approval_workflow_steps', 'is_active')) {
                    $subQuery->where('approval_workflow_steps.is_active', true);
                }
            });
        }

        return $query->exists();
    }




    public static function approvalWorkflowDocumentTypeOptions(): array
    {
        return [
            'payroll_run' => 'Nómina',
            'payroll_pre_approval' => 'Aprobación de pre-nómina',

            'purchase_request' => 'Solicitud de compra',
            'purchase_order' => 'Orden de compra',

            'sales_quote' => 'Cotización de venta',
            'sales_order' => 'Pedido / orden de venta',
            'sales_margin_approval' => 'Aprobación de margen de venta',

            'treasury_cash_transfer_request' => 'Solicitud de efectivo / retiro PDV',

            'service_repair_parts_request' => 'Solicitud de refacciones/materiales para reparación',
            'service_repair_quote_internal' => 'Presupuesto de reparación / servicio',
            'service_repair_warranty' => 'Validación de garantía de servicio',
            'service_repair_delivery' => 'Autorización de entrega de reparación',
        ];
    }

    public static function approvalWorkflowDocumentTypeLabel(?string $documentType): string
    {
        if (! $documentType) {
            return 'Sin documento';
        }

        $aliases = [
            'payroll_pre_nomina' => 'payroll_pre_approval',
            'pre_nomina' => 'payroll_pre_approval',

            'cash_request' => 'treasury_cash_transfer_request',
            'pdv_cash_request' => 'treasury_cash_transfer_request',
            'cash_withdrawal' => 'treasury_cash_transfer_request',
        ];

        $canonical = $aliases[$documentType] ?? $documentType;
        $options = self::approvalWorkflowDocumentTypeOptions();

        return $options[$canonical]
            ?? ucfirst(str_replace('_', ' ', (string) $documentType));
    }




    public static function approvalWorkflowUserOptions(?string $search = null): array
    {
        if (! self::tableExists('users')) {
            return [];
        }

        try {
            $query = DB::table('users')->select('users.*');

            self::scopeUsersToCurrentCompanyGroup($query);

            if ($search !== null && trim($search) !== '') {
                $search = mb_strtolower(trim($search));

                $query->where(function ($query) use ($search): void {
                    foreach (['name', 'email', 'username'] as $column) {
                        if (self::hasColumn('users', $column)) {
                            $query->orWhereRaw('LOWER(users.'.$column.') LIKE ?', ['%'.$search.'%']);
                        }
                    }
                });
            }

            if (self::hasColumn('users', 'name')) {
                $query->orderBy('users.name');
            } else {
                $query->orderBy('users.id');
            }

            return $query
                ->limit(50)
                ->get()
                ->mapWithKeys(function ($row): array {
                    $id = (int) ($row->id ?? 0);

                    if ($id <= 0) {
                        return [];
                    }

                    return [$id => self::approvalWorkflowUserLabel($id)];
                })
                ->filter()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function approvalWorkflowUserLabel(?int $userId): ?string
    {
        if (! $userId || ! self::tableExists('users')) {
            return null;
        }

        try {
            $row = DB::table('users')->where('id', $userId)->first();

            if (! $row) {
                return null;
            }

            $name = self::valueFromRow($row, ['name', 'username', 'email']) ?: ('Usuario #'.$userId);
            $email = self::valueFromRow($row, ['email']);

            return $email && $email !== $name
                ? $name.' - '.$email
                : $name;
        } catch (\Throwable) {
            return 'Usuario #'.$userId;
        }
    }

    protected static function scopeUsersToCurrentCompanyGroup($query): void
    {
        $companyIds = self::companyGroupCompanyIds();
        $currentCompanyId = self::currentCompanyId();

        if ($companyIds === [] && $currentCompanyId) {
            $companyIds = [$currentCompanyId];
        }

        if ($companyIds === []) {
            // En contexto sin tenant no mostramos todos para evitar fugas entre empresas.
            $query->whereRaw('1 = 0');
            return;
        }

        if (self::hasColumn('users', 'company_id')) {
            $query->whereIn('users.company_id', $companyIds);
            return;
        }

        foreach ([
            'company_user' => ['user_id', 'company_id'],
            'company_users' => ['user_id', 'company_id'],
            'user_companies' => ['user_id', 'company_id'],
            'company_user_roles' => ['user_id', 'company_id'],
            'model_has_companies' => ['model_id', 'company_id'],
        ] as $table => [$userColumn, $companyColumn]) {
            if (! self::tableExists($table) || ! self::hasColumn($table, $userColumn) || ! self::hasColumn($table, $companyColumn)) {
                continue;
            }

            $query->whereExists(function ($sub) use ($table, $userColumn, $companyColumn, $companyIds): void {
                $sub->from($table)
                    ->selectRaw('1')
                    ->whereColumn($table.'.'.$userColumn, 'users.id')
                    ->whereIn($table.'.'.$companyColumn, $companyIds);
            });

            return;
        }

        // Si no existe relación detectable, mostramos solo el usuario actual si aplica.
        $authId = auth()->id();

        if ($authId) {
            $query->where('users.id', $authId);
            return;
        }

        $query->whereRaw('1 = 0');
    }

    protected static function valueFromRow(object $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (property_exists($row, $column) && filled($row->{$column})) {
                return (string) $row->{$column};
            }
        }

        return null;
    }


    public static function resolveServiceApprovalWorkflowForRepair(
        object $repairOrder,
        string $documentType = 'service_repair_quote_internal'
    ): ?object {
        if (! self::tableExists('approval_workflows')) {
            return null;
        }

        $companyId = (int) (self::serviceRepairValue($repairOrder, 'company_id') ?: self::currentCompanyId() ?: 1);
        $repairOrderId = (int) (self::serviceRepairValue($repairOrder, 'id') ?: 0);
        $amountTotal = (float) (self::serviceRepairValue($repairOrder, 'quote_total') ?: 0);

        if ($amountTotal <= 0 && $repairOrderId > 0 && self::tableExists('repair_order_parts')) {
            $amountTotal = (float) DB::table('repair_order_parts')
                ->where('repair_order_id', $repairOrderId)
                ->sum('total_price');
        }

        $query = DB::table('approval_workflows')
            ->where('document_type', $documentType);

        if (self::hasColumn('approval_workflows', 'is_active')) {
            $query->where('is_active', true);
        }

        if (self::hasColumn('approval_workflows', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        if (self::hasColumn('approval_workflows', 'amount_min')) {
            $query->where(function ($query) use ($amountTotal): void {
                $query->whereNull('amount_min')->orWhere('amount_min', '<=', $amountTotal);
            });
        }

        if (self::hasColumn('approval_workflows', 'amount_max')) {
            $query->where(function ($query) use ($amountTotal): void {
                $query->whereNull('amount_max')->orWhere('amount_max', '>=', $amountTotal);
            });
        }

        if (self::tableExists('approval_workflow_steps')) {
            $query->whereExists(function ($subQuery): void {
                $subQuery->from('approval_workflow_steps')
                    ->selectRaw('1')
                    ->whereColumn('approval_workflow_steps.approval_workflow_id', 'approval_workflows.id');

                if (self::hasColumn('approval_workflow_steps', 'is_active')) {
                    $subQuery->where('approval_workflow_steps.is_active', true);
                }
            });
        }

        if (self::hasColumn('approval_workflows', 'priority')) {
            $query->orderBy('priority');
        }

        return $query->orderByDesc('id')->first();
    }

    public static function canSendRepairQuoteToApproval(object $repairOrder): bool
    {
        $stage = (string) (self::serviceRepairValue($repairOrder, 'workflow_stage') ?: '');
        $quoteStatus = (string) (self::serviceRepairValue($repairOrder, 'quote_status') ?: '');
        $quoteTotal = (float) (self::serviceRepairValue($repairOrder, 'quote_total') ?: 0);

        if ($stage !== 'quote_draft') {
            return false;
        }

        if ($quoteStatus !== 'draft') {
            return false;
        }

        if ($quoteTotal <= 0) {
            return false;
        }

        if (self::repairHasOpenQuoteApprovalRequest($repairOrder)) {
            return false;
        }

        return self::resolveServiceApprovalWorkflowForRepair($repairOrder, 'service_repair_quote_internal') !== null;
    }

    public static function repairHasOpenQuoteApprovalRequest(object $repairOrder): bool
    {
        $repairOrderId = (int) (self::serviceRepairValue($repairOrder, 'id') ?: 0);

        if ($repairOrderId <= 0 || ! self::tableExists('approval_requests')) {
            return false;
        }

        if (! self::hasColumn('approval_requests', 'approvable_id')) {
            return false;
        }

        $query = DB::table('approval_requests')
            ->where('approvable_id', $repairOrderId);

        if (self::hasColumn('approval_requests', 'approvable_type')) {
            $query->where('approvable_type', \App\Models\RepairOrder::class);
        }

        if (self::hasColumn('approval_requests', 'document_type')) {
            $query->where('document_type', 'service_repair_quote_internal');
        }

        if (self::hasColumn('approval_requests', 'status')) {
            $query->whereNotIn('status', [
                'approved',
                'rejected',
                'cancelled',
                'canceled',
                'done',
            ]);
        }

        return $query->exists();
    }

    protected static function serviceRepairValue(object $repairOrder, string $key): mixed
    {
        if (method_exists($repairOrder, 'getAttribute')) {
            return $repairOrder->getAttribute($key);
        }

        return $repairOrder->{$key} ?? null;
    }



    public static function repairOrderCoreFieldsLocked(?object $repairOrder): bool
    {
        if (! $repairOrder) {
            return false;
        }

        $stage = (string) ($repairOrder->workflow_stage ?? '');

        return in_array($stage, [
            'pending_approval',
            'quote_approved',
            'in_repair',
            'repaired',
            'supervisor_review',
            'ready_for_delivery',
            'delivered',
            'cancelled',
        ], true);
    }



    public static function repairSupervisorReviewConfigured(?object $repairOrder = null): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('approval_workflows')) {
            return false;
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('approval_workflows');

        if (! in_array('document_type', $columns, true)) {
            return false;
        }

        $query = \Illuminate\Support\Facades\DB::table('approval_workflows')
            ->where('document_type', 'service_repair_supervisor_review');

        if ($repairOrder && in_array('company_id', $columns, true) && ! empty($repairOrder->company_id)) {
            $query->where(function ($q) use ($repairOrder) {
                $q->where('company_id', $repairOrder->company_id)
                    ->orWhereNull('company_id');
            });
        }

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        if (in_array('active', $columns, true)) {
            $query->where('active', true);
        }

        if (in_array('status', $columns, true)) {
            $query->whereIn('status', ['active', 'enabled', 'activo']);
        }

        return $query->exists();
    }

    public static function canSendRepairToSupervisorReview(?object $repairOrder): bool
    {
        if (! $repairOrder) {
            return false;
        }

        if ((string) ($repairOrder->workflow_stage ?? '') !== 'repaired') {
            return false;
        }

        return self::repairSupervisorReviewConfigured($repairOrder);
    }



    public static function canDeleteRepairDraft(?object $repairOrder): bool
    {
        if (! $repairOrder) {
            return false;
        }

        $stage = (string) ($repairOrder->workflow_stage ?? '');
        $quoteStatus = (string) ($repairOrder->quote_status ?? '');
        $status = (string) ($repairOrder->status ?? '');

        return $stage === 'quote_draft'
            || ($stage === '' && $quoteStatus === 'draft')
            || ($stage === '' && $status === 'draft');
    }



    public static function currentServiceRoleNames(): array
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'getRoleNames')) {
            return [];
        }

        self::setPermissionTeam();

        try {
            return $user->getRoleNames()
                ->map(fn ($role): string => (string) $role)
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function hasServiceRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return array_intersect(
            $roles,
            self::currentServiceRoleNames()
        ) !== [];
    }

    public static function currentUserEmployeeId(): ?int
    {
        $user = auth()->user();

        if (! $user || ! self::tableExists('employees')) {
            return null;
        }

        try {
            if (self::hasColumn('employees', 'user_id')) {
                $id = (int) (
                    DB::table('employees')
                        ->where('user_id', $user->getKey())
                        ->value('id') ?: 0
                );

                if ($id > 0) {
                    return $id;
                }
            }

            $email = mb_strtolower(
                trim((string) ($user->email ?? ''))
            );

            if ($email === '') {
                return null;
            }

            foreach (['email', 'work_email'] as $column) {
                if (! self::hasColumn('employees', $column)) {
                    continue;
                }

                $id = (int) (
                    DB::table('employees')
                        ->whereRaw(
                            'LOWER(' . $column . ') = ?',
                            [$email]
                        )
                        ->value('id') ?: 0
                );

                if ($id > 0) {
                    return $id;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public static function isAssignedRepairTechnician(
        ?object $repairOrder
    ): bool {
        if (! $repairOrder) {
            return false;
        }

        $employeeId = self::currentUserEmployeeId();

        return $employeeId
            && (int) self::serviceRepairValue(
                $repairOrder,
                'assigned_employee_id'
            ) === $employeeId;
    }

    public static function scopeRepairOrdersForCurrentUser(
        Builder $query
    ): void {
        if (! self::hasServiceRole('Servicio - Técnico')) {
            return;
        }

        if (self::hasServiceRole([
            'Servicio - Supervisor',
            'Servicio - Encargado de Técnicos',
            'Servicio - Recepción',
            'Servicio - Cajero Reparaciones',
        ])) {
            return;
        }

        $employeeId = self::currentUserEmployeeId();

        if (! $employeeId) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where('assigned_employee_id', $employeeId);
    }

    public static function canViewRepairOrder(
        ?object $repairOrder
    ): bool {
        if (! self::can([
            'service.repairs.view',
            'service.repairs.update',
        ])) {
            return false;
        }

        if (! self::hasServiceRole('Servicio - Técnico')) {
            return true;
        }

        return self::isAssignedRepairTechnician($repairOrder);
    }

    public static function canEditRepairOrder(
        ?object $repairOrder
    ): bool {
        return self::can('service.repairs.update')
            && self::canViewRepairOrder($repairOrder);
    }

    public static function canSubmitRepairQuote(
        ?object $repairOrder
    ): bool {
        return $repairOrder
            && self::can('service.repairs.quote.submit')
            && self::canSendRepairQuoteToApproval($repairOrder);
    }

    public static function repairQuoteApprovalStatus(
        ?object $repairOrder
    ): ?string {
        if (
            ! $repairOrder
            || ! self::tableExists('approval_requests')
        ) {
            return null;
        }

        $repairOrderId = (int) self::serviceRepairValue(
            $repairOrder,
            'id'
        );

        if ($repairOrderId <= 0) {
            return null;
        }

        try {
            $query = DB::table('approval_requests')
                ->where('approvable_id', $repairOrderId);

            if (self::hasColumn(
                'approval_requests',
                'approvable_type'
            )) {
                $query->where(
                    'approvable_type',
                    \App\Models\RepairOrder::class
                );
            }

            if (self::hasColumn(
                'approval_requests',
                'document_type'
            )) {
                $query->where(
                    'document_type',
                    'service_repair_quote_internal'
                );
            }

            return $query->orderByDesc('id')->value('status');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function canConfirmRepairQuoteApproval(
        ?object $repairOrder
    ): bool {
        return $repairOrder
            && (string) self::serviceRepairValue(
                $repairOrder,
                'workflow_stage'
            ) === 'pending_approval'
            && self::can('service.repairs.quote.approve')
            && in_array(
                strtolower((string) self::repairQuoteApprovalStatus(
                    $repairOrder
                )),
                ['approved', 'aprobado'],
                true
            );
    }

    public static function canWorkRepair(
        ?object $repairOrder
    ): bool {
        if (
            ! $repairOrder
            || ! self::can('service.repairs.work')
        ) {
            return false;
        }

        if (self::hasServiceRole('Servicio - Técnico')) {
            return self::isAssignedRepairTechnician($repairOrder);
        }

        return self::hasServiceRole([
            'Servicio - Supervisor',
            'Servicio - Encargado de Técnicos',
        ]) || self::can('company.update');
    }

    public static function canRequestRepairSupervisorReview(
        ?object $repairOrder
    ): bool {
        return self::canWorkRepair($repairOrder)
            && self::canSendRepairToSupervisorReview(
                $repairOrder
            );
    }

    public static function canApproveRepairSupervisorReview(
        ?object $repairOrder
    ): bool {
        return $repairOrder
            && (string) self::serviceRepairValue(
                $repairOrder,
                'workflow_stage'
            ) === 'supervisor_review'
            && self::can(
                'service.repairs.supervisor_review.approve'
            );
    }

    public static function canMarkRepairReadyForDelivery(
        ?object $repairOrder
    ): bool {
        return $repairOrder
            && (string) self::serviceRepairValue(
                $repairOrder,
                'workflow_stage'
            ) === 'repaired'
            && ! self::repairSupervisorReviewConfigured(
                $repairOrder
            )
            && self::can(
                'service.repairs.supervisor_review.approve'
            );
    }

    public static function canDeliverRepair(
        ?object $repairOrder
    ): bool {
        return $repairOrder
            && (string) self::serviceRepairValue(
                $repairOrder,
                'workflow_stage'
            ) === 'ready_for_delivery'
            && self::can('service.repairs.delivery');
    }

    public static function canManageRepairEconomic(
        ?object $repairOrder = null
    ): bool {
        return self::can('service.repairs.economic');
    }

    public static function canManageRepairPublicTracking(): bool
    {
        return self::hasServiceRole([
            'Servicio - Supervisor',
            'Servicio - Encargado de Técnicos',
        ]) || self::can('company.update');
    }

    public static function canCaptureRepairReception(): bool
    {
        return self::hasServiceRole([
            'Servicio - Recepción',
            'Servicio - Encargado de Técnicos',
            'Servicio - Supervisor',
        ]) || self::can('company.update');
    }

}
