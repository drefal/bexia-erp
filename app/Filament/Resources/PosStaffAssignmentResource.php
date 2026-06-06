<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PosStaffAssignmentResource\Pages;
use App\Models\PosStaffAssignment;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosStaffAssignmentResource extends Resource
{
    protected static ?string $model = PosStaffAssignment::class;

    protected static ?string $navigationGroup = 'Punto de Venta';

    protected static ?string $navigationLabel = 'Personal de cajas';

    protected static ?string $modelLabel = 'personal de caja';

    protected static ?string $pluralModelLabel = 'personal de cajas';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 40;

    protected static bool $isScopedToTenant = false;

public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('pos_point_employee', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.posstaffassignmentresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Asignación')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->options(fn () => static::optionsFromTable('companies', ['name']))
                        ->default(fn () => static::currentCompanyId())
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('pos_point_id')
                        ->label('Caja / Punto de venta')
                        ->options(fn () => static::optionsFromTable('pos_points', ['name', 'code']))
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('employee_id')
                        ->label('Empleado')
                        ->options(fn () => static::employeeOptions())
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('role')
                        ->label('Rol en caja')
                        ->options([
                            'seller' => 'Vendedor: genera ticket sin cobrar',
                            'cashier' => 'Cajero: cobra tickets',
                            'mixed' => 'Mixto: vende y cobra',
                        ])
                        ->default('cashier')
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activo')
                        ->default(true),
                ]),

            Forms\Components\Section::make('Permisos')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('can_create_ticket')
                        ->label('Puede generar ticket')
                        ->default(true),

                    Forms\Components\Toggle::make('can_charge')
                        ->label('Puede cobrar')
                        ->default(true),

                    Forms\Components\Toggle::make('can_discount')
                        ->label('Puede aplicar descuentos')
                        ->default(true),

                    Forms\Components\Toggle::make('can_cancel')
                        ->label('Puede cancelar')
                        ->default(true),

                    Forms\Components\Toggle::make('can_open_cash_drawer')
                        ->label('Puede abrir cajón')
                        ->default(false),

                    Forms\Components\TextInput::make('max_discount_percent')
                        ->label('Descuento máximo %')
                        ->numeric()
                        ->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('pos_point_id')
                    ->label('Caja / PDV')
                    ->state(fn ($record) => static::labelFromTable('pos_points', $record->pos_point_id, ['name', 'code']))
                    ->searchable(),

                Tables\Columns\TextColumn::make('employee_id')
                    ->label('Empleado')
                    ->state(fn ($record) => static::labelFromTable('employees', $record->employee_id, ['name', 'employee_number']))
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((string) $state) {
                        'seller' => 'Vendedor',
                        'cashier' => 'Cajero',
                        'mixed' => 'Mixto',
                        default => $state ?: '—',
                    })
                    ->color(fn ($state) => match ((string) $state) {
                        'seller' => 'warning',
                        'cashier' => 'success',
                        'mixed' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('can_create_ticket')->label('Ticket')->boolean(),
                Tables\Columns\IconColumn::make('can_charge')->label('Cobro')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosStaffAssignments::route('/'),
            'create' => Pages\CreatePosStaffAssignment::route('/create'),
            'edit' => Pages\EditPosStaffAssignment::route('/{record}/edit'),
        ];
    }

    protected static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                return (int) $tenant->getKey();
            }

            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
        } catch (\Throwable $e) {
        }

        $tenant = request()->route('tenant');

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        return auth()->user()?->company_id ? (int) auth()->user()->company_id : null;
    }

    protected static function optionsFromTable(string $table, array $labelColumns): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table);
        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn($table, 'active')) {
            $query->where('active', true);
        }

        if (Schema::hasColumn($table, 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->orderBy('id')->limit(500)->get()->mapWithKeys(function ($row) use ($labelColumns) {
            $parts = [];

            foreach ($labelColumns as $column) {
                if (isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                    $parts[] = trim((string) $row->{$column});
                }
            }

            return [$row->id => $parts ? implode(' - ', $parts) : ('#' . $row->id)];
        })->all();
    }

    protected static function employeeOptions(): array
    {
        if (! Schema::hasTable('employees')) {
            return [];
        }

        $query = DB::table('employees');

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('employees', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('employees', 'active')) {
            $query->where('active', true);
        }

        if (Schema::hasColumn('employees', 'pos_active')) {
            $query->where('pos_active', true);
        }

        if (Schema::hasColumn('employees', 'is_pos_cashier') && Schema::hasColumn('employees', 'is_pos_seller')) {
            $query->where(function ($q) {
                $q->where('is_pos_cashier', true)
                    ->orWhere('is_pos_seller', true);
            });
        }

        return $query->orderBy('name')->limit(500)->get()->mapWithKeys(function ($row) {
            $number = ! empty($row->employee_number) ? (' - ' . $row->employee_number) : '';

            $roles = [];

            if (! empty($row->is_pos_cashier)) {
                $roles[] = 'Cajero';
            }

            if (! empty($row->is_pos_seller)) {
                $roles[] = 'Vendedor';
            }

            $roleText = $roles ? (' · ' . implode(' / ', $roles)) : '';

            return [$row->id => $row->name . $number . $roleText];
        })->all();
    }

    protected static function labelFromTable(string $table, mixed $id, array $labelColumns): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '—';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '—';
        }

        $parts = [];

        foreach ($labelColumns as $column) {
            if (isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                $parts[] = trim((string) $row->{$column});
            }
        }

        return $parts ? implode(' - ', $parts) : ('#' . $id);
    }
}
