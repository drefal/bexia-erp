<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollIsrTariffResource\Pages;
use App\Filament\Resources\PayrollIsrTariffResource\RelationManagers\BracketsRelationManager;
use App\Models\PayrollIsrTariff;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PayrollIsrTariffResource extends Resource
{
    protected static ?string $model = PayrollIsrTariff::class;

    protected static ?string $navigationIcon =
        'heroicon-o-calculator';

    protected static ?string $navigationGroup =
        'Nómina';

    protected static ?string $navigationLabel =
        'Configuración fiscal ISR';

    protected static ?string $modelLabel =
        'tarifa ISR';

    protected static ?string $pluralModelLabel =
        'tarifas ISR';

    protected static ?int $navigationSort = 27;

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(
                    'Tarifa ISR'
                )
                    ->description(
                        'Conserva cada ejercicio y vigencia. '
                        . 'No sobrescribas tarifas históricas.'
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
                            ->minValue(2000)
                            ->maxValue(2200)
                            ->required(),

                        Forms\Components\Select::make(
                            'period_type'
                        )
                            ->label('Periodicidad')
                            ->options([
                                'weekly' =>
                                    'Semanal / 7 días',
                                'biweekly' =>
                                    'Quincenal / 15 días',
                                'monthly' =>
                                    'Mensual',
                            ])
                            ->required()
                            ->native(false)
                            ->live(),

                        Forms\Components\TextInput::make(
                            'period_days'
                        )
                            ->label('Días del periodo')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->visible(
                                fn (Forms\Get $get): bool =>
                                    (string) $get(
                                        'period_type'
                                    ) !== 'monthly'
                            ),

                        Forms\Components\DatePicker::make(
                            'valid_from'
                        )
                            ->label('Vigente desde')
                            ->required()
                            ->native(false),

                        Forms\Components\DatePicker::make(
                            'valid_to'
                        )
                            ->label('Vigente hasta')
                            ->native(false)
                            ->afterOrEqual('valid_from'),

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
                            ->columnSpanFull()
                            ->placeholder(
                                'SAT / DOF / Anexo aplicable'
                            ),
                    ]),

                Forms\Components\Section::make(
                    'Conservación histórica'
                )
                    ->schema([
                        Forms\Components\Placeholder::make(
                            'historical_notice'
                        )
                            ->label('')
                            ->content(
                                fn (?PayrollIsrTariff $record): string =>
                                    $record
                                    && $record->isHistoricallyUsed()
                                        ? 'Esta tarifa ya fue utilizada '
                                            . 'por una nómina fiscal y '
                                            . 'queda congelada.'
                                        : 'Para un nuevo ejercicio usa '
                                            . '“Clonar a nuevo ejercicio”.'
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
                        fn (PayrollIsrTariff $record): bool =>
                            $record->isHistoricallyUsed()
                    )
                    ->boolean()
                    ->tooltip(
                        'Ya utilizada en cálculo fiscal'
                    ),

                Tables\Columns\TextColumn::make(
                    'fiscal_year'
                )
                    ->label('Año')
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'period_type'
                )
                    ->label('Periodicidad')
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            match ($state) {
                                'weekly' => 'Semanal',
                                'biweekly' => 'Quincenal',
                                'monthly' => 'Mensual',
                                default => $state ?: '-',
                            }
                    )
                    ->badge(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

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
                    ->date('d/m/Y')
                    ->placeholder('Sin límite'),

                Tables\Columns\TextColumn::make(
                    'brackets_count'
                )
                    ->counts('brackets')
                    ->label('Rangos'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make(
                    'fiscal_year'
                )
                    ->label('Ejercicio')
                    ->options(
                        fn (): array =>
                            PayrollIsrTariff::query()
                                ->select('fiscal_year')
                                ->distinct()
                                ->orderByDesc('fiscal_year')
                                ->pluck(
                                    'fiscal_year',
                                    'fiscal_year'
                                )
                                ->toArray()
                    ),

                Tables\Filters\SelectFilter::make(
                    'period_type'
                )
                    ->label('Periodicidad')
                    ->options([
                        'weekly' => 'Semanal',
                        'biweekly' => 'Quincenal',
                        'monthly' => 'Mensual',
                    ]),

                Tables\Filters\TernaryFilter::make(
                    'is_active'
                )
                    ->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn (PayrollIsrTariff $record): bool =>
                            ! $record->isHistoricallyUsed()
                    ),

                Tables\Actions\Action::make(
                    'cloneFiscalYear'
                )
                    ->label('Clonar a nuevo ejercicio')
                    ->icon('heroicon-o-document-duplicate')
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
                            ->label('Nuevo ejercicio')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(2000)
                            ->maxValue(2200)
                            ->default(
                                fn (PayrollIsrTariff $record): int =>
                                    ((int) $record->fiscal_year) + 1
                            ),

                        Forms\Components\TextInput::make('code')
                            ->label('Nuevo código')
                            ->required()
                            ->default(
                                function (
                                    PayrollIsrTariff $record
                                ): string {
                                    $next =
                                        ((int) $record->fiscal_year)
                                        + 1;

                                    return preg_replace(
                                        '/\b'
                                        . preg_quote(
                                            (string) $record->fiscal_year,
                                            '/'
                                        )
                                        . '\b/',
                                        (string) $next,
                                        (string) $record->code,
                                        1
                                    ) ?: (
                                        $record->code
                                        . '-'
                                        . $next
                                    );
                                }
                            ),

                        Forms\Components\TextInput::make('name')
                            ->label('Nuevo nombre')
                            ->required()
                            ->default(
                                function (
                                    PayrollIsrTariff $record
                                ): string {
                                    $next =
                                        ((int) $record->fiscal_year)
                                        + 1;

                                    return str_replace(
                                        (string) $record->fiscal_year,
                                        (string) $next,
                                        (string) $record->name
                                    );
                                }
                            ),

                        Forms\Components\DatePicker::make(
                            'valid_from'
                        )
                            ->label('Vigente desde')
                            ->native(false)
                            ->required()
                            ->default(
                                fn (
                                    PayrollIsrTariff $record
                                ): string =>
                                    (((int) $record->fiscal_year) + 1)
                                    . '-01-01'
                            ),

                        Forms\Components\DatePicker::make(
                            'valid_to'
                        )
                            ->label('Vigente hasta')
                            ->native(false)
                            ->required()
                            ->default(
                                fn (
                                    PayrollIsrTariff $record
                                ): string =>
                                    (((int) $record->fiscal_year) + 1)
                                    . '-12-31'
                            ),
                    ])
                    ->modalHeading(
                        'Clonar tarifa ISR a nuevo ejercicio'
                    )
                    ->modalDescription(
                        'Se copiarán también todos los rangos. '
                        . 'La nueva tarifa quedará INACTIVA '
                        . 'hasta que confirmes los valores oficiales.'
                    )
                    ->action(
                        function (
                            PayrollIsrTariff $record,
                            array $data
                        ): void {
                            $clone = DB::transaction(
                                function () use (
                                    $record,
                                    $data
                                ): PayrollIsrTariff {
                                    $new =
                                        PayrollIsrTariff::create([
                                            'code' =>
                                                $data['code'],
                                            'name' =>
                                                $data['name'],
                                            'fiscal_year' =>
                                                (int) $data[
                                                    'fiscal_year'
                                                ],
                                            'period_type' =>
                                                $record->period_type,
                                            'period_days' =>
                                                $record->period_days,
                                            'valid_from' =>
                                                $data['valid_from'],
                                            'valid_to' =>
                                                $data['valid_to'],
                                            'source_reference' =>
                                                null,
                                            'is_active' =>
                                                false,
                                        ]);

                                    foreach (
                                        $record->brackets()
                                            ->orderBy(
                                                'sort_order'
                                            )
                                            ->get()
                                        as $bracket
                                    ) {
                                        $new->brackets()->create([
                                            'sort_order' =>
                                                $bracket->sort_order,
                                            'lower_limit' =>
                                                $bracket->lower_limit,
                                            'upper_limit' =>
                                                $bracket->upper_limit,
                                            'fixed_fee' =>
                                                $bracket->fixed_fee,
                                            'marginal_rate' =>
                                                $bracket->marginal_rate,
                                        ]);
                                    }

                                    return $new;
                                }
                            );

                            Notification::make()
                                ->success()
                                ->title(
                                    'Tarifa ISR clonada'
                                )
                                ->body(
                                    'Se creó '
                                    . $clone->code
                                    . ' como INACTIVA. '
                                    . 'Revisa valores y fuente '
                                    . 'oficial antes de activarla.'
                                )
                                ->send();
                        }
                    ),
            ])
            ->defaultSort('valid_from', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            BracketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                Pages\ListPayrollIsrTariffs::route('/'),

            'create' =>
                Pages\CreatePayrollIsrTariff::route('/create'),

            'edit' =>
                Pages\EditPayrollIsrTariff::route(
                    '/{record}/edit'
                ),
        ];
    }
}
