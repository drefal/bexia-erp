<?php

namespace App\Filament\Resources\ContactResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChildContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'childContacts';

    protected static ?string $title = 'Direcciones y contactos';

    protected static ?string $modelLabel = 'dirección / contacto';

    protected static ?string $pluralModelLabel = 'direcciones y contactos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => $this->getOwnerRecord()?->company_id),

                Forms\Components\Hidden::make('parent_contact_id')
                    ->default(fn (): ?int => $this->getOwnerRecord()?->id),

                Forms\Components\Section::make('Tipo')
                    ->schema([
                        Forms\Components\Select::make('address_type')
                            ->label('Tipo')
                            ->options([
                                'invoice' => 'Dirección de facturación',
                                'delivery' => 'Dirección de entrega',
                                'contact' => 'Contacto',
                                'payment' => 'Contacto de pagos',
                                'purchase' => 'Contacto de compras',
                                'sales' => 'Contacto de ventas',
                                'other' => 'Otro',
                            ])
                            ->default('contact')
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpan(4),

                        Forms\Components\Select::make('contact_type')
                            ->label('Persona / Empresa')
                            ->options([
                                'person' => 'Persona',
                                'company' => 'Empresa',
                            ])
                            ->default('person')
                            ->required()
                            ->native(false)
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(2),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Datos principales')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('commercial_name')
                            ->label('Puesto / referencia')
                            ->maxLength(255)
                            ->helperText('Ejemplo: Compras, Pagos, Almacén, Recepción.')
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->maxLength(80)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('mobile')
                            ->label('Móvil')
                            ->maxLength(80)
                            ->columnSpan(4),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Dirección')
                    ->description('Llena esta sección si el registro es dirección de entrega, facturación u otra ubicación. El CP y colonia usan catálogos SAT.')
                    ->schema([
                        Forms\Components\TextInput::make('street')
                            ->label('Calle')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('exterior_number')
                            ->label('No. exterior')
                            ->maxLength(80)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('interior_number')
                            ->label('No. interior')
                            ->maxLength(80)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('street2')
                            ->label('Calle 2 / referencia')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\Select::make('postal_code')
                            ->label('Código postal')
                            ->placeholder('Busca por código postal SAT')
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->getSearchResultsUsing(fn (string $search): array => static::childSatPostalCodeOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::childSatPostalCodeLabel($value))
                            ->afterStateUpdated(function (Forms\Set $set, $state): void {
                                static::applyChildPostalCodeData($set, $state);
                            })
                            ->helperText('Busca por código postal SAT.')
                            ->columnSpan(3),

                        Forms\Components\Select::make('neighborhood')
                            ->label('Colonia')
                            ->placeholder('Primero selecciona el código postal')
                            ->searchable()
                            ->native(false)
                            ->options(fn (Forms\Get $get): array => static::childNeighborhoodOptions($get('postal_code')))
                            ->getSearchResultsUsing(fn (Forms\Get $get, string $search): array => static::childNeighborhoodOptions($get('postal_code'), $search))
                            ->helperText('Solo muestra colonias del código postal seleccionado.')
                            ->columnSpan(5),

                        Forms\Components\TextInput::make('locality')
                            ->label('Localidad')
                            ->readOnly()
                            ->maxLength(255)
                            ->helperText('Se llena automáticamente desde el catálogo SAT.')
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('municipality')
                            ->label('Municipio / alcaldía')
                            ->readOnly()
                            ->maxLength(255)
                            ->helperText('Se llena automáticamente desde el código postal.')
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('state')
                            ->label('Estado')
                            ->readOnly()
                            ->maxLength(255)
                            ->helperText('Se llena automáticamente desde el código postal.')
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('city')
                            ->label('Ciudad')
                            ->maxLength(255)
                            ->helperText('Si aplica, se llena con la localidad; puedes ajustarla.')
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('country')
                            ->label('País')
                            ->default('México')
                            ->maxLength(120)
                            ->columnSpan(4),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Notas')
                    ->schema([
                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Notas internas')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(12),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Direcciones y contactos')
            ->description('Contactos, direcciones de entrega, facturación y referencias relacionadas con este contacto principal.')
            ->columns([
                Tables\Columns\TextColumn::make('address_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'invoice' => 'Facturación',
                        'delivery' => 'Entrega',
                        'contact' => 'Contacto',
                        'payment' => 'Pagos',
                        'purchase' => 'Compras',
                        'sales' => 'Ventas',
                        'other' => 'Otro',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('commercial_name')
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('mobile')
                    ->label('Móvil')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('postal_code')
                    ->label('CP')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('neighborhood')
                    ->label('Colonia')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar contacto / dirección')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()?->company_id;
                        $data['parent_contact_id'] = $this->getOwnerRecord()?->id;

                        $data['is_customer'] = false;
                        $data['is_supplier'] = false;

                        foreach (['rfc', 'curp', 'sat_country_code'] as $field) {
                            if (isset($data[$field]) && filled($data[$field])) {
                                $data[$field] = strtoupper(trim((string) $data[$field]));
                            }
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()?->company_id;
                        $data['parent_contact_id'] = $this->getOwnerRecord()?->id;

                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Archivar')
                    ->modalHeading('Archivar registro')
                    ->modalSubmitActionLabel('Archivar')
                    ->modalDescription('El registro no se eliminará físicamente; quedará archivado para conservar historial.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Archivar seleccionados')
                        ->modalHeading('Archivar registros seleccionados')
                        ->modalSubmitActionLabel('Archivar')
                        ->modalDescription('Los registros no se eliminarán físicamente; quedarán archivados para conservar historial.'),
                ]),
            ])
            ->defaultSort('address_type');
    }

    protected function canCreate(): bool
    {
        return true;
    }

    protected function canEdit(Model $record): bool
    {
        return true;
    }

    protected function canDelete(Model $record): bool
    {
        return true;
    }

    protected static function childSatPostalCodeOptions(?string $search = null, int $limit = 80): array
    {
        if (! Schema::hasTable('sat_billing_catalog_items')) {
            return [];
        }

        $search = preg_replace('/\D+/', '', trim((string) $search));

        if ($search === '') {
            return [];
        }

        $limit = max(1, min($limit, 30));
        $prefix = $search . '%';

        return DB::table('sat_billing_catalog_items')
            ->where('catalog_key', 'codigo_postal')
            ->where('is_active', true)
            ->where('code', 'like', $prefix)
            ->orderBy('code')
            ->limit($limit)
            ->pluck('code', 'code')
            ->all();
    }

    protected static function childSatPostalCodeLabel($value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected static function childSatExtraValuesFromRow(object $row): array
    {
        $extra = json_decode((string) ($row->extra_attributes ?? ''), true);

        if (! is_array($extra)) {
            return [];
        }

        $values = $extra['values'] ?? [];

        return is_array($values) ? $values : [];
    }

    protected static function childSatNameFromCatalogByCode(string $catalogKey, ?string $code, ?string $stateCode = null): ?string
    {
        $code = trim((string) $code);
        $stateCode = strtoupper(trim((string) $stateCode));

        if ($code === '' || ! Schema::hasTable('sat_billing_catalog_items')) {
            return null;
        }

        $query = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', $catalogKey)
            ->where('code', $code)
            ->where('is_active', true);

        if ($stateCode !== '') {
            $query->whereRaw('extra_attributes::text ilike ?', ['%' . $stateCode . '%']);
        }

        $rows = $query
            ->limit(50)
            ->get(['code', 'name', 'description', 'extra_attributes']);

        if ($rows->isEmpty() && $stateCode !== '') {
            $rows = DB::table('sat_billing_catalog_items')
                ->where('catalog_key', $catalogKey)
                ->where('code', $code)
                ->where('is_active', true)
                ->limit(50)
                ->get(['code', 'name', 'description', 'extra_attributes']);
        }

        foreach ($rows as $row) {
            $values = static::childSatExtraValuesFromRow($row);

            foreach (['C', 'D', 'B', 'A'] as $column) {
                $candidate = trim((string) ($values[$column] ?? ''));

                if ($candidate === '') {
                    continue;
                }

                if ($candidate === $code) {
                    continue;
                }

                if ($stateCode !== '' && strtoupper($candidate) === $stateCode) {
                    continue;
                }

                if (preg_match('/^\d+$/', $candidate)) {
                    continue;
                }

                return $candidate;
            }

            $candidate = trim((string) ($row->name ?: $row->description));

            if ($candidate !== '' && $candidate !== $code && ! preg_match('/^\d+$/', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected static function applyChildPostalCodeData(Forms\Set $set, ?string $postalCode): void
    {
        $postalCode = trim((string) $postalCode);

        $set('neighborhood', null);
        $set('locality', null);
        $set('municipality', null);
        $set('state', null);
        $set('city', null);
        $set('country', 'México');

        if ($postalCode === '' || ! Schema::hasTable('sat_billing_catalog_items')) {
            return;
        }

        $row = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', 'codigo_postal')
            ->where('code', $postalCode)
            ->where('is_active', true)
            ->first(['code', 'name', 'description', 'extra_attributes']);

        if (! $row) {
            return;
        }

        $description = (string) ($row->description ?: $row->name ?: '');

        $extract = function (string $label) use ($description): ?string {
            if (preg_match('/' . preg_quote($label, '/') . '\s+([^\/\s]+)/iu', $description, $matches)) {
                return trim((string) ($matches[1] ?? ''));
            }

            return null;
        };

        $stateCode = strtoupper((string) $extract('Estado'));
        $municipalityCode = (string) $extract('Municipio');
        $localityCode = (string) $extract('Localidad');

        $stateMap = [
            'AGU' => 'Aguascalientes',
            'BCN' => 'Baja California',
            'BCS' => 'Baja California Sur',
            'CAM' => 'Campeche',
            'CHP' => 'Chiapas',
            'CHH' => 'Chihuahua',
            'CMX' => 'Ciudad de México',
            'COA' => 'Coahuila',
            'COL' => 'Colima',
            'DUR' => 'Durango',
            'GUA' => 'Guanajuato',
            'GRO' => 'Guerrero',
            'HID' => 'Hidalgo',
            'JAL' => 'Jalisco',
            'MEX' => 'México',
            'MIC' => 'Michoacán',
            'MOR' => 'Morelos',
            'NAY' => 'Nayarit',
            'NLE' => 'Nuevo León',
            'OAX' => 'Oaxaca',
            'PUE' => 'Puebla',
            'QUE' => 'Querétaro',
            'ROO' => 'Quintana Roo',
            'SLP' => 'San Luis Potosí',
            'SIN' => 'Sinaloa',
            'SON' => 'Sonora',
            'TAB' => 'Tabasco',
            'TAM' => 'Tamaulipas',
            'TLA' => 'Tlaxcala',
            'VER' => 'Veracruz',
            'YUC' => 'Yucatán',
            'ZAC' => 'Zacatecas',
        ];

        $stateName = $stateCode ? ($stateMap[$stateCode] ?? $stateCode) : null;

        $municipalityName = static::childSatNameFromCatalogByCode('municipio', $municipalityCode, $stateCode);
        $localityName = static::childSatNameFromCatalogByCode('localidad', $localityCode, $stateCode);

        $set('state', $stateName);
        $set('municipality', $municipalityName ?: ($municipalityCode ?: null));
        $set('locality', $localityName ?: ($localityCode ?: null));
        $set('city', $localityName ?: ($localityCode ?: null));
        $set('country', 'México');
    }

    protected static function childNeighborhoodOptions(?string $postalCode, ?string $search = null, int $limit = 80): array
    {
        if (! Schema::hasTable('sat_billing_catalog_items')) {
            return [];
        }

        $postalCode = trim((string) $postalCode);
        $search = trim((string) $search);

        if ($postalCode === '') {
            return [];
        }

        $limit = max(1, min($limit, 50));

        // Catálogo colonias importado:
        // values.A = código colonia
        // values.B = código postal
        // values.C = nombre colonia
        $query = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', 'colonia')
            ->where('is_active', true)
            ->whereRaw('extra_attributes::text ilike ?', ['%"B":"' . $postalCode . '"%'])
            ->select(['code', 'name', 'description', 'extra_attributes']);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->whereRaw('extra_attributes::text ilike ?', [$like]);
        }

        return $query
            ->limit($limit)
            ->get()
            ->mapWithKeys(function ($row): array {
                $values = static::childSatExtraValuesFromRow($row);

                $name = trim((string) ($values['C'] ?? ''));

                if ($name === '') {
                    $name = trim((string) ($row->name ?: $row->description ?: $row->code));
                }

                return [$name => $name];
            })
            ->filter(fn ($label) => filled($label))
            ->sort()
            ->all();
    }

}
