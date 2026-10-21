<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesTenantCompany;
use App\Filament\Resources\PayrollGroupResource\Pages;
use App\Models\PayrollGroup;
use App\Models\PayrollPeriodicity;
use App\Support\PayrollGroupAuthorization as GroupAuth;
use Illuminate\Database\Eloquent\Model;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayrollGroupResource extends Resource
{
    use UsesTenantCompany;

    protected static ?string $model = PayrollGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Nómina';
    protected static ?string $navigationLabel = 'Grupos de nómina';

    protected static ?string $modelLabel = 'grupo de nómina';
    protected static ?string $pluralModelLabel = 'grupos de nómina';

    protected static ?int $navigationSort = 18;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return GroupAuth::currentUserCan(GroupAuth::VIEW);
    }

    public static function canView(Model $record): bool
    {
        return static::recordBelongsToCurrentCompany($record)
            && GroupAuth::currentUserCan(GroupAuth::VIEW);
    }

    public static function canCreate(): bool
    {
        return GroupAuth::currentUserCan(GroupAuth::CREATE);
    }

    public static function canEdit(Model $record): bool
    {
        return static::recordBelongsToCurrentCompany($record)
            && GroupAuth::currentUserCan(GroupAuth::EDIT);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    private static function recordBelongsToCurrentCompany(
        Model $record
    ): bool {
        $companyId = GroupAuth::currentCompanyId();

        return $companyId !== null
            && (int) $record->getAttribute('company_id') === $companyId;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Grupo de nómina')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(30),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(120),

                        Forms\Components\Select::make('payroll_periodicity_id')
                            ->label('Periodicidad')
                            ->options(fn (): array => static::periodicityOptions())
                            ->searchable()
                            ->preload()
                            ->helperText('Periodicidad habitual de este grupo de nómina.'),

                        Forms\Components\Select::make('calculation_mode')
                            ->label('Modo de nómina')
                            ->options(
                                PayrollGroup::calculationModeOptions()
                            )
                            ->default('operational')
                            ->required()
                            ->native(false)
                            ->helperText(
                                'Nómina simple: calcula sueldo, asistencia, incidencias, percepciones y deducciones sin ISR ni CFDI. '
                                . 'Nómina fiscal sin timbrado: agrega ISR y subsidio, pero no genera CFDI. '
                                . 'Nómina fiscal con CFDI: agrega cálculo fiscal y habilita preparación y timbrado CFDI.'
                            ),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payrollPeriodicity.name')
                    ->label('Periodicidad'),

                Tables\Columns\TextColumn::make('calculation_mode')
                    ->label('Modo')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            PayrollGroup::calculationModeOptions()[
                                $state ?: 'operational'
                            ]
                            ?? ($state ?: 'Nómina simple')
                    ),

                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Empleados')
                    ->counts('employees'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    protected static function periodicityOptions(): array
    {
        $companyId = Filament::getTenant()?->getKey();

        return PayrollPeriodicity::query()
            ->when(
                $companyId,
                fn (Builder $query): Builder =>
                    $query->where('company_id', $companyId)
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollGroups::route('/'),
            'create' => Pages\CreatePayrollGroup::route('/create'),
            'edit' => Pages\EditPayrollGroup::route('/{record}/edit'),
        ];
    }
}
