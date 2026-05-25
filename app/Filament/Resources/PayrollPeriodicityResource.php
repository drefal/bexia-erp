<?php

namespace App\Filament\Resources;


use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\Concerns\UsesTenantCompany;
use App\Filament\Resources\PayrollPeriodicityResource\Pages;
use App\Models\PayrollPeriodicity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollPeriodicityResource extends Resource
{
    use UsesTenantCompany;

    protected static ?string $model = PayrollPeriodicity::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Nómina';
    protected static ?string $navigationLabel = 'Periodicidades';
    protected static ?string $modelLabel = 'periodicidad';
    protected static ?string $pluralModelLabel = 'periodicidades';
    protected static ?int $navigationSort = 10;
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Periodicidad')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('sat_code')
                        ->label('Código SAT')
                        ->maxLength(20),

                    Forms\Components\TextInput::make('days')
                        ->label('Días')
                        ->numeric(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sat_code')->label('Código SAT')->searchable(),
                Tables\Columns\TextColumn::make('days')->label('Días')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollPeriodicities::route('/'),
            'create' => Pages\CreatePayrollPeriodicity::route('/create'),
            'edit' => Pages\EditPayrollPeriodicity::route('/{record}/edit'),
        ];
    }

    /*
     * V5.64.1i-start
     * Control de permisos RRHH/Nomina.
     * Nota: superadmin puede operar estos catalogos aunque Spatie no resuelva
     * el company_id en auth()->user()->can() dentro de algunos contextos.
     */
    protected static function bexiaCanCatalogPermission(string $permission): bool
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

        return $user->can($permission);
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.eliminar');
    }
    /*
     * V5.64.1i-end
     */

}
