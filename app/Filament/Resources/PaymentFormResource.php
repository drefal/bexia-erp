<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentFormResource\Pages;
use App\Models\PaymentForm;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentFormResource extends Resource
{
    protected static ?string $model = PaymentForm::class;

    protected static ?string $navigationGroup = 'Catálogos de facturación';

    protected static ?string $navigationLabel = 'Formas de pago';

    protected static ?string $modelLabel = 'forma de pago';

    protected static ?string $pluralModelLabel = 'formas de pago';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 30;

    protected static bool $isScopedToTenant = false;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        return method_exists($user, 'can') && (
            $user->can('sales.view')
            || $user->can('inventory.view')
            || $user->can('catalogs.view')
        );
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('payment_forms', 'company_id')) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Forma de pago')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->options(fn () => static::companyOptions())
                        ->default(fn () => static::currentCompanyId())
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('code')
                        ->label('Código')
                        ->required()
                        ->maxLength(20),

                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('description')
                        ->label('Descripción')
                        ->maxLength(255)
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Orden')
                        ->numeric()
                        ->default(10),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activo')
                        ->default(true),

                    Forms\Components\Toggle::make('is_cash')
                        ->label('Es efectivo'),

                    Forms\Components\Toggle::make('is_credit')
                        ->label('Es crédito / cuenta por cobrar'),

                    Forms\Components\Toggle::make('requires_reference')
                        ->label('Requiere referencia'),

                    Forms\Components\Toggle::make('requires_bank')
                        ->label('Requiere banco'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Forma de pago')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_cash')->label('Efectivo')->boolean(),
                Tables\Columns\IconColumn::make('is_credit')->label('Crédito')->boolean(),
                Tables\Columns\IconColumn::make('requires_reference')->label('Referencia')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Orden')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentForms::route('/'),
            'create' => Pages\CreatePaymentForm::route('/create'),
            'edit' => Pages\EditPaymentForm::route('/{record}/edit'),
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

    protected static function companyOptions(): array
    {
        if (! Schema::hasTable('companies')) {
            return [];
        }

        return DB::table('companies')->orderBy('name')->pluck('name', 'id')->all();
    }
}
