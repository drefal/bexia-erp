<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollConceptResource\Pages;
use App\Models\PayrollConcept;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PayrollConceptResource extends Resource
{
    protected static ?string $model = PayrollConcept::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Nómina';

    protected static ?string $navigationLabel = 'Conceptos de nómina';

    protected static ?string $modelLabel = 'concepto de nómina';

    protected static ?string $pluralModelLabel = 'conceptos de nómina';

    protected static ?int $navigationSort = 25;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanConceptPermission(string $permission): bool
    {
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

        return $user->can($permission) || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanConceptPermission('nomina.conceptos.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanConceptPermission('nomina.conceptos.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanConceptPermission('nomina.conceptos.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanConceptPermission('nomina.conceptos.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanConceptPermission('nomina.conceptos.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanConceptPermission('nomina.conceptos.eliminar');
    }

    /*
     * BEXIA_PAYROLL_CONCEPT_RESOURCE_RESPONSIVE_V5_79_63C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form
            ->schema([
                Forms\Components\Section::make('Datos del concepto')
                    ->extraAttributes(['class' => 'bexia-payroll-concept-section bexia-payroll-concept-section-main'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-company bexia-payroll-concept-select'])
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default($tenantId)
                            ->disabled(filled($tenantId))
                            ->dehydrated(),

                        Forms\Components\TextInput::make('code')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-code bexia-payroll-concept-code-field'])
                            ->label('Código')
                            ->required()
                            ->maxLength(80)
                            ->alphaDash()
                            ->uppercase(),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-name'])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-type bexia-payroll-concept-select'])
                            ->label('Tipo')
                            ->options(PayrollConcept::typeOptions())
                            ->required()
                            ->default('perception')
                            ->live(),

                        Forms\Components\Select::make('category')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-category bexia-payroll-concept-select'])
                            ->label('Categoría')
                            ->options(PayrollConcept::categoryOptions())
                            ->required()
                            ->default('other'),

                        Forms\Components\Select::make('source')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-source bexia-payroll-concept-select'])
                            ->label('Origen')
                            ->options(PayrollConcept::sourceOptions())
                            ->required()
                            ->default('system'),

                        Forms\Components\Select::make('unit')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-unit bexia-payroll-concept-select'])
                            ->label('Unidad')
                            ->options(PayrollConcept::unitOptions())
                            ->required()
                            ->default('amount'),

                        Forms\Components\Select::make('sat_key')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-sat-key bexia-payroll-concept-select'])
                            ->label('Clave SAT nómina')
                            ->options(fn (Forms\Get $get): array => self::satPayrollConceptOptions((string) $get('type')))
                            ->searchable()
                            ->preload()
                            ->helperText('Clave SAT del complemento de nómina según el tipo del concepto.'),

                        Forms\Components\Toggle::make('is_taxable')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-is-taxable bexia-payroll-concept-toggle'])
                            ->label('Gravado para CFDI')
                            ->helperText('Actívalo cuando el concepto se considere gravado. Si es exento o informativo, déjalo apagado.'),

                        Forms\Components\TextInput::make('taxable_amount_default')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-taxable-amount bexia-payroll-concept-money-field'])
                            ->label('Importe gravado default')
                            ->numeric()
                            ->prefix('$'),

                        Forms\Components\TextInput::make('exempt_amount_default')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-exempt-amount bexia-payroll-concept-money-field'])
                            ->label('Importe exento default')
                            ->numeric()
                            ->prefix('$'),

                        Forms\Components\TextInput::make('sort_order')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-sort-order bexia-payroll-concept-number-field'])
                            ->label('Orden')
                            ->numeric()
                            ->integer()
                            ->default(100),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-active bexia-payroll-concept-toggle'])
                            ->label('Activo')
                            ->default(true),

                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-payroll-concept-field bexia-payroll-concept-field-notes'])
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function satPayrollConceptOptions(?string $type = null): array
    {
        $perceptions = [
            '001' => '001 - Sueldos, Salarios Rayas y Jornales',
            '002' => '002 - Gratificación Anual (Aguinaldo)',
            '003' => '003 - Participación de los Trabajadores en las Utilidades PTU',
            '004' => '004 - Reembolso de Gastos Médicos Dentales y Hospitalarios',
            '005' => '005 - Fondo de Ahorro',
            '006' => '006 - Caja de ahorro',
            '009' => '009 - Contribuciones a cargo del trabajador pagadas por el patrón',
            '010' => '010 - Premios por puntualidad',
            '019' => '019 - Horas extra',
            '020' => '020 - Prima dominical',
            '021' => '021 - Prima vacacional',
            '022' => '022 - Prima por antigüedad',
            '028' => '028 - Comisiones',
            '029' => '029 - Vales de despensa',
            '038' => '038 - Otros ingresos por salarios',
            '046' => '046 - Ingresos asimilados a salarios',
            '047' => '047 - Alimentación',
            '048' => '048 - Habitación',
            '049' => '049 - Premios por asistencia',
            '050' => '050 - Viáticos',
            '051' => '051 - Pagos distintos',
        ];

        $deductions = [
            '001' => '001 - Seguridad social',
            '002' => '002 - ISR',
            '003' => '003 - Aportaciones a retiro, cesantía o vejez',
            '004' => '004 - Otros',
            '005' => '005 - Aportaciones a Fondo de vivienda',
            '006' => '006 - Descuento por incapacidad',
            '007' => '007 - Pensión alimenticia',
            '009' => '009 - Préstamos provenientes del Fondo Nacional de la Vivienda',
            '010' => '010 - Pago por crédito de vivienda',
            '011' => '011 - Pago de abonos INFONACOT',
            '012' => '012 - Anticipo de salarios',
            '013' => '013 - Pagos hechos con exceso al trabajador',
            '014' => '014 - Errores',
            '015' => '015 - Pérdidas',
            '016' => '016 - Averías',
            '017' => '017 - Adquisición de artículos producidos por la empresa',
            '018' => '018 - Cuotas sindicales',
            '019' => '019 - Ausencia',
            '020' => '020 - Cuotas obrero patronales',
            '021' => '021 - Anticipo de viáticos',
            '101' => '101 - ISR retenido de ejercicio anterior',
        ];

        return $type === 'deduction' ? $deductions : $perceptions;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-active bexia-payroll-concept-col-icon'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-active bexia-payroll-concept-col-icon'])
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-company bexia-payroll-concept-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-company bexia-payroll-concept-col-context'])
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-code bexia-payroll-concept-col-primary bexia-payroll-concept-col-code-text'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-code bexia-payroll-concept-col-primary bexia-payroll-concept-col-code-text'])
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-name bexia-payroll-concept-col-primary-text'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-name bexia-payroll-concept-col-primary-text'])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-type bexia-payroll-concept-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-type bexia-payroll-concept-col-badge'])
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => PayrollConcept::typeOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-category bexia-payroll-concept-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-category bexia-payroll-concept-col-badge'])
                    ->label('Categoría')
                    ->formatStateUsing(fn (?string $state): string => PayrollConcept::categoryOptions()[$state] ?? ($state ?: '-'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-source bexia-payroll-concept-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-source bexia-payroll-concept-col-context'])
                    ->label('Origen')
                    ->formatStateUsing(fn (?string $state): string => PayrollConcept::sourceOptions()[$state] ?? ($state ?: '-'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-unit bexia-payroll-concept-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-unit bexia-payroll-concept-col-context'])
                    ->label('Unidad')
                    ->formatStateUsing(fn (?string $state): string => PayrollConcept::unitOptions()[$state] ?? ($state ?: '-'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sat_key')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-sat-key bexia-payroll-concept-col-code-text'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-sat-key bexia-payroll-concept-col-code-text'])
                    ->label('Clave SAT')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_taxable')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-taxable bexia-payroll-concept-col-icon'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-taxable bexia-payroll-concept-col-icon'])
                    ->label('Gravado')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-concept-col-sort-order bexia-payroll-concept-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-concept-col-sort-order bexia-payroll-concept-col-number'])
                    ->label('Orden')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(PayrollConcept::typeOptions()),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoría')
                    ->options(PayrollConcept::categoryOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->defaultSort('sort_order');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId) {
            $query->where('company_id', $tenantId);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollConcepts::route('/'),
            'create' => Pages\CreatePayrollConcept::route('/create'),
            'edit' => Pages\EditPayrollConcept::route('/{record}/edit'),
        ];
    }
}
