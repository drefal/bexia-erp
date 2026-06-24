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
                            ->required(),

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

                Tables\Columns\TextColumn::make('latitude')
                    ->label('Latitud')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('longitude')
                    ->label('Longitud')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('radius_meters')
                    ->label('Radio')
                    ->suffix(' m')
                    ->sortable(),

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

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $data['company_id'] ?? Filament::getTenant()?->getKey();
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
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
