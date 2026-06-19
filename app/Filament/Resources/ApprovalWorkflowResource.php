<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovalWorkflowResource\Pages;
use App\Models\ApprovalWorkflow;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalWorkflowResource extends Resource
{
    
    protected static ?string $model = ApprovalWorkflow::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Configuración empresa';

    protected static ?string $navigationLabel = 'Flujos de aprobación';

    protected static ?string $modelLabel = 'flujo de aprobación';

    protected static ?string $pluralModelLabel = 'flujos de aprobación';

    protected static ?int $navigationSort = 20;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withCount('steps');

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('approval_workflows', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.approvalworkflowresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('settings.access')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('settings.access')
            );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Datos del flujo')
                    ->description('Define cuándo aplica este flujo. Bexia elegirá el flujo activo más específico y con mayor prioridad.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del flujo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\Select::make('document_type')
                            ->label('Tipo de documento')
                            ->options(fn (): array => \App\Support\Service\ServiceAccess::approvalWorkflowDocumentTypeOptions())
                            ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? \App\Support\Service\ServiceAccess::approvalWorkflowDocumentTypeLabel((string) $value) : null)
                            ->required()
                            ->native(false)
                            ->searchable(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),

                        Forms\Components\TextInput::make('priority')
                            ->label('Prioridad')
                            ->numeric()
                            ->required()
                            ->default(100)
                            ->helperText('Menor número = mayor prioridad.'),

                        Forms\Components\TextInput::make('amount_min')
                            ->label('Monto mínimo')
                            ->prefix('$')
                            ->numeric()
                            ->minValue(0),

                        Forms\Components\TextInput::make('amount_max')
                            ->label('Monto máximo')
                            ->prefix('$')
                            ->numeric()
                            ->minValue(0),
Forms\Components\Select::make('applies_to_user_id')
                            ->label('Aplica a usuario')
                            ->placeholder('Cualquier usuario del grupo')
                            ->helperText('Solo muestra usuarios de la empresa actual o del mismo grupo de empresas.')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => \App\Support\Service\ServiceAccess::approvalWorkflowUserOptions())
                            ->getSearchResultsUsing(fn (string $search): array => \App\Support\Service\ServiceAccess::approvalWorkflowUserOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? \App\Support\Service\ServiceAccess::approvalWorkflowUserLabel((int) $value) : null)
                            ->native(false)
                            ->columnSpan(1),

                        

                        Forms\Components\Select::make('applies_to_role_name')
                            ->label('Aplica a rol')
                            ->options(fn (): array => static::roleOptions())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Cualquier rol'),

                        Forms\Components\Select::make('applies_to_warehouse_id')
                            ->label('Aplica a almacén')
                            ->options(fn (): array => static::warehouseOptions())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Cualquier almacén'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Etapas de aprobación')
                    ->description('Configura todas las etapas necesarias. Ejemplo: Coordinador → Compras → Dirección.')
                    ->schema([
                        Forms\Components\Repeater::make('steps')
                            ->relationship('steps')
                            ->label('')
                            ->addActionLabel('Agregar etapa')
                            ->reorderable(false)
                            ->defaultItems(1)
                            ->schema([
                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Orden')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre de etapa')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ej. Coordinador directo')
                                    ->columnSpan(3),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Activa')
                                    ->default(true)
                                    ->columnSpan(1),

                                Forms\Components\Select::make('approver_type')
                                    ->label('Tipo de aprobador')
                                    ->options(static::approverTypeOptions())
                                    ->required()
                                    ->native(false)
                                    ->reactive()
                                    ->columnSpan(2),

                                Forms\Components\Select::make('approver_user_id')
                                    ->label('Usuario aprobador')
                                    ->options(fn (): array => static::userOptions())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->visible(fn (Get $get): bool => $get('approver_type') === 'specific_user')
                                    ->required(fn (Get $get): bool => $get('approver_type') === 'specific_user')
                                    ->columnSpan(2),

                                Forms\Components\Select::make('approver_role_name')
                                    ->label('Rol aprobador')
                                    ->options(fn (): array => static::roleOptions())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->visible(fn (Get $get): bool => $get('approver_type') === 'role')
                                    ->required(fn (Get $get): bool => $get('approver_type') === 'role')
                                    ->columnSpan(2),

                                Forms\Components\Toggle::make('require_all')
                                    ->label('Requiere todos')
                                    ->helperText('Si el aprobador es rol, requiere aprobación de todos los usuarios del rol.')
                                    ->visible(fn (Get $get): bool => $get('approver_type') === 'role')
                                    ->default(false)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('amount_min')
                                    ->label('Monto mín. etapa')
                                    ->prefix('$')
                                    ->numeric()
                                    ->minValue(0)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('amount_max')
                                    ->label('Monto máx. etapa')
                                    ->prefix('$')
                                    ->numeric()
                                    ->minValue(0)
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Notas de etapa')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(12)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Flujo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Documento')

                    ->formatStateUsing(fn (?string $state): string => \App\Support\Service\ServiceAccess::approvalWorkflowDocumentTypeLabel($state))
                    ->formatStateUsing(fn (?string $state): string => static::documentTypeOptions()[$state] ?? ($state ?: '—'))
                    ->badge(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridad')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_range')
                    ->label('Rango')
                    ->state(fn (ApprovalWorkflow $record): string => static::amountRangeLabel($record)),

                Tables\Columns\TextColumn::make('steps_count')
                    ->label('Etapas')
                    ->counts('steps')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo de documento')
                    ->options(static::documentTypeOptions()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('priority', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovalWorkflows::route('/'),
            'create' => Pages\CreateApprovalWorkflow::route('/create'),
            'edit' => Pages\EditApprovalWorkflow::route('/{record}/edit'),
        ];
    }

public static function canCreate(): bool
    {
        return static::userCanManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanManage();
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCanManage();
    }

public static function documentTypeOptions(): array
    {
        return [
            'purchase_request' => 'Solicitud de compra',
            'purchase_order' => 'Orden de compra',
            'sales_quote' => 'Cotización de venta',
            'sales_order' => 'Orden de venta',
            'sales_margin_approval' => 'Aprobación de margen de venta',
            'employee_incident' => 'Incidencia RRHH',
            'payroll_run' => 'Aprobación de pre-nómina',
            'treasury_cash_transfer_request' => 'Solicitud de efectivo / Retiro PDV',
        ];
    }



    public static function approverTypeOptions(): array
    {
        return [
            'specific_user' => 'Usuario específico',
            'role' => 'Rol específico',
            'requester_manager' => 'Coordinador del solicitante',
            'company_admin' => 'Admin de empresa',
            'group_admin' => 'Admin de grupo',
            'warehouse_responsible' => 'Responsable de almacén',
            'purchase_responsible' => 'Responsable de compras',
            'accounting_responsible' => 'Responsable de contabilidad',
        ];
    }

    protected static function amountRangeLabel(ApprovalWorkflow $record): string
    {
        $min = $record->amount_min !== null ? '$ ' . number_format((float) $record->amount_min, 2) : 'Sin mínimo';
        $max = $record->amount_max !== null ? '$ ' . number_format((float) $record->amount_max, 2) : 'Sin máximo';

        return $min . ' - ' . $max;
    }

    protected static function userOptions(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $query = DB::table('users');

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('users', 'active')) {
            $query->where('active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('users', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query
            ->orderBy(Schema::hasColumn('users', 'name') ? 'name' : 'id')
            ->limit(500)
            ->get()
            ->mapWithKeys(function ($user): array {
                $name = Schema::hasColumn('users', 'name') ? trim((string) ($user->name ?? '')) : '';
                $email = Schema::hasColumn('users', 'email') ? trim((string) ($user->email ?? '')) : '';

                return [
                    $user->id => trim($name . ($email ? ' <' . $email . '>' : '')) ?: 'Usuario #' . $user->id,
                ];
            })
            ->all();
    }

    protected static function roleOptions(): array
    {
        if (! Schema::hasTable('roles')) {
            return [];
        }

        $query = DB::table('roles');

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('roles', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query
            ->orderBy('name')
            ->pluck('name', 'name')
            ->toArray();
    }

    protected static function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $query = DB::table('warehouses');

        if (Schema::hasColumn('warehouses', 'is_active')) {
            $query->where('is_active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('warehouses', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($warehouse): array {
                $code = Schema::hasColumn('warehouses', 'code') ? trim((string) ($warehouse->code ?? '')) : '';
                $name = Schema::hasColumn('warehouses', 'name') ? trim((string) ($warehouse->name ?? '')) : '';

                return [
                    $warehouse->id => trim(($code ? $code . ' - ' : '') . ($name ?: 'Almacén #' . $warehouse->id)),
                ];
            })
            ->all();
    }

    protected static function userCanView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

if (
    method_exists($user, 'hasAnyRole')
    && $user->hasAnyRole([
        'super_admin',
        'Super Admin',
        'Super Administrador',
    ])
) {
    return true;
}

        return method_exists($user, 'can')
            ? $user->can('approvals.view_workflows')
            : false;
    }

    protected static function userCanManage(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
    method_exists($user, 'hasAnyRole')
    && $user->hasAnyRole([
        'super_admin',
        'Super Admin',
        'Super Administrador',
    ])
) {
    return true;
}

        return method_exists($user, 'can')
            ? $user->can('approvals.manage_workflows')
            : false;
    }

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }
}
