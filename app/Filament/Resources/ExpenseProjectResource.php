<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseProjectResource\Pages;
use App\Models\ExpenseProject;
use App\Support\Security\BexiaTenantPermission;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseProjectResource extends Resource
{
    protected static ?string $model = ExpenseProject::class;

    protected static ?string $navigationIcon =
        'heroicon-o-folder';

    protected static ?string $navigationGroup =
        'Gastos';

    protected static ?string $navigationLabel =
        'Proyectos';

    protected static ?string $modelLabel =
        'Proyecto';

    protected static ?string $pluralModelLabel =
        'Proyectos';

    protected static ?int $navigationSort =
        25;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function currentCompanyId(): int
    {
        return (int) (
            Filament::getTenant()?->getKey()
            ?? 0
        );
    }

    protected static function canManage(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        return BexiaTenantPermission::can(
            'expenses.admin'
        ) || BexiaTenantPermission::can(
            'expenses.categories.manage'
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canManage();
    }

    public static function canViewAny(): bool
    {
        return static::canManage();
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit($record): bool
    {
        return static::canManage()
            && (int) $record->company_id === static::currentCompanyId();
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(
                'company_id',
                static::currentCompanyId()
            );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(
                    'Proyecto'
                )
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(50)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule) =>
                                    $rule->where(
                                        'company_id',
                                        static::currentCompanyId()
                                    )
                            ),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(4)
                            ->columnSpanFull(),

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
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(60)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseProjects::route('/'),
            'create' => Pages\CreateExpenseProject::route('/create'),
            'edit' => Pages\EditExpenseProject::route('/{record}/edit'),
        ];
    }
}
