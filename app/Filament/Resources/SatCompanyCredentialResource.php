<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatCompanyCredentialResource\Pages;
use App\Models\SatCompanyCredential;
use App\Support\FiscalSat\FiscalSatAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatCompanyCredentialResource extends Resource
{
    protected static ?string $model = SatCompanyCredential::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Configuración SAT';

    protected static ?string $modelLabel = 'Configuración SAT';

    protected static ?string $pluralModelLabel = 'Configuración SAT';

    protected static ?string $navigationGroup = 'Fiscal SAT';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return FiscalSatAccess::can('fiscal_sat.credentials.manage');
    }

    public static function canCreate(): bool
    {
        return FiscalSatAccess::can('fiscal_sat.credentials.manage');
    }

    public static function canEdit($record): bool
    {
        return FiscalSatAccess::can('fiscal_sat.credentials.manage');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Empresa')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->options(fn () => FiscalSatAccess::companyOptions())
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('rfc')
                            ->label('RFC')
                            ->maxLength(13)
                            ->required(),

                        Forms\Components\TextInput::make('legal_name')
                            ->label('Razón social')
                            ->maxLength(255),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Estado SAT')
                    ->schema([
                        Forms\Components\Select::make('credential_status')
                            ->label('Estado')
                            ->options([
                                'pending' => 'Pendiente',
                                'configured' => 'Configurado',
                                'verified' => 'Verificado',
                                'error' => 'Error',
                                'disabled' => 'Deshabilitado',
                            ])
                            ->default('pending')
                            ->required(),

                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Activo'),

                        Forms\Components\TextInput::make('certificate_serial')
                            ->label('Número de certificado')
                            ->maxLength(255),

                        Forms\Components\DateTimePicker::make('last_verified_at')
                            ->label('Última verificación'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Notas')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas internas')
                            ->rows(4),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rfc')
                    ->label('RFC')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('legal_name')
                    ->label('Razón social')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('credential_status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_verified_at')
                    ->label('Última verificación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('credential_status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'configured' => 'Configurado',
                        'verified' => 'Verificado',
                        'error' => 'Error',
                        'disabled' => 'Deshabilitado',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return FiscalSatAccess::scopeCompany(parent::getEloquentQuery());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSatCompanyCredentials::route('/'),
            'create' => Pages\CreateSatCompanyCredential::route('/create'),
            'edit' => Pages\EditSatCompanyCredential::route('/{record}/edit'),
        ];
    }
}
