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

    protected static ?string $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Formas de pago';

    protected static ?string $modelLabel = 'forma de pago';

    protected static ?string $pluralModelLabel = 'formas de pago';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 10;

    protected static bool $isScopedToTenant = false;

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

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.paymentformresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
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

                    /*
                     * BEXIA_V5525J2_PAYMENT_FORM_SAT_FIELDS
                     */
                    Forms\Components\Select::make('sat_payment_form_code')
                        ->label('Forma SAT CFDI')
                        ->options(static::satPaymentFormOptions())
                        ->searchable()
                        ->helperText('Clave SAT c_FormaPago que se usará en CFDI.')
                        ->columnSpan(1),

                    Forms\Components\Select::make('default_payment_method_code')
                        ->label('Método SAT default')
                        ->options([
                            'PUE' => 'PUE - Pago en una sola exhibición',
                            'PPD' => 'PPD - Pago en parcialidades o diferido',
                        ])
                        ->searchable()
                        ->helperText('Para PDV se forzará PUE al facturar tickets pagados.')
                        ->columnSpan(1),

                    Forms\Components\Select::make('default_payment_term_id')
                        ->label('Condición de pago default')
                        ->options(fn (): array => static::paymentTermOptions())
                        ->searchable()
                        ->preload()
                        ->helperText('Ej. Pago inmediato, Crédito 15 días, Crédito 30 días.')
                        ->columnSpan(1),

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
                Tables\Columns\TextColumn::make('sat_payment_form_code')->label('SAT')->sortable(),
                Tables\Columns\TextColumn::make('default_payment_method_code')->label('Método')->sortable(),
                Tables\Columns\TextColumn::make('defaultPaymentTerm.name')->label('Condición')->placeholder('—'),
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


    public static function satPaymentFormOptions(): array
    {
        return [
            '01' => '01 - Efectivo',
            '02' => '02 - Cheque nominativo',
            '03' => '03 - Transferencia electrónica de fondos',
            '04' => '04 - Tarjeta de crédito',
            '05' => '05 - Monedero electrónico',
            '06' => '06 - Dinero electrónico',
            '08' => '08 - Vales de despensa',
            '12' => '12 - Dación en pago',
            '13' => '13 - Pago por subrogación',
            '14' => '14 - Pago por consignación',
            '15' => '15 - Condonación',
            '17' => '17 - Compensación',
            '23' => '23 - Novación',
            '24' => '24 - Confusión',
            '25' => '25 - Remisión de deuda',
            '26' => '26 - Prescripción o caducidad',
            '27' => '27 - A satisfacción del acreedor',
            '28' => '28 - Tarjeta de débito',
            '29' => '29 - Tarjeta de servicios',
            '30' => '30 - Aplicación de anticipos',
            '31' => '31 - Intermediario pagos',
            '99' => '99 - Por definir',
        ];
    }

    public static function paymentTermOptions(): array
    {
        if (! Schema::hasTable('payment_terms')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('payment_terms')
            ->where('is_active', true);

        if (Schema::hasColumn('payment_terms', 'company_id') && $companyId) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query
            ->orderBy('days')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
