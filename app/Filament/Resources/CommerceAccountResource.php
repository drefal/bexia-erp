<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommerceAccountResource\Pages;
use App\Models\CommerceAccount;
use App\Models\CommerceChannel;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommerceAccountResource extends Resource
{
    protected static ?string $model = CommerceAccount::class;

    // El modelo aún no tiene una relación ownership
    // nativa compatible con el tenant de Filament.
    // Por eso aplicamos un filtro explícito obligatorio.
    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon =
        'heroicon-o-link';

    protected static ?string $navigationGroup =
        'Configuración empresa';

    protected static ?string $navigationLabel =
        'Mis conexiones Commerce';

    protected static ?string $modelLabel =
        'Conexión Commerce';

    protected static ?string $pluralModelLabel =
        'Mis conexiones Commerce';

    protected static ?int $navigationSort = 95;

    public static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Company
            ? (int) $tenant->getKey()
            : null;
    }

    public static function canManageCompany(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        if (! $tenant instanceof Company || ! $user) {
            return false;
        }

        if (
            ! method_exists($user, 'canAccessTenant')
            || ! $user->canAccessTenant($tenant)
        ) {
            return false;
        }

        if (
            method_exists($user, 'isSystemAdmin')
            && $user->isSystemAdmin()
        ) {
            return true;
        }

        $groupId = (int) ($tenant->company_group_id ?? 0);

        return $groupId > 0
            && method_exists($user, 'isGroupAdmin')
            && $user->isGroupAdmin($groupId);
    }

    public static function ownsRecord(Model $record): bool
    {
        $companyId = static::currentCompanyId();

        return $companyId !== null
            && (int) $record->getAttribute('company_id')
                === $companyId;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        // Filament puede evaluar el acceso antes de IdentifyTenant.
        // Solo el Super Administrador autenticado puede superar
        // esa comprobacion inicial sin tenant.
        // Ninguna consulta o escritura se habilita sin empresa.
        if (static::currentCompanyId() === null) {
            $user = auth()->user();

            return $user !== null
                && method_exists($user, 'isSystemAdmin')
                && $user->isSystemAdmin();
        }

        return static::canManageCompany();
    }

    public static function canView(Model $record): bool
    {
        return static::canManageCompany()
            && static::ownsRecord($record);
    }

    public static function canCreate(): bool
    {
        return static::canManageCompany();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageCompany()
            && static::ownsRecord($record)
            && ! (bool) $record->is_active;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $companyId = static::currentCompanyId();

        if (! static::canManageCompany() || ! $companyId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('company_id', $companyId);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(
                'Identificación de la cuenta'
            )
                ->schema([
                    Forms\Components\Select::make(
                        'commerce_channel_id'
                    )
                        ->label('Marketplace')
                        ->options(
                            fn (): array => CommerceChannel::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                        )
                        ->required()
                        ->searchable()
                        ->disabledOn('edit'),

                    Forms\Components\TextInput::make('name')
                        ->label('Nombre de la cuenta')
                        ->required()
                        ->maxLength(150),

                    Forms\Components\TextInput::make(
                        'external_account_id'
                    )
                        ->label('Identificador externo')
                        ->maxLength(150)
                        ->helperText(
                            'Identificador público o de referencia. '
                            . 'No introducir contraseñas ni tokens.'
                        ),

                    Forms\Components\TextInput::make('country')
                        ->label('País')
                        ->default('MX')
                        ->required()
                        ->maxLength(2),

                    Forms\Components\TextInput::make('currency')
                        ->label('Moneda')
                        ->default('MXN')
                        ->required()
                        ->maxLength(3),
                ])
                ->columns(2),

            Forms\Components\Section::make(
                'Estado de la conexión'
            )
                ->schema([
                    Forms\Components\Placeholder::make(
                        'connection_notice'
                    )
                        ->label('Activación')
                        ->content(
                            'Esta etapa registra la cuenta '
                            . 'sin activarla. No captura credenciales '
                            . 'ni realiza sincronizaciones.'
                        ),

                    Forms\Components\Placeholder::make(
                        'company_notice'
                    )
                        ->label('Empresa')
                        ->content(
                            'La cuenta pertenece exclusivamente '
                            . 'a la empresa seleccionada.'
                        ),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('channel.name')
                    ->label('Marketplace'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Cuenta')
                    ->searchable(),

                Tables\Columns\TextColumn::make(
                    'external_account_id'
                )
                    ->label('Referencia externa'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommerceAccounts::route('/'),
            'create' => Pages\CreateCommerceAccount::route(
                '/create'
            ),
            'edit' => Pages\EditCommerceAccount::route(
                '/{record}/edit'
            ),
        ];
    }
}
