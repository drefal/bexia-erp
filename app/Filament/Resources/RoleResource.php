<?php

namespace App\Filament\Resources;

use App\Support\PermissionLabels;
use App\Filament\Resources\RoleResource\Pages;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// BEXIA_ROLE_RESOURCE_RESPONSIVE_V5_79_28B
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    protected static bool $isScopedToTenant = false;
    protected static ?string $tenantOwnershipRelationshipName = null;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Seguridad';
    protected static ?int $navigationSort = 10;

public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('roles.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('roles.manage');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->check() && auth()->user()->can('roles.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('roles.manage');
    }

    public static function getNavigationLabel(): string
    {
        return 'Roles';
    }

    public static function getModelLabel(): string
    {
        return 'Rol';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Roles';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->withCount('permissions')
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }
public static function shouldRegisterNavigation(): bool
{
    return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
        'resources.roleresource',
        fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
    );
}

protected static function bexiaBaseShouldRegisterNavigation(): bool
{
    $user = auth()->user();

    return (bool) (
        $user &&
        (
            (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) ||
            $user->can('roles.view') ||
            $user->can('rol.view')
        )
    );
}

public static function canViewAny(): bool
{
    $user = auth()->user();

    return (bool) (
        $user &&
        (
            (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) ||
$user->can('roles.view')
        )
    );
}

    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre del rol')
                ->required()
                ->maxLength(255)
                ->extraAttributes(['class' => 'bexia-role-field bexia-role-name-field']),

            Forms\Components\Select::make('company_ids')
                ->label('Empresas')
                ->options(fn () => Company::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->multiple()
                ->searchable()
                ->preload()
                ->native(false)
                ->visible(fn (string $operation): bool => auth()->user()?->isSystemAdmin() && $operation === 'create')
                ->required(fn (string $operation): bool => auth()->user()?->isSystemAdmin() && $operation === 'create')
                ->helperText('Como superadmin, puedes replicar este rol en varias empresas.')
                ->extraAttributes(['class' => 'bexia-role-field bexia-role-company-ids-field']),

            Forms\Components\Select::make('company_id')
                ->label('Empresa')
                ->options(function () use ($tenantId) {
                    return Company::query()
                        ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->default($tenantId)
                ->required()
                ->searchable()
                ->preload()
                ->native(false)
                ->visible(fn (string $operation): bool => ! (auth()->user()?->isSystemAdmin() && $operation === 'create'))
                ->dehydrated(fn (string $operation): bool => ! (auth()->user()?->isSystemAdmin() && $operation === 'create'))
                ->extraAttributes(['class' => 'bexia-role-field bexia-role-company-field']),

            Forms\Components\Placeholder::make('permission_guide')
                ->label('Guía rápida de permisos')
                ->extraAttributes(['class' => 'bexia-role-permission-guide-wrapper'])
                ->content(new \Illuminate\Support\HtmlString('
                    <div class="bexia-role-permission-guide">
                        <div><strong>Ver empresas</strong>: permite entrar al módulo de empresas y consultar su información.</div>
                        <div><strong>Editar empresas</strong>: permite crear o modificar datos de empresas.</div>
                        <div><strong>Ver usuarios</strong>: permite ver los usuarios de la empresa actual.</div>
                        <div><strong>Crear usuarios</strong>: permite registrar nuevos usuarios.</div>
                        <div><strong>Editar usuarios</strong>: permite modificar usuarios existentes.</div>
                        <div><strong>Eliminar usuarios</strong>: permite eliminar usuarios.</div>
                        <div><strong>Ver salidas</strong>: permite entrar al módulo de salidas.</div>
                        <div><strong>Crear salidas</strong>: permite registrar nuevas salidas.</div>
                        <div><strong>Editar salidas</strong>: permite modificar salidas existentes.</div>
                        <div><strong>Eliminar salidas</strong>: permite borrar salidas.</div>
                        <div><strong>Enviar PDF</strong>: permite enviar el PDF de una salida.</div>
                        <div><strong>Ver todas las salidas</strong>: permite ver salidas de todos los usuarios, no solo las propias.</div>
                        <div><strong>Ver roles</strong>: permite ver el módulo de roles.</div>
                        <div><strong>Administrar roles</strong>: permite crear, editar y eliminar roles.</div>
                        <div><strong>Ver accesos</strong>: permite consultar accesos o permisos directos.</div>
                        <div><strong>Editar accesos</strong>: permite modificar accesos o permisos directos.</div>
                        <div><strong>Acceso a configuración</strong>: permite entrar a opciones avanzadas de configuración.</div>
                    </div>
                '))
                ->columnSpanFull(),

            Forms\Components\Group::make()
                ->schema(static::permissionModuleSections())
                ->columnSpanFull(),
        ])->columns(2);
    }


    /**
     * Construye la UI de permisos sin modificar el modelo de permisos.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function permissionModuleSections(): array
    {
        $permissions = \Spatie\Permission\Models\Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $modules = [];

        foreach ($permissions as $permission) {
            [$module, $resource] = static::permissionModuleResource(
                (string) $permission->name
            );

            if (! isset($modules[$module])) {
                $modules[$module] = [
                    'label' => static::permissionModuleLabel($module),
                    'sort' => static::permissionModuleSort($module),
                    'resources' => [],
                ];
            }

            if (! isset($modules[$module]['resources'][$resource])) {
                $modules[$module]['resources'][$resource] = [
                    'label' => static::permissionResourceLabel(
                        $module,
                        $resource
                    ),
                    'field' => static::permissionGroupFieldName(
                        $module,
                        $resource
                    ),
                    'options' => [],
                ];
            }

            $modules[$module]['resources'][$resource]['options'][
                (int) $permission->id
            ] = PermissionLabels::label(
                (string) $permission->name
            ) . ' (' . $permission->name . ')';
        }

        uasort(
            $modules,
            static function (array $a, array $b): int {
                return [
                    $a['sort'],
                    $a['label'],
                ] <=> [
                    $b['sort'],
                    $b['label'],
                ];
            }
        );

        $sections = [];

        foreach ($modules as $moduleData) {
            uasort(
                $moduleData['resources'],
                static fn (array $a, array $b): int =>
                    $a['label'] <=> $b['label']
            );

            $resourceComponents = [];
            $modulePermissionCount = 0;
            $moduleStateFields = [];

            foreach ($moduleData['resources'] as $resourceData) {
                $modulePermissionCount += count(
                    $resourceData['options']
                );

                $moduleStateFields[] =
                    $resourceData['field'];

                $resourceComponents[] =
                    Forms\Components\Fieldset::make(
                        $resourceData['label']
                    )
                    ->schema([
                        Forms\Components\CheckboxList::make(
                            $resourceData['field']
                        )
                            ->label('Permisos')
                            ->options(
                                $resourceData['options']
                            )
                            ->columns(2)
                            ->searchable()
                            ->live()
                            ->bulkToggleable(),
                    ])
                    ->columns(1);
            }

            $sections[] =
                Forms\Components\Section::make(
                    $moduleData['label']
                )
                ->description(
                    static function (
                        Forms\Get $get
                    ) use (
                        $modulePermissionCount,
                        $moduleStateFields
                    ): string {
                        $selectedCount = 0;

                        foreach ($moduleStateFields as $field) {
                            $value = $get($field);

                            if (! is_array($value)) {
                                continue;
                            }

                            $selectedCount += count(
                                array_filter(
                                    $value,
                                    static fn ($id): bool =>
                                        is_numeric($id)
                                )
                            );
                        }

                        return $modulePermissionCount
                            . ' permisos / '
                            . $selectedCount
                            . ' seleccionados';
                    }
                )
                ->schema($resourceComponents)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull();
        }

        return $sections;
    }

    /**
     * Carga los IDs seleccionados en cada grupo visual.
     */
    public static function hydratePermissionGroupState(
        array $data,
        array $permissionIds
    ): array {
        $selected = array_fill_keys(
            array_map('intval', $permissionIds),
            true
        );

        $groupedIds = static::permissionIdsByGroup();

        foreach ($groupedIds as $field => $ids) {
            $data[$field] = array_values(
                array_filter(
                    $ids,
                    static fn (int $id): bool =>
                        isset($selected[$id])
                )
            );
        }

        unset($data['permission_ids']);

        return $data;
    }

    /**
     * Obtiene todos los IDs elegidos y elimina los campos virtuales
     * antes de guardar el modelo Role.
     */
    public static function extractPermissionIds(
        array &$data
    ): array {
        $permissionIds = [];

        if (isset($data['permission_ids'])) {
            $permissionIds = array_merge(
                $permissionIds,
                (array) $data['permission_ids']
            );

            unset($data['permission_ids']);
        }

        foreach (array_keys($data) as $key) {
            if (! str_starts_with(
                (string) $key,
                'permission_group_'
            )) {
                continue;
            }

            $permissionIds = array_merge(
                $permissionIds,
                (array) $data[$key]
            );

            unset($data[$key]);
        }

        $permissionIds = array_values(
            array_unique(
                array_map(
                    'intval',
                    array_filter(
                        $permissionIds,
                        static fn ($id): bool =>
                            is_numeric($id)
                    )
                )
            )
        );

        sort($permissionIds);

        return $permissionIds;
    }

    /**
     * @return array<string, array<int, int>>
     */
    protected static function permissionIdsByGroup(): array
    {
        $groups = [];

        $permissions = \Spatie\Permission\Models\Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->get(['id', 'name']);

        foreach ($permissions as $permission) {
            [$module, $resource] = static::permissionModuleResource(
                (string) $permission->name
            );

            $field = static::permissionGroupFieldName(
                $module,
                $resource
            );

            $groups[$field][] = (int) $permission->id;
        }

        return $groups;
    }

    /**
     * @return array{0:string,1:string}
     */
    protected static function permissionModuleResource(
        string $permission
    ): array {
        $parts = explode('.', $permission);

        $module = (string) ($parts[0] ?? 'otros');

        $module = match ($module) {
            'hr' => 'rrhh',
            'payroll' => 'nomina',
            'rol' => 'roles',
            'billing' => 'invoicing',
            default => $module,
        };

        $resource = count($parts) >= 3
            ? (string) $parts[1]
            : 'general';

        return [
            $module,
            $resource,
        ];
    }

    protected static function permissionGroupFieldName(
        string $module,
        string $resource
    ): string {
        return 'permission_group_'
            . substr(
                sha1($module . '|' . $resource),
                0,
                16
            );
    }

    protected static function permissionModuleLabel(
        string $module
    ): string {
        return [
            'accounting' => 'Contabilidad',
            'account_payables' => 'Cuentas por pagar',
            'account_receivable_payments' => 'Cobros de cuentas por cobrar',
            'account_receivables' => 'Cuentas por cobrar',
            'ai_insights' => 'IA / Insights',
            'approvals' => 'Aprobaciones',
            'bexia' => 'Bexia',
            'catalogs' => 'Catálogos',
            'company' => 'Empresas',
            'contacts' => 'Contactos',
            'dashboard' => 'Inicio / Dashboard',
            'inventory' => 'Inventario',
            'invoicing' => 'Facturación',
            'nomina' => 'Nómina',
            'payment_terms' => 'Condiciones de pago',
            'pos' => 'Punto de venta',
            'purchase_requests' => 'Solicitudes de compra',
            'purchases' => 'Compras',
            'reports' => 'Reportes',
            'roles' => 'Roles',
            'rrhh' => 'RRHH',
            'sales' => 'Ventas',
            'salidas' => 'Salidas',
            'server_monitor' => 'Monitor del servidor',
            'service' => 'Atención y Servicio',
            'settings' => 'Configuración',
            'treasury' => 'Tesorería',
            'user_access' => 'Accesos de usuario',
            'users' => 'Usuarios',
        ][$module]
            ?? \Illuminate\Support\Str::of($module)
                ->replace('_', ' ')
                ->headline()
                ->toString();
    }

    protected static function permissionModuleSort(
        string $module
    ): int {
        $order = [
            'dashboard',
            'company',
            'users',
            'roles',
            'rrhh',
            'nomina',
            'sales',
            'salidas',
            'purchases',
            'purchase_requests',
            'inventory',
            'pos',
            'contacts',
            'invoicing',
            'account_receivables',
            'account_receivable_payments',
            'account_payables',
            'accounting',
            'treasury',
            'service',
            'reports',
            'approvals',
            'catalogs',
            'payment_terms',
            'ai_insights',
            'server_monitor',
            'settings',
            'bexia',
        ];

        $position = array_search(
            $module,
            $order,
            true
        );

        return $position === false
            ? 900
            : $position;
    }

    protected static function permissionResourceLabel(
        string $module,
        string $resource
    ): string {
        $labels = [
            'rrhh' => [
                'general' => 'General',
                'menu' => 'Acceso al módulo',
                'empleados' => 'Empleados',
                'departamentos' => 'Departamentos',
                'expediente' => 'Expedientes',
                'vacaciones' => 'Vacaciones / Saldos',
                'contratos' => 'Contratos',
                'bajas' => 'Bajas',
                'organigrama' => 'Organigrama',
                'puestos' => 'Puestos',
                'incidencias' => 'Incidencias',
                'asistencias' => 'Asistencias',
                'geocercas' => 'Geocercas de asistencia',
                'catalogos' => 'Configuración general',
                'tipos_documento' => 'Tipos de documento',
                'tipos_incidencia' => 'Tipos de incidencia',
                'terminales' => 'Terminales de asistencia',
                'credenciales' => 'Credenciales QR',
                'horarios' => 'Horarios',
            ],

            'nomina' => [
                'general' => 'General',
                'menu' => 'Acceso al módulo',
                'catalogos' => 'Catálogos de nómina',
                'compras_via_nomina' => 'Compras vía nómina',
                'conceptos' => 'Conceptos de nómina',
                'descuentos' => 'Descuentos de empleados',
                'percepciones' => 'Percepciones de empleados',
                'politicas' => 'Políticas de nómina',
                'prenomina' => 'Pre-nómina',
                'procesos' => 'Procesos de nómina',
                'recibos_cfdi' => 'Recibos CFDI nómina',
            ],

            'inventory' => [
                'general' => 'General',
                'menu' => 'Acceso al módulo',
                'adjustments' => 'Ajustes de inventario',
                'as_of_date' => 'Inventario a fecha',
                'costing_diagnostic' => 'Diagnóstico de costos',
                'kardex' => 'Kardex',
                'locations' => 'Ubicaciones',
                'location_types' => 'Tipos de ubicación',
                'lots' => 'Lotes',
                'movements' => 'Movimientos',
                'operation_types' => 'Tipos de operación',
                'product_attributes' => 'Atributos de producto',
                'product_categories' => 'Categorías de producto',
                'products' => 'Productos',
                'serials' => 'Series',
                'stock' => 'Existencias',
                'traceability' => 'Trazabilidad',
                'valuation' => 'Valuación',
                'warehouses' => 'Almacenes',
            ],

            'pos' => [
                'general' => 'Operación general',
                'menu' => 'Acceso al módulo',
                'audit' => 'Auditoría',
                'discount' => 'Descuentos',
                'pending_tickets' => 'Tickets pendientes',
                'refund' => 'Devoluciones',
                'refunds' => 'Devoluciones',
                'session' => 'Sesión',
                'sessions' => 'Sesiones',
                'ticket' => 'Tickets',
            ],

            'service' => [
                'general' => 'General',
                'menu' => 'Acceso al módulo',
                'cases' => 'Tickets de servicio',
                'events' => 'Bitácora de servicio',
                'repairs' => 'Reparaciones',
            ],

            'catalogs' => [
                'general' => 'General',
                'menu' => 'Acceso al módulo',
                'fiscal' => 'Catálogos fiscales',
            ],
        ];

        if (isset($labels[$module][$resource])) {
            return $labels[$module][$resource];
        }

        return match ($resource) {
            'general' => 'General',
            'menu' => 'Acceso al módulo',
            default => \Illuminate\Support\Str::of($resource)
                ->replace('_', ' ')
                ->headline()
                ->toString(),
        };
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rol')
                    ->sortable()
                    ->searchable()
                    ->extraHeaderAttributes(['class' => 'bexia-role-col-name'])
                    ->extraCellAttributes(['class' => 'bexia-role-col-name'])
                    ->wrap(),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->formatStateUsing(fn ($state) => Company::find($state)?->name ?? '—')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'bexia-role-col-company'])
                    ->extraCellAttributes(['class' => 'bexia-role-col-company'])
                    ->wrap(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('# Permisos')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'bexia-role-col-permissions'])
                    ->extraCellAttributes(['class' => 'bexia-role-col-permissions']),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'bexia-role-col-updated'])
                    ->extraCellAttributes(['class' => 'bexia-role-col-updated']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->check() && auth()->user()->can('roles.manage')),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn () => auth()->check() && auth()->user()->can('roles.manage')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
