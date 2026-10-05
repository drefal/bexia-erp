<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseCategoryResource\Pages;
use App\Models\ExpenseCategory;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Gastos';

    protected static ?string $navigationLabel = 'Categorías de gasto';

    protected static ?string $modelLabel = 'categoría de gasto';

    protected static ?string $pluralModelLabel = 'categorías de gasto';

    protected static ?int $navigationSort = 20;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanExpensePermission(string $permission): bool
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

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanExpensePermission('treasury.view')
            || static::bexiaCanExpensePermission('treasury.update');
    }

    public static function canViewAny(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanExpensePermission('treasury.update');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanExpensePermission('treasury.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanExpensePermission('treasury.update')
            && ! $record->lines()->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->when(
                $tenantId,
                fn (Builder $query) => $query->where('company_id', $tenantId)
            );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Categoría de gasto')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Código')
                        ->required()
                        ->maxLength(50),

                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(150),

                    Forms\Components\Toggle::make('requires_receipt')
                        ->label('Requiere comprobante')
                        ->default(true),

                    Forms\Components\Toggle::make('allows_without_receipt')
                        ->label('Permitir sin comprobante')
                        ->default(false)
                        ->helperText(
                            'Si se permite, el empleado deberá justificar por qué no tiene comprobante.'
                        ),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
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
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('requires_receipt')
                    ->label('Comprobante')
                    ->boolean(),

                Tables\Columns\IconColumn::make('allows_without_receipt')
                    ->label('Sin comprobante')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseCategories::route('/'),
            'create' => Pages\CreateExpenseCategory::route('/create'),
            'edit' => Pages\EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}
