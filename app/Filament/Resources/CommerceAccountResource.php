<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommerceAccountResource\Pages;
use App\Models\CommerceAccount;
use App\Models\CommerceAuthField;
use App\Models\CommerceChannel;
use App\Models\CommerceConnectorVersion;
use App\Services\Commerce\AccountCredentialService;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
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

                Tables\Actions\Action::make('configureCredential')
                    ->label('Configurar credencial')
                    ->icon('heroicon-o-key')
                    ->visible(
                        fn (CommerceAccount $record): bool =>
                            static::canEdit($record)
                    )
                    ->modalHeading('Configurar credencial')
                    ->modalDescription(
                        'La clave se almacenará cifrada. '
                        . 'No se mostrará nuevamente. '
                        . 'Guardar no activa la conexión.'
                    )
                    ->modalSubmitActionLabel('Guardar credencial')
                    ->form(
                        fn (CommerceAccount $record): array => [
                            Forms\Components\Select::make(
                                'connector_version_id'
                            )
                                ->label('Versión del conector')
                                ->options(
                                    fn (): array =>
                                        CommerceConnectorVersion::query()
                                            ->where(
                                                'commerce_channel_id',
                                                $record->commerce_channel_id
                                            )
                                            ->where('status', 'ready')
                                            ->where('is_enabled', false)
                                            ->orderBy('version')
                                            ->pluck('version', 'id')
                                            ->all()
                                )
                                ->required()
                                ->live(),

                            Forms\Components\Select::make(
                                'field_key'
                            )
                                ->label('Campo de autenticación')
                                ->options(
                                    fn (Get $get): array =>
                                        CommerceAuthField::query()
                                            ->where(
                                                'commerce_connector_version_id',
                                                (int) $get(
                                                    'connector_version_id'
                                                )
                                            )
                                            ->where(
                                                'credential_owner',
                                                'account'
                                            )
                                            ->where('is_secret', true)
                                            ->where('is_active', true)
                                            ->orderBy('sort_order')
                                            ->pluck('label', 'field_key')
                                            ->all()
                                )
                                ->required(),

                            Forms\Components\TextInput::make(
                                'secret'
                            )
                                ->label('Nueva credencial')
                                ->password()
                                ->required()
                                ->maxLength(4096)
                                ->autocomplete('new-password')
                                ->helperText(
                                    'Introduce una nueva clave. '
                                    . 'Por seguridad, el valor existente '
                                    . 'nunca se recupera en este formulario.'
                                ),
                        ]
                    )
                    ->action(
                        function (
                            CommerceAccount $record,
                            array $data
                        ): void {
                            if (! static::canEdit($record)) {
                                abort(403);
                            }

                            app(AccountCredentialService::class)
                                ->replace(
                                    (int) $record->getKey(),
                                    (int) (
                                        $data['connector_version_id'] ?? 0
                                    ),
                                    (string) (
                                        $data['field_key'] ?? ''
                                    ),
                                    (string) (
                                        $data['secret'] ?? ''
                                    )
                                );

                            Notification::make()
                                ->title('Credencial guardada')
                                ->body(
                                    'Se almacenó cifrada. '
                                    . 'La conexión continúa desactivada.'
                                )
                                ->success()
                                ->send();
                        }
                    ),
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
