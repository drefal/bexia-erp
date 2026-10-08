<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseAdvanceSettingResource\Pages;
use App\Models\ExpenseAdvanceSetting;
use App\Support\Security\BexiaTenantPermission;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseAdvanceSettingResource extends Resource
{
    protected static ?string $model =
        ExpenseAdvanceSetting::class;

    protected static ?string $navigationIcon =
        'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup =
        'Gastos';

    protected static ?string $navigationLabel =
        'Configuración de anticipos';

    protected static ?string $modelLabel =
        'configuración de anticipos';

    protected static ?string $pluralModelLabel =
        'configuración de anticipos';

    protected static ?int $navigationSort = 16;

    protected static bool $isScopedToTenant = false;

    protected static ?string
        $tenantOwnershipRelationshipName = null;

    protected static function companyId(): ?int
    {
        return Filament::getTenant()?->getKey()
            ? (int) Filament::getTenant()->getKey()
            : null;
    }

    protected static function isAdmin(): bool
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

        return BexiaTenantPermission::can(
            'expenses.admin'
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isAdmin();
    }

    public static function canViewAny(): bool
    {
        return static::isAdmin();
    }

    public static function canCreate(): bool
    {
        if (! static::isAdmin()) {
            return false;
        }

        $companyId = static::companyId();

        if (! $companyId) {
            return false;
        }

        return ! ExpenseAdvanceSetting::query()
            ->where('company_id', $companyId)
            ->exists();
    }

    public static function canEdit(Model $record): bool
    {
        return static::isAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with('company');

        $companyId = static::companyId();

        if ($companyId) {
            $query->where(
                'company_id',
                $companyId
            );
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(
                'Reglas de anticipos'
            )
                ->description(
                    'Controla qué ocurre cuando un empleado ya tiene anticipos pendientes de comprobar.'
                )
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make(
                        'pending_rule'
                    )
                        ->label(
                            'Anticipos pendientes'
                        )
                        ->options([
                            ExpenseAdvanceSetting::RULE_OFF =>
                                'Desactivado',
                            ExpenseAdvanceSetting::RULE_WARN =>
                                'Advertir y permitir',
                            ExpenseAdvanceSetting::RULE_BLOCK =>
                                'Bloquear nuevo anticipo',
                        ])
                        ->default(
                            ExpenseAdvanceSetting::RULE_WARN
                        )
                        ->required(),

                    Forms\Components\TextInput::make(
                        'due_days'
                    )
                        ->label(
                            'Días para comprobar'
                        )
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(365)
                        ->default(5)
                        ->required()
                        ->helperText(
                            'Se cuentan desde la fecha de solicitud.'
                        ),

                    Forms\Components\Toggle::make(
                        'is_active'
                    )
                        ->label(
                            'Aplicar reglas de anticipos'
                        )
                        ->default(true)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make(
                    'company.name'
                )
                    ->label('Empresa'),

                Tables\Columns\TextColumn::make(
                    'pending_rule'
                )
                    ->label('Regla')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state):
                            string => match ($state) {
                                ExpenseAdvanceSetting::RULE_OFF =>
                                    'Desactivado',
                                ExpenseAdvanceSetting::RULE_WARN =>
                                    'Advertir',
                                ExpenseAdvanceSetting::RULE_BLOCK =>
                                    'Bloquear',
                                default => $state ?: '—',
                            }
                    ),

                Tables\Columns\TextColumn::make(
                    'due_days'
                )
                    ->label(
                        'Días para comprobar'
                    ),

                Tables\Columns\IconColumn::make(
                    'is_active'
                )
                    ->label('Activo')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                Pages\ListExpenseAdvanceSettings::route(
                    '/'
                ),
            'create' =>
                Pages\CreateExpenseAdvanceSetting::route(
                    '/create'
                ),
            'edit' =>
                Pages\EditExpenseAdvanceSetting::route(
                    '/{record}/edit'
                ),
        ];
    }
}
