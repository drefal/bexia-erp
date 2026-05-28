<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Configuración Bexia';

    protected static ?int $navigationSort = 30;

public static function canCreate(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isSystemAdmin() || $user->isGroupAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('company.update');
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isSystemAdmin() || $user->isGroupAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isSystemAdmin() || $user->isGroupAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return 'Empresas';
    }

    public static function getModelLabel(): string
    {
        return 'Empresa';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Empresas';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = Company::query();
        $user = auth()->user();
        $tenantId = Filament::getTenant()?->getKey();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSystemAdmin()) {
            return $query;
        }

        $directCompanyIds = $user->companies()->pluck('companies.id')->all();

        if ($user->isGroupAdmin()) {
            $groupIds = $user->manageableCompanyGroupIds();

            return $query->where(function (Builder $q) use ($groupIds, $directCompanyIds, $tenantId) {
                $hasAny = false;

                if (! empty($groupIds)) {
                    $q->whereIn('company_group_id', $groupIds);
                    $hasAny = true;
                }

                if (! empty($directCompanyIds)) {
                    if ($hasAny) {
                        $q->orWhereIn('id', $directCompanyIds);
                    } else {
                        $q->whereIn('id', $directCompanyIds);
                        $hasAny = true;
                    }
                }

                if ($tenantId) {
                    if ($hasAny) {
                        $q->orWhere('id', $tenantId);
                    } else {
                        $q->where('id', $tenantId);
                    }
                }
            });
        }

        if ($tenantId) {
            return $query->where('id', $tenantId);
        }

        if (! empty($directCompanyIds)) {
            return $query->whereIn('id', $directCompanyIds);
        }

        return $query->whereRaw('1 = 0');
    }

        public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

        public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Datos generales')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre comercial')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('business_name')
                        ->label('Razón social')
                        ->maxLength(255),

                    Select::make('company_group_id')
                        ->label('Grupo de empresas')
                        ->options(fn (): array => \App\Models\CompanyGroup::query()
                            ->where('active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(function (\Filament\Forms\Set $set, $state): void {
                            $set('organization_id', $state
                                ? \App\Models\CompanyGroup::query()->whereKey($state)->value('organization_id')
                                : null
                            );
                        })
                        ->helperText('Jerarquía: Cliente → Grupo de empresas → Empresa → Sucursal.'),

                    \Filament\Forms\Components\Hidden::make('organization_id'),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(table: 'companies', column: 'slug', ignoreRecord: true),

                    Toggle::make('active')
                        ->label('Activa')
                        ->default(true),

                    TextInput::make('tax_id')
                        ->label('RFC')
                        ->maxLength(30),

                    Select::make('tax_regime')
                        ->label('Régimen fiscal')
                        ->options(fn (): array => static::taxRegimeOptions())
                        ->searchable()
                        ->preload()
                        ->helperText('Selecciona el régimen fiscal SAT de la empresa emisora.')
                        ->nullable(),

                    TextInput::make('fiscal_postal_code')
                        ->label('Código postal fiscal')
                        ->maxLength(20),
                ])
                ->columns(2),

            Section::make('Configuración PAC / Timbrado')
                ->description('Credenciales sensibles para timbrado CFDI. Solo visible para superadmin del sistema.')
                ->visible(fn (): bool => static::currentUserIsSystemAdmin())
                ->schema([
                    Select::make('billing_pac_provider')
                        ->label('PAC')
                        ->options([
                            'sw' => 'SW sapien-SmarterWEB',
                        ])
                        ->default('sw')
                        ->required(),

                    Toggle::make('billing_pac_test_env')
                        ->label('Entorno de prueba')
                        ->helperText('Activo: services.test.sw.com.mx. Inactivo: services.sw.com.mx.')
                        ->default(true),

                    TextInput::make('billing_pac_username')
                        ->label('Usuario PAC')
                        ->maxLength(255)
                        ->autocomplete(false),

                    TextInput::make('billing_pac_password')
                        ->label('Contraseña PAC')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->helperText('Se guarda cifrada. Déjala vacía para conservar la contraseña actual.')
                        ->formatStateUsing(fn (): ?string => null)
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->dehydrateStateUsing(fn ($state): string => Crypt::encryptString((string) $state)),

                    TextInput::make('billing_trusted_exporter_number')
                        ->label('Número de exportador confiable')
                        ->maxLength(80),

                    Placeholder::make('billing_pac_last_test_status_display')
                        ->label('Última prueba de conexión')
                        ->content(fn (?Company $record): string => static::pacLastTestText($record)),
                ])
                ->columns(2),


            Section::make('Configuración CSD')
                ->description('Certificado de Sello Digital de la empresa. Necesario para generar y firmar CFDI.')
                ->visible(fn (): bool => static::currentUserIsSystemAdmin())
                ->schema([
                    FileUpload::make('billing_csd_certificate_path')
                        ->label('Certificado CSD (.cer)')
                        ->disk('local')
                        ->directory('companies/csd')
                        ->preserveFilenames()
                        ->downloadable()
                        ->helperText('Sube el archivo .cer del CSD, no el de la FIEL.'),

                    FileUpload::make('billing_csd_key_path')
                        ->label('Llave privada CSD (.key)')
                        ->disk('local')
                        ->directory('companies/csd')
                        ->preserveFilenames()
                        ->downloadable()
                        ->helperText('Sube el archivo .key del CSD.'),

                    TextInput::make('billing_csd_password')
                        ->label('Contraseña CSD')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->helperText('Se guarda cifrada. Déjala vacía para conservar la contraseña actual.')
                        ->formatStateUsing(fn (): ?string => null)
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->dehydrateStateUsing(fn ($state): string => Crypt::encryptString((string) $state)),

                    Placeholder::make('billing_csd_last_test_status_display')
                        ->label('Última validación CSD')
                        ->content(fn (?Company $record): string => static::csdLastTestText($record)),

                    Placeholder::make('billing_csd_data_display')
                        ->label('Datos del certificado')
                        ->content(fn (?Company $record): string => static::csdDataText($record)),
                ])
                ->columns(2),

            Section::make('Branding')
                ->schema([
                    FileUpload::make('logo_path')
                        ->label('Logo principal')
                        ->disk('public')
                        ->directory('companies/logos')
                        ->image(),

                    FileUpload::make('logo_compact_path')
                        ->label('Logo compacto')
                        ->disk('public')
                        ->directory('companies/icons')
                        ->image(),

                    FileUpload::make('favicon_path')
                        ->label('Favicon')
                        ->disk('public')
                        ->directory('companies/favicons')
                        ->image(),
                ])
                ->columns(3),
        
                \Filament\Forms\Components\Section::make('Costeo de inventario')
                    ->description('Configuración base del método de costeo para esta empresa.')
                    ->schema([
                        \Filament\Forms\Components\Select::make('default_costing_method')
                            ->label('Método de costeo por defecto')
                            ->options([
                                'average' => 'Promedio',
                                'fifo' => 'FIFO',
                                'standard' => 'Costo estándar',
                            ])
                            ->default('average')
                            ->required()
                            ->helperText('Se usa cuando el producto y la categoría están en Heredar.'),

                        \Filament\Forms\Components\Select::make('costing_scope')
                            ->label('Alcance de costeo')
                            ->options([
                                'company' => 'Por empresa',
                                'warehouse' => 'Por almacén (preparado para versión futura)',
                            ])
                            ->default('company')
                            ->required()
                            ->helperText('Por ahora debe permanecer Por empresa. Por almacén se activará en una fase posterior.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_compact_path')
                    ->label('Logo')
                    ->disk('public')
                    ->circular(),

                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tax_id')
                    ->label('RFC')
                    ->searchable(),

                TextColumn::make('billing_pac_provider')
                    ->label('PAC')
                    ->visible(fn (): bool => static::currentUserIsSystemAdmin())
                    ->formatStateUsing(fn ($state): string => $state === 'sw' ? 'SW' : ($state ?: 'Sin configurar'))
                    ->badge(),

                IconColumn::make('billing_pac_test_env')
                    ->label('Prueba PAC')
                    ->visible(fn (): bool => static::currentUserIsSystemAdmin())
                    ->boolean(),

                TextColumn::make('billing_pac_last_test_status')
                    ->label('Conexión PAC')
                    ->visible(fn (): bool => static::currentUserIsSystemAdmin())
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

                IconColumn::make('active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->check() && auth()->user()->can('company.update')),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn () => auth()->check() && auth()->user()->can('company.update'))
                    ->before(function ($record) {
                        if (method_exists($record, 'users') && $record->users()->count() > 0) {
                            Notification::make()
                                ->title('No se puede eliminar')
                                ->body('Esta empresa tiene usuarios asignados.')
                                ->danger()
                                ->send();

                            throw new \Exception('Cancel delete');
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function currentUserIsSystemAdmin(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    public static function pacLastTestText(?Company $record): string
    {
        if (! $record || ! $record->billing_pac_last_test_status) {
            return 'Sin pruebas registradas.';
        }

        $status = $record->billing_pac_last_test_status === 'success' ? 'Correcta' : 'Error';
        $date = $record->billing_pac_last_test_at ? (string) $record->billing_pac_last_test_at : 'sin fecha';
        $message = (string) ($record->billing_pac_last_test_message ?? '');

        return "{$status} | {$date} | {$message}";
    }


    public static function csdLastTestText(?Company $record): string
    {
        if (! $record || ! $record->billing_csd_last_test_status) {
            return 'Sin validaciones registradas.';
        }

        $status = $record->billing_csd_last_test_status === 'success' ? 'Correcta' : 'Error';
        $date = $record->billing_csd_last_test_at ? (string) $record->billing_csd_last_test_at : 'sin fecha';
        $message = (string) ($record->billing_csd_last_test_message ?? '');

        return "{$status} | {$date} | {$message}";
    }

    public static function csdDataText(?Company $record): string
    {
        if (! $record) {
            return 'Sin empresa.';
        }

        $serial = (string) ($record->billing_csd_serial_number ?? 'N/D');
        $rfc = (string) ($record->billing_csd_rfc ?? 'N/D');
        $from = $record->billing_csd_valid_from ? (string) $record->billing_csd_valid_from : 'N/D';
        $to = $record->billing_csd_valid_to ? (string) $record->billing_csd_valid_to : 'N/D';

        return "Serie: {$serial} | RFC: {$rfc} | Vigencia: {$from} a {$to}";
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }

    public static function taxRegimeOptions(): array
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('sat_tax_regimes')) {
            $rows = \Illuminate\Support\Facades\DB::table('sat_tax_regimes')
                ->where('active', true)
                ->orderBy('code')
                ->get();

            if ($rows->count() > 0) {
                return $rows
                    ->mapWithKeys(fn ($row): array => [
                        (string) $row->code => (string) $row->code . ' - ' . (string) $row->name,
                    ])
                    ->all();
            }
        }

        return [
            '601' => '601 - General de Ley Personas Morales',
            '603' => '603 - Personas Morales con Fines no Lucrativos',
            '605' => '605 - Sueldos y Salarios e Ingresos Asimilados a Salarios',
            '606' => '606 - Arrendamiento',
            '607' => '607 - Régimen de Enajenación o Adquisición de Bienes',
            '608' => '608 - Demás ingresos',
            '610' => '610 - Residentes en el Extranjero sin Establecimiento Permanente en México',
            '611' => '611 - Ingresos por Dividendos',
            '612' => '612 - Personas Físicas con Actividades Empresariales y Profesionales',
            '614' => '614 - Ingresos por intereses',
            '615' => '615 - Régimen de los ingresos por obtención de premios',
            '616' => '616 - Sin obligaciones fiscales',
            '620' => '620 - Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
            '621' => '621 - Incorporación Fiscal',
            '622' => '622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
            '623' => '623 - Opcional para Grupos de Sociedades',
            '624' => '624 - Coordinados',
            '625' => '625 - Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
            '626' => '626 - Régimen Simplificado de Confianza',
        ];
    }

}
