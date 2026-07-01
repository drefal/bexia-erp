<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingPacConfigurationResource\Pages;
use App\Models\Company;
use App\Support\Billing\SwPacClient;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;

class BillingPacConfigurationResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Configuración Bexia';

    protected static ?string $navigationLabel = 'PAC por empresa';

    protected static ?string $modelLabel = 'PAC por empresa';

    protected static ?string $pluralModelLabel = 'PAC por empresa';

    protected static ?string $slug = 'billing-pac-configuration';

    protected static ?int $navigationSort = 40;

public static function canAccess(): bool
    {
        return false;
    }

public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::isSuperAdminUser();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! static::isSuperAdminUser()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->orderBy('id');
    }

        public static function shouldRegisterNavigation(): bool
        {
            return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
                'resources.billingpacconfigurationresource',
                fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
            );
        }

        protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

        public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    /*
     * BEXIA_BILLING_PAC_CONFIGURATION_RESOURCE_RESPONSIVE_V5_79_68C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Empresa')
                ->extraAttributes(['class' => 'bexia-bpac-section bexia-bpac-section-company'])
                ->columns(12)
                ->schema([
                    Forms\Components\Placeholder::make('company_display')
                        ->extraAttributes(['class' => 'bexia-bpac-field bexia-bpac-placeholder bexia-bpac-placeholder-company'])
                        ->label('Empresa')
                        ->content(fn (?Company $record): string => $record ? ('ID ' . $record->id . ' | ' . ($record->name ?? 'Empresa')) : 'Empresa')
                        ->columnSpan(8),

                    Forms\Components\Placeholder::make('rfc_display')
                        ->extraAttributes(['class' => 'bexia-bpac-field bexia-bpac-placeholder bexia-bpac-placeholder-rfc bexia-bpac-code-field'])
                        ->label('RFC')
                        ->content(fn (?Company $record): string => (string) ($record?->tax_id ?? $record?->rfc ?? $record?->vat ?? 'N/D'))
                        ->columnSpan(4),
                ]),

            Forms\Components\Section::make('PAC MX / SW')
                ->extraAttributes(['class' => 'bexia-bpac-section bexia-bpac-section-pac'])
                ->description('Configuración sensible por empresa. Visible solo para superadmin.')
                ->columns(12)
                ->schema([
                    Forms\Components\Select::make('billing_pac_provider')
                        ->extraAttributes(['class' => 'bexia-bpac-field bexia-bpac-field-provider'])
                        ->label('PAC')
                        ->options([
                            'sw' => 'SW sapien-SmarterWEB',
                        ])
                        ->default('sw')
                        ->required()
                        ->columnSpan(4),

                    Forms\Components\Toggle::make('billing_pac_test_env')
                        ->extraAttributes(['class' => 'bexia-bpac-field bexia-bpac-field-test-env'])
                        ->label('Entorno de prueba')
                        ->helperText('Activo: services.test.sw.com.mx. Inactivo: services.sw.com.mx.')
                        ->default(true)
                        ->columnSpan(3),

                    Forms\Components\TextInput::make('billing_pac_username')
                        ->extraAttributes(['class' => 'bexia-bpac-field bexia-bpac-field-username bexia-bpac-code-field'])
                        ->label('Usuario PAC')
                        ->maxLength(255)
                        ->autocomplete(false)
                        ->columnSpan(5),

                    Forms\Components\TextInput::make('billing_pac_password')
                        ->extraAttributes(['class' => 'bexia-bpac-field bexia-bpac-field-password bexia-bpac-secret-field'])
                        ->label('Contraseña PAC')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->helperText('Se guarda cifrada. Déjala vacía para conservar la contraseña actual.')
                        ->formatStateUsing(fn (): ?string => null)
                        ->dehydrateStateUsing(fn ($state, ?Company $record): ?string => filled($state)
                            ? Crypt::encryptString((string) $state)
                            : ($record?->billing_pac_password))
                        ->columnSpan(6),

                    Forms\Components\TextInput::make('billing_trusted_exporter_number')
                        ->extraAttributes(['class' => 'bexia-bpac-field bexia-bpac-field-trusted-exporter bexia-bpac-code-field'])
                        ->label('Número de exportador confiable')
                        ->maxLength(80)
                        ->columnSpan(6),

                    Forms\Components\Placeholder::make('billing_pac_last_test_status_display')
                        ->extraAttributes(['class' => 'bexia-bpac-field bexia-bpac-placeholder bexia-bpac-placeholder-last-test'])
                        ->label('Última prueba')
                        ->content(fn (?Company $record): string => static::lastTestText($record))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Endpoints SW')
                ->extraAttributes(['class' => 'bexia-bpac-section bexia-bpac-section-endpoints'])
                ->description('Referencia técnica. No editable.')
                ->columns(1)
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('sw_endpoints')
                        ->extraAttributes(['class' => 'bexia-bpac-field bexia-bpac-placeholder bexia-bpac-placeholder-endpoints bexia-bpac-endpoints-field'])
                        ->label('')
                        ->content(fn (?Company $record): string => static::endpointText((bool) ($record?->billing_pac_test_env ?? true))),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->extraHeaderAttributes(['class' => 'bexia-bpac-col-id bexia-bpac-col-code bexia-bpac-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-bpac-col-id bexia-bpac-col-code bexia-bpac-col-compact'])
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-bpac-col-company bexia-bpac-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-bpac-col-company bexia-bpac-col-primary'])
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('billing_pac_provider')
                    ->extraHeaderAttributes(['class' => 'bexia-bpac-col-provider bexia-bpac-col-context bexia-bpac-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-bpac-col-provider bexia-bpac-col-context bexia-bpac-col-badge'])
                    ->label('PAC')
                    ->formatStateUsing(fn ($state): string => $state === 'sw' ? 'SW' : ($state ?: 'Sin configurar'))
                    ->badge(),

                Tables\Columns\TextColumn::make('billing_pac_username')
                    ->extraHeaderAttributes(['class' => 'bexia-bpac-col-username bexia-bpac-col-code bexia-bpac-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-bpac-col-username bexia-bpac-col-code bexia-bpac-col-context'])
                    ->label('Usuario PAC')
                    ->placeholder('Sin usuario'),

                Tables\Columns\IconColumn::make('billing_pac_test_env')
                    ->extraHeaderAttributes(['class' => 'bexia-bpac-col-test-env bexia-bpac-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-bpac-col-test-env bexia-bpac-col-compact'])
                    ->label('Prueba')
                    ->boolean(),

                Tables\Columns\TextColumn::make('billing_pac_last_test_status')
                    ->extraHeaderAttributes(['class' => 'bexia-bpac-col-last-test-status bexia-bpac-col-context bexia-bpac-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-bpac-col-last-test-status bexia-bpac-col-context bexia-bpac-col-badge'])
                    ->label('Última prueba')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ((string) $state) {
                        'success' => 'Correcta',
                        'error' => 'Error',
                        default => 'Sin probar',
                    })
                    ->color(fn ($state): string => match ((string) $state) {
                        'success' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('billing_pac_last_test_at')
                    ->extraHeaderAttributes(['class' => 'bexia-bpac-col-last-test-at bexia-bpac-col-date bexia-bpac-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-bpac-col-last-test-at bexia-bpac-col-date bexia-bpac-col-context'])
                    ->label('Fecha prueba')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('test_sw_connection')
                    ->extraAttributes(['class' => 'bexia-bpac-action bexia-bpac-action-test-connection'])
                    ->label('Probar conexión')
                    ->icon('heroicon-o-bolt')
                    ->color('info')
                    ->visible(fn (): bool => static::isSuperAdminUser())
                    ->action(fn (Company $record): mixed => static::notifySwConnection($record)),

                Tables\Actions\EditAction::make()
                    ->extraAttributes(['class' => 'bexia-bpac-action bexia-bpac-action-edit'])
                    ->label('Editar')
                    ->visible(fn (): bool => static::isSuperAdminUser()),
            ])
            ->paginated(false);
    }

    public static function notifySwConnection(Company $record): void
    {
        if (! static::isSuperAdminUser()) {
            Notification::make()
                ->title('Sin permisos')
                ->body('Solo superadmin puede probar conexión PAC.')
                ->danger()
                ->send();

            return;
        }

        $result = app(SwPacClient::class)->testAuthentication($record->refresh());

        Notification::make()
            ->title($result['success'] ? 'Conexión correcta con SW' : 'Error de conexión con SW')
            ->body($result['message'])
            ->color($result['success'] ? 'success' : 'danger')
            ->send();
    }

    public static function lastTestText(?Company $record): string
    {
        if (! $record || ! $record->billing_pac_last_test_status) {
            return 'Sin pruebas registradas.';
        }

        $status = $record->billing_pac_last_test_status === 'success' ? 'Correcta' : 'Error';
        $date = $record->billing_pac_last_test_at ? (string) $record->billing_pac_last_test_at : 'sin fecha';
        $message = (string) ($record->billing_pac_last_test_message ?? '');

        return "{$status} | {$date} | {$message}";
    }

    public static function endpointText(bool $testEnv): string
    {
        $endpoints = app(SwPacClient::class)->endpoints($testEnv);

        return implode("\n", [
            'Login: ' . $endpoints['login_url'],
            'Timbrado: ' . $endpoints['sign_url'],
            'Cancelación: ' . $endpoints['cancel_url'],
        ]);
    }

    public static function isSuperAdminUser(): bool
    {
        $user = Filament::auth()->user() ?: auth()->user();

        if (! $user) {
            return false;
        }

        foreach (['is_super_admin', 'super_admin', 'isSuperAdmin'] as $property) {
            if (isset($user->{$property}) && (bool) $user->{$property}) {
                return true;
            }
        }

        foreach (['role', 'type'] as $property) {
            $value = strtolower((string) ($user->{$property} ?? ''));

            if (in_array($value, ['superadmin', 'super_admin', 'super admin', 'owner'], true)) {
                return true;
            }
        }

        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole([
                    'super_admin',
                    'superadmin',
                    'Super Admin',
                    'SuperAdmin',
                    'Administrador Global',
                    'admin_global',
                    'owner',
                ])) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Ignorar y probar hasRole.
            }
        }

        if (method_exists($user, 'hasRole')) {
            foreach ([
                'super_admin',
                'superadmin',
                'Super Admin',
                'SuperAdmin',
                'Administrador Global',
                'admin_global',
                'owner',
            ] as $role) {
                try {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // Ignorar.
                }
            }
        }

        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillingPacConfigurations::route('/'),
            'edit' => Pages\EditBillingPacConfiguration::route('/{record}/edit'),
        ];
    }
}
