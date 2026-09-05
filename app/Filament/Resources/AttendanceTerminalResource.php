<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceTerminalResource\Pages;
use App\Models\AttendanceTerminal;
use App\Models\Branch;
use App\Support\Navigation\BexiaMenuRuntime;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AttendanceTerminalResource extends Resource
{
    protected static ?string $model = AttendanceTerminal::class;

    protected static ?string $modelLabel = 'terminal de asistencia';

    protected static ?string $pluralModelLabel = 'terminales de asistencia';

    protected static ?string $navigationLabel = 'Terminales de asistencia';

    protected static ?string $navigationIcon = 'heroicon-o-device-tablet';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?int $navigationSort = 45;

    protected static ?string $slug = 'terminales-asistencia';

    /*
     * La tabla ya contiene company_id.
     *
     * Igual que otros recursos RRHH de Bexia, el alcance se controla
     * explicitamente con Filament::getTenant().
     */
    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function shouldRegisterNavigation(): bool
    {
        return BexiaMenuRuntime::shouldRegister(
            'resources.attendanceterminalresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->check()
            && (bool) auth()->user()?->can('company.update');
    }

    public static function canCreate(): bool
    {
        return auth()->check()
            && Filament::getTenant() !== null
            && (bool) auth()->user()?->can('company.update');
    }

    public static function canView(Model $record): bool
    {
        return static::userCanManageRecord($record);
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanManageRecord($record);
    }

    public static function canDelete(Model $record): bool
    {
        /*
         * Por ahora las terminales NO se eliminan desde interfaz.
         *
         * Posteriormente se implementara bloquear/desactivar terminal,
         * conservando evidencia y trazabilidad.
         */
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function userCanManageRecord(Model $record): bool
    {
        if (
            ! auth()->check()
            || ! (bool) auth()->user()?->can('company.update')
        ) {
            return false;
        }

        $tenantId = static::currentCompanyId();

        if (! $tenantId) {
            return false;
        }

        return (int) $record->company_id === $tenantId;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = static::currentCompanyId();

        $query = parent::getEloquentQuery()
            ->with([
                'company',
                'branch',
            ]);

        if (! $tenantId) {
            /*
             * Nunca presentar terminales sin un tenant/empresa activo.
             */
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            'company_id',
            $tenantId,
        );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Asignación de la terminal')
                    ->description(
                        'La terminal queda ligada a la empresa actual de Bexia. '
                        .'Selecciona la sucursal física donde estará instalada.'
                    )
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('company_display')
                            ->label('Empresa')
                            ->content(
                                fn (?AttendanceTerminal $record): string =>
                                    (string) (
                                        $record?->company?->name
                                        ?? Filament::getTenant()?->name
                                        ?? 'Empresa actual'
                                    )
                            ),

                        Forms\Components\Select::make('branch_id')
                            ->label('Sucursal / ubicación física')
                            ->options(
                                fn (?AttendanceTerminal $record): array =>
                                    static::branchOptions($record)
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText(
                                'Solo se muestran sucursales de la empresa actual.'
                            ),

                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('CEDIS-T01')
                            ->dehydrateStateUsing(
                                fn ($state): string =>
                                    strtoupper(trim((string) $state))
                            )
                            ->helperText(
                                'Identificador corto y único dentro de la empresa. '
                                .'Ejemplo: CEDIS-T01.'
                            ),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('Terminal CEDIS Entrada')
                            ->dehydrateStateUsing(
                                fn ($state): string =>
                                    trim((string) $state)
                            ),

                        Forms\Components\Toggle::make('active')
                            ->label('Terminal activa')
                            ->default(true)
                            ->inline(false)
                            ->helperText(
                                'Desactívala para impedir su uso operativo. '
                                .'El bloqueo de seguridad se agregará en el siguiente bloque.'
                            ),
                    ]),

                Forms\Components\Section::make('Identidad técnica')
                    ->description(
                        'Datos técnicos del dispositivo. '
                        .'Se completarán automáticamente durante la vinculación del kiosco.'
                    )
                    ->columns(2)
                    ->visible(
                        fn (?AttendanceTerminal $record): bool =>
                            $record !== null
                    )
                    ->schema([
                        Forms\Components\TextInput::make('uuid')
                            ->label('UUID de terminal')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(
                                'Identificador interno permanente de Bexia. '
                                .'No corresponde a la MAC del equipo.'
                            ),

                        Forms\Components\TextInput::make('device_name')
                            ->label('Nombre del dispositivo')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('device_model')
                            ->label('Modelo')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('platform')
                            ->label('Plataforma')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('app_version')
                            ->label('Versión del kiosco')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('mac_address')
                            ->label('MAC informativa')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(
                                'Solo auditoría. La MAC NO autentica la terminal.'
                            ),

                        Forms\Components\TextInput::make('last_ip_address')
                            ->label('Última IP')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Placeholder::make('last_seen_display')
                            ->label('Última conexión')
                            ->content(
                                function (?AttendanceTerminal $record): string {
                                    if (! $record?->last_seen_at) {
                                        return 'Nunca';
                                    }

                                    return $record->last_seen_at
                                        ->timezone('America/Mexico_City')
                                        ->format('d/m/Y H:i:s');
                                }
                            ),

                        Forms\Components\Placeholder::make('security_status')
                            ->label('Estado de seguridad')
                            ->content(
                                fn (?AttendanceTerminal $record): string =>
                                    $record?->blocked_at
                                        ? 'BLOQUEADA'
                                        : (
                                            $record?->active
                                                ? 'ACTIVA'
                                                : 'INACTIVA'
                                        )
                            ),

                        Forms\Components\Textarea::make('blocked_reason')
                            ->label('Motivo de bloqueo')
                            ->rows(2)
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(
                                fn (?AttendanceTerminal $record): bool =>
                                    $record?->blocked_at !== null
                            )
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
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Terminal')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('estado_terminal')
                    ->label('Estado')
                    ->getStateUsing(
                        fn (AttendanceTerminal $record): string =>
                            $record->blocked_at
                                ? 'Bloqueada'
                                : (
                                    $record->active
                                        ? 'Activa'
                                        : 'Inactiva'
                                )
                    )
                    ->badge()
                    ->color(
                        fn (string $state): string => match ($state) {
                            'Activa' => 'success',
                            'Bloqueada' => 'danger',
                            default => 'gray',
                        }
                    ),

                Tables\Columns\TextColumn::make('device_model')
                    ->label('Dispositivo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('platform')
                    ->label('Plataforma')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Última conexión')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Mexico_City')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_ip_address')
                    ->label('Última IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Mexico_City')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Activa')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas')
                    ->placeholder('Todas'),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(
                        fn (): array =>
                            static::branchOptions()
                    ),

                Tables\Filters\Filter::make('blocked')
                    ->label('Solo bloqueadas')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereNotNull('blocked_at')
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Abrir'),
            ])
            ->bulkActions([])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceTerminals::route('/'),
            'create' => Pages\CreateAttendanceTerminal::route('/create'),
            'edit' => Pages\EditAttendanceTerminal::route('/{record}/edit'),
        ];
    }

    /**
     * Empresa operativa actual.
     *
     * El tenant es la fuente principal.
     * En edición el record solo se utiliza como respaldo técnico.
     */
    public static function currentCompanyId(
        ?AttendanceTerminal $record = null
    ): ?int {
        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId) {
            return (int) $tenantId;
        }

        if ($record?->company_id) {
            return (int) $record->company_id;
        }

        return null;
    }

    /**
     * Sucursales válidas de la empresa actual.
     *
     * Se permiten:
     * - sucursales activas;
     * - la sucursal ya asignada al registro, aunque después haya sido
     *   desactivada, para no romper una edición histórica.
     */
    public static function branchOptions(
        ?AttendanceTerminal $record = null
    ): array {
        $companyId = static::currentCompanyId($record);

        if (! $companyId) {
            return [];
        }

        $currentBranchId = (int) ($record?->branch_id ?? 0);

        return Branch::query()
            ->where('company_id', $companyId)
            ->where(
                function (Builder $query) use ($currentBranchId): void {
                    $query->where('active', true);

                    if ($currentBranchId > 0) {
                        $query->orWhere(
                            'id',
                            $currentBranchId,
                        );
                    }
                }
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'active',
            ])
            ->mapWithKeys(
                function (Branch $branch): array {
                    $label = trim(
                        (
                            filled($branch->code)
                                ? $branch->code.' - '
                                : ''
                        )
                        .$branch->name
                    );

                    if (! $branch->active) {
                        $label .= ' (inactiva)';
                    }

                    return [
                        (int) $branch->id => $label,
                    ];
                }
            )
            ->all();
    }

    /**
     * Normaliza y valida el registro antes de persistir.
     *
     * Regla crítica:
     * branch.company_id DEBE coincidir con terminal.company_id.
     *
     * No confiamos únicamente en las opciones mostradas en HTML;
     * se vuelve a comprobar del lado servidor.
     */
    public static function prepareDataForPersistence(
        array $data,
        ?AttendanceTerminal $record = null
    ): array {
        $companyId = static::currentCompanyId($record);

        if (! $companyId) {
            throw ValidationException::withMessages([
                'branch_id' => 'No fue posible determinar la empresa actual.',
            ]);
        }

        if (
            $record
            && (int) $record->company_id !== $companyId
        ) {
            throw ValidationException::withMessages([
                'branch_id' =>
                    'La terminal no pertenece a la empresa actual.',
            ]);
        }

        $branchId = (int) ($data['branch_id'] ?? 0);

        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'branch_id' => 'Selecciona una sucursal.',
            ]);
        }

        $branch = Branch::query()
            ->whereKey($branchId)
            ->where('company_id', $companyId)
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'branch_id' =>
                    'La sucursal seleccionada no pertenece a la empresa actual.',
            ]);
        }

        $sameExistingBranch = $record
            && (int) $record->branch_id === $branchId;

        if (
            ! $branch->active
            && ! $sameExistingBranch
        ) {
            throw ValidationException::withMessages([
                'branch_id' =>
                    'No puedes asignar una terminal a una sucursal inactiva.',
            ]);
        }

        $code = strtoupper(
            trim((string) ($data['code'] ?? ''))
        );

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => 'Captura el código de la terminal.',
            ]);
        }

        $name = trim(
            (string) ($data['name'] ?? '')
        );

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Captura el nombre de la terminal.',
            ]);
        }

        $duplicate = AttendanceTerminal::withTrashed()
            ->where('company_id', $companyId)
            ->whereRaw(
                'UPPER(code) = ?',
                [$code],
            );

        if ($record?->id) {
            $duplicate->whereKeyNot(
                $record->getKey(),
            );
        }

        if ($duplicate->exists()) {
            throw ValidationException::withMessages([
                'code' =>
                    'Ya existe una terminal con ese código en esta empresa.',
            ]);
        }

        /*
         * La empresa se fuerza desde el servidor.
         * Nunca se acepta company_id del navegador.
         */
        $data['company_id'] = $companyId;
        $data['branch_id'] = $branchId;
        $data['code'] = $code;
        $data['name'] = $name;

        /*
         * Campos de seguridad/telemetría que NO pueden modificarse
         * desde este formulario administrativo.
         */
        foreach ([
            'uuid',
            'token_hash',
            'pairing_code_hash',
            'pairing_expires_at',
            'blocked_at',
            'blocked_reason',
            'device_name',
            'device_model',
            'platform',
            'app_version',
            'mac_address',
            'last_seen_at',
            'last_ip_address',
            'last_user_agent',
            'capabilities',
            'settings',
        ] as $protectedField) {
            unset($data[$protectedField]);
        }

        return $data;
    }
}
