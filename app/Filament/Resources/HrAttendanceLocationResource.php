<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HrAttendanceLocationResource\Pages;
use App\Models\Branch;
use App\Models\HrAttendanceLocation;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HrAttendanceLocationResource extends Resource
{
    protected static ?string $model = HrAttendanceLocation::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $tenantRelationshipName = 'hrAttendanceLocations';

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Geocercas asistencia';

    protected static ?string $modelLabel = 'Geocerca de asistencia';

    protected static ?string $pluralModelLabel = 'Geocercas de asistencia';

    protected static ?int $navigationSort = 38;

    protected static function bexiaCanGeofencePermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission)) {
            return true;
        }

        if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('hr.menu.view')) {
            return true;
        }

        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanGeofencePermission('rrhh.geocercas.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanGeofencePermission('rrhh.geocercas.ver');
    }

    public static function canView($record): bool
    {
        return static::bexiaCanGeofencePermission('rrhh.geocercas.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanGeofencePermission('rrhh.geocercas.crear');
    }

    public static function canEdit($record): bool
    {
        return static::bexiaCanGeofencePermission('rrhh.geocercas.editar');
    }

    public static function canDelete($record): bool
    {
        return static::bexiaCanGeofencePermission('rrhh.geocercas.eliminar');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $tenant = Filament::getTenant();

        if ($tenant?->getKey()) {
            $query->where('company_id', $tenant->getKey());
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn () => Filament::getTenant()?->getKey())
                    ->required(),

                Forms\Components\Section::make('Ubicación autorizada')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->maxLength(255),

                        Forms\Components\Select::make('branch_id')
                            ->label('Sucursal')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Branch::query()
                                    ->when($tenant?->getKey(), fn ($query) => $query->where('company_id', $tenant->getKey()))
                                    ->where('active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload(),

                        Forms\Components\Textarea::make('address')
                            ->label('Dirección / referencia')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('geofence_type')
                            ->label('Tipo de geocerca')
                            ->options([
                                'circle' => 'Círculo: centro + radio',
                                'polygon' => 'Polígono: forma dibujada por puntos',
                            ])
                            ->default('circle')
                            ->required()
                            ->live()
                            ->helperText('Puedes capturar el polígono escribiendo coordenadas JSON o usando el mapa visual inferior.'),

                        Forms\Components\TextInput::make('latitude')
                            ->label('Latitud')
                            ->numeric()
                            ->required()
                            ->helperText('Ejemplo: 19.4326077'),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitud')
                            ->numeric()
                            ->required()
                            ->helperText('Ejemplo: -99.1332080'),

                        Forms\Components\TextInput::make('radius_meters')
                            ->label('Radio permitido en metros')
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(5000)
                            ->default(100)
                            ->required()
                            ->visible(fn (Forms\Get $get): bool => ($get('geofence_type') ?: 'circle') === 'circle'),

                        Forms\Components\Textarea::make('polygon_coordinates')
                            ->label('Coordenadas del polígono')
                            ->rows(8)
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get): bool => ($get('geofence_type') ?: 'circle') === 'polygon')
                            ->helperText('Formato: [[lat,lng],[lat,lng],[lat,lng]]. Ejemplo: [[19.357633,-99.107700],[19.357567,-99.106894],[19.358600,-99.106870],[19.358673,-99.107488]]')
                            ->dehydrateStateUsing(function ($state) {
                                if (blank($state)) {
                                    return null;
                                }

                                if (is_array($state)) {
                                    return $state;
                                }

                                $decoded = json_decode((string) $state, true);

                                return is_array($decoded) ? $decoded : null;
                            })
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state),

                        Forms\Components\ViewField::make('geofence_map_picker')
                            ->label('Mapa')
                            ->view('filament.forms.components.geofence-map-picker')
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('accuracy_required_meters')
                            ->label('Precisión GPS máxima aceptada')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(5000)
                            ->helperText('Opcional. Si el celular reporta peor precisión, se marca para revisión.'),
                    ]),

                Forms\Components\Section::make('Reglas')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('allow_mobile_clock_in')
                            ->label('Permitir checada móvil')
                            ->default(true),

                        Forms\Components\Toggle::make('requires_review_when_outside')
                            ->label('Revisar si está fuera')
                            ->default(true),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('geofence_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => $state === 'polygon' ? 'Polígono' : 'Círculo')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('latitude')
                    ->label('Latitud')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('longitude')
                    ->label('Longitud')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('radius_meters')
                    ->label('Radio')
                    ->suffix(' m')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('allow_mobile_clock_in')
                    ->label('Móvil')
                    ->boolean(),

                Tables\Columns\IconColumn::make('requires_review_when_outside')
                    ->label('Revisión')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),

                Tables\Filters\TernaryFilter::make('allow_mobile_clock_in')
                    ->label('Permite móvil'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('name');
    }


    protected static function normalizePolygonCoordinates(array $data): array
    {
        $type = $data['geofence_type'] ?? 'circle';

        if ($type !== 'polygon') {
            $data['polygon_coordinates'] = null;

            return $data;
        }

        $points = $data['polygon_coordinates'] ?? null;

        if (is_string($points)) {
            $decoded = json_decode($points, true);
            $points = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($points) || count($points) < 3) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'polygon_coordinates' => 'El polígono debe tener al menos 3 puntos en formato [[lat,lng],[lat,lng],[lat,lng]].',
            ]);
        }

        $normalized = collect($points)
            ->map(function ($point): ?array {
                if (! is_array($point) || count($point) < 2) {
                    return null;
                }

                $lat = (float) $point[0];
                $lng = (float) $point[1];

                if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                    return null;
                }

                return [$lat, $lng];
            })
            ->filter()
            ->values()
            ->all();

        if (count($normalized) < 3) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'polygon_coordinates' => 'El polígono debe tener al menos 3 puntos válidos.',
            ]);
        }

        $data['polygon_coordinates'] = $normalized;

        if (empty($data['radius_meters'])) {
            $data['radius_meters'] = 100;
        }

        return $data;
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data = static::normalizePolygonCoordinates($data);

        $data['company_id'] = $data['company_id'] ?? Filament::getTenant()?->getKey();
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $data = static::normalizePolygonCoordinates($data);

        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHrAttendanceLocations::route('/'),
            'create' => Pages\CreateHrAttendanceLocation::route('/create'),
            'edit' => Pages\EditHrAttendanceLocation::route('/{record}/edit'),
        ];
    }
}
