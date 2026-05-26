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

    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form
            ->schema([
                Forms\Components\Section::make('Datos del concepto')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default($tenantId)
                            ->disabled(filled($tenantId))
                            ->dehydrated(),

                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(80)
                            ->alphaDash()
                            ->uppercase(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(PayrollConcept::typeOptions())
                            ->required()
                            ->default('perception'),

                        Forms\Components\Select::make('category')
                            ->label('Categoría')
                            ->options(PayrollConcept::categoryOptions())
                            ->required()
                            ->default('other'),

                        Forms\Components\Select::make('source')
                            ->label('Origen')
                            ->options(PayrollConcept::sourceOptions())
                            ->required()
                            ->default('system'),

                        Forms\Components\Select::make('unit')
                            ->label('Unidad')
                            ->options(PayrollConcept::unitOptions())
                            ->required()
                            ->default('amount'),

                        Forms\Components\TextInput::make('sat_key')
                            ->label('Clave SAT futura')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->integer()
                            ->default(100),

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
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => PayrollConcept::typeOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Categoría')
                    ->formatStateUsing(fn (?string $state): string => PayrollConcept::categoryOptions()[$state] ?? ($state ?: '-'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->formatStateUsing(fn (?string $state): string => PayrollConcept::sourceOptions()[$state] ?? ($state ?: '-'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unidad')
                    ->formatStateUsing(fn (?string $state): string => PayrollConcept::unitOptions()[$state] ?? ($state ?: '-'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sort_order')
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
