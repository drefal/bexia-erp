<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollEmploymentSubsidyRuleResource\Pages;
use App\Models\PayrollEmploymentSubsidyRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PayrollEmploymentSubsidyRuleResource extends Resource
{
    protected static ?string $model =
        PayrollEmploymentSubsidyRule::class;

    protected static ?string $navigationIcon =
        'heroicon-o-banknotes';

    protected static ?string $navigationGroup =
        'Nómina';

    protected static ?string $navigationLabel =
        'Subsidio al empleo';

    protected static ?string $modelLabel =
        'regla de subsidio al empleo';

    protected static ?string $pluralModelLabel =
        'reglas de subsidio al empleo';

    protected static ?int $navigationSort = 28;

    protected static bool $isScopedToTenant = false;

    protected static function canFiscalCatalog(
        string $permission
    ): bool {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        if (($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        return $user->can($permission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canFiscalCatalog(
            'nomina.catalogos.ver'
        );
    }

    public static function canViewAny(): bool
    {
        return static::canFiscalCatalog(
            'nomina.catalogos.ver'
        );
    }

    public static function canView(Model $record): bool
    {
        return static::canFiscalCatalog(
            'nomina.catalogos.ver'
        );
    }

    public static function canCreate(): bool
    {
        return static::canFiscalCatalog(
            'nomina.catalogos.crear'
        );
    }

    public static function canEdit(Model $record): bool
    {
        if (
            method_exists($record, 'isHistoricallyUsed')
            && $record->isHistoricallyUsed()
        ) {
            return false;
        }

        return static::canFiscalCatalog(
            'nomina.catalogos.editar'
        );
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function cleanDecimal(
        mixed $state,
        int $decimals = 2
    ): string {
        if ($state === null || $state === '') {
            return '';
        }

        return number_format(
            (float) $state,
            $decimals,
            '.',
            ''
        );
    }

    protected static function cleanFactor(
        mixed $state
    ): string {
        if ($state === null || $state === '') {
            return '';
        }

        $value = number_format(
            (float) $state,
            6,
            '.',
            ''
        );

        return rtrim(
            rtrim($value, '0'),
            '.'
        );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(
                    'Regla de subsidio al empleo'
                )
                    ->description(
                        'Conserva cada cambio legal como una '
                        . 'vigencia independiente.'
                    )
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(80)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make(
                            'fiscal_year'
                        )
                            ->label('Ejercicio fiscal')
                            ->numeric()
                            ->integer()
                            ->required(),

                        Forms\Components\DatePicker::make(
                            'valid_from'
                        )
                            ->label('Vigente desde')
                            ->native(false)
                            ->required(),

                        Forms\Components\DatePicker::make(
                            'valid_to'
                        )
                            ->label('Vigente hasta')
                            ->native(false)
                            ->afterOrEqual('valid_from'),

                        Forms\Components\TextInput::make(
                            'monthly_income_limit'
                        )
                            ->label(
                                'Límite ingreso mensual'
                            )
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->prefix('$')
                            ->afterStateHydrated(
                                fn (
                                    Forms\Components\TextInput $component,
                                    $state
                                ) =>
                                    $component->state(
                                        static::cleanDecimal(
                                            $state
                                        )
                                    )
                            ),

                        Forms\Components\TextInput::make(
                            'uma_monthly'
                        )
                            ->label('UMA mensual')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->prefix('$')
                            ->afterStateHydrated(
                                fn (
                                    Forms\Components\TextInput $component,
                                    $state
                                ) =>
                                    $component->state(
                                        static::cleanDecimal(
                                            $state
                                        )
                                    )
                            ),

                        Forms\Components\TextInput::make(
                            'subsidy_percentage'
                        )
                            ->label(
                                'Porcentaje de subsidio'
                            )
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->suffix('%')
                            ->afterStateHydrated(
                                fn (
                                    Forms\Components\TextInput $component,
                                    $state
                                ) =>
                                    $component->state(
                                        static::cleanDecimal(
                                            $state
                                        )
                                    )
                            ),

                        Forms\Components\TextInput::make(
                            'month_days_factor'
                        )
                            ->label('Factor días mes')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->afterStateHydrated(
                                fn (
                                    Forms\Components\TextInput $component,
                                    $state
                                ) =>
                                    $component->state(
                                        static::cleanFactor(
                                            $state
                                        )
                                    )
                            ),

                        Forms\Components\Toggle::make(
                            'is_active'
                        )
                            ->label('Activa')
                            ->default(true),

                        Forms\Components\TextInput::make(
                            'source_reference'
                        )
                            ->label('Fuente oficial')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make(
                            'calculated_monthly_maximum'
                        )
                            ->label(
                                'Máximo mensual estimado'
                            )
                            ->content(
                                function (
                                    Forms\Get $get
                                ): string {
                                    $uma = (float) (
                                        $get(
                                            'uma_monthly'
                                        ) ?: 0
                                    );

                                    $pct = (float) (
                                        $get(
                                            'subsidy_percentage'
                                        ) ?: 0
                                    );

                                    return '$'
                                        . number_format(
                                            $uma
                                            * ($pct / 100),
                                            2
                                        );
                                }
                            ),

                        Forms\Components\Placeholder::make(
                            'historical_status'
                        )
                            ->label(
                                'Estado histórico'
                            )
                            ->content(
                                fn (
                                    ?PayrollEmploymentSubsidyRule $record
                                ): string =>
                                    $record
                                    && $record->isHistoricallyUsed()
                                        ? 'Utilizada en nómina fiscal. '
                                            . 'Configuración congelada.'
                                        : 'Editable mientras no haya '
                                            . 'sido utilizada fiscalmente.'
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make(
                    'is_active'
                )
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\IconColumn::make(
                    'historically_used'
                )
                    ->label('Histórica')
                    ->getStateUsing(
                        fn (
                            PayrollEmploymentSubsidyRule $record
                        ): bool =>
                            $record->isHistoricallyUsed()
                    )
                    ->boolean(),

                Tables\Columns\TextColumn::make(
                    'fiscal_year'
                )
                    ->label('Año'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                Tables\Columns\TextColumn::make(
                    'valid_from'
                )
                    ->label('Desde')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make(
                    'valid_to'
                )
                    ->label('Hasta')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make(
                    'monthly_income_limit'
                )
                    ->label('Límite mensual')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make(
                    'uma_monthly'
                )
                    ->label('UMA mensual')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make(
                    'subsidy_percentage'
                )
                    ->label('% subsidio')
                    ->formatStateUsing(
                        fn ($state): string =>
                            number_format(
                                (float) $state,
                                2
                            )
                            . '%'
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn (
                            PayrollEmploymentSubsidyRule $record
                        ): bool =>
                            ! $record->isHistoricallyUsed()
                    ),

                Tables\Actions\Action::make(
                    'cloneRule'
                )
                    ->label('Clonar regla')
                    ->icon(
                        'heroicon-o-document-duplicate'
                    )
                    ->visible(
                        fn (): bool =>
                            static::canFiscalCatalog(
                                'nomina.catalogos.crear'
                            )
                    )
                    ->form([
                        Forms\Components\TextInput::make(
                            'fiscal_year'
                        )
                            ->label('Ejercicio')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->default(
                                fn (
                                    PayrollEmploymentSubsidyRule $record
                                ): int =>
                                    ((int) $record->fiscal_year) + 1
                            ),

                        Forms\Components\TextInput::make('code')
                            ->label('Nuevo código')
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nuevo nombre')
                            ->required(),

                        Forms\Components\DatePicker::make(
                            'valid_from'
                        )
                            ->label('Vigente desde')
                            ->native(false)
                            ->required(),

                        Forms\Components\DatePicker::make(
                            'valid_to'
                        )
                            ->label('Vigente hasta')
                            ->native(false),
                    ])
                    ->modalHeading(
                        'Clonar regla de subsidio'
                    )
                    ->modalDescription(
                        'La copia quedará INACTIVA. '
                        . 'Actualiza UMA, porcentaje, límite '
                        . 'y fuente oficial antes de activarla.'
                    )
                    ->action(
                        function (
                            PayrollEmploymentSubsidyRule $record,
                            array $data
                        ): void {
                            $clone =
                                PayrollEmploymentSubsidyRule
                                    ::create([
                                        'code' =>
                                            $data['code'],
                                        'name' =>
                                            $data['name'],
                                        'fiscal_year' =>
                                            (int) $data[
                                                'fiscal_year'
                                            ],
                                        'valid_from' =>
                                            $data[
                                                'valid_from'
                                            ],
                                        'valid_to' =>
                                            $data[
                                                'valid_to'
                                            ] ?? null,
                                        'monthly_income_limit' =>
                                            $record
                                                ->monthly_income_limit,
                                        'uma_monthly' =>
                                            $record
                                                ->uma_monthly,
                                        'subsidy_percentage' =>
                                            $record
                                                ->subsidy_percentage,
                                        'month_days_factor' =>
                                            $record
                                                ->month_days_factor,
                                        'source_reference' =>
                                            null,
                                        'is_active' =>
                                            false,
                                    ]);

                            Notification::make()
                                ->success()
                                ->title(
                                    'Regla clonada'
                                )
                                ->body(
                                    $clone->code
                                    . ' quedó INACTIVA.'
                                )
                                ->send();
                        }
                    ),
            ])
            ->defaultSort('valid_from', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                Pages\ListPayrollEmploymentSubsidyRules
                    ::route('/'),

            'create' =>
                Pages\CreatePayrollEmploymentSubsidyRule
                    ::route('/create'),

            'edit' =>
                Pages\EditPayrollEmploymentSubsidyRule
                    ::route('/{record}/edit'),
        ];
    }
}
