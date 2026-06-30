<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatCompanyCredentialResource\Pages;
use App\Models\SatCompanyCredential;
use App\Support\FiscalSat\FiscalSatAccess;
use App\Support\FiscalSat\SatCredentialInspector;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Crypt;

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


    /*
     * BEXIA_SC_RESOURCE_RESPONSIVE_V5_79_53C
     * Visual-only responsive marker.
     */

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Empresa')
                    ->extraAttributes(['class' => 'bexia-scred-section bexia-scred-section-company'])
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-company'])
                            ->label('Empresa')
                            ->options(fn () => FiscalSatAccess::companyOptions())
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('rfc')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-rfc bexia-scred-mono'])
                            ->label('RFC')
                            ->maxLength(13)
                            ->required()
                            ->helperText('Debe coincidir con el RFC de la e.firma.'),

                        Forms\Components\TextInput::make('legal_name')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-legal-name'])
                            ->label('Razón social')
                            ->maxLength(255),

                        Forms\Components\Select::make('credential_type')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-type'])
                            ->label('Tipo de credencial')
                            ->options([
                                'efirma' => 'e.firma / FIEL',
                            ])
                            ->default('efirma')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Archivos e.firma')
                    ->extraAttributes(['class' => 'bexia-scred-section bexia-scred-section-files'])
                    ->description('Los archivos se guardan en storage privado. No se publican ni se suben a Git.')
                    ->schema([
                        Forms\Components\FileUpload::make('cer_file_path')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-file bexia-scred-field-cer'])
                            ->label('Archivo .cer')
                            ->disk('local')
                            ->directory('fiscal-sat/credentials/cer')
                            ->preserveFilenames()
                            ->maxSize(2048)
                            ->downloadable()
                            ->helperText('Certificado público de e.firma. Formato esperado: archivo .cer del SAT.'),

                        Forms\Components\FileUpload::make('key_file_path')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-file bexia-scred-field-key'])
                            ->label('Archivo .key')
                            ->disk('local')
                            ->directory('fiscal-sat/credentials/key')
                            ->preserveFilenames()
                            ->maxSize(2048)
                            ->downloadable()
                            ->helperText('Llave privada de e.firma. Formato esperado: archivo .key del SAT. Se guarda en storage privado.'),

                        Forms\Components\TextInput::make('password_encrypted')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-password'])
                            ->label('Contraseña e.firma')
                            ->password()
                            ->revealable()
                            ->formatStateUsing(fn () => null)
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->dehydrateStateUsing(fn ($state): string => Crypt::encryptString((string) $state))
                            ->helperText('No se muestra por seguridad. Captura solo para guardar o actualizar la contraseña.'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Validación')
                    ->extraAttributes(['class' => 'bexia-scred-section bexia-scred-section-validation'])
                    ->schema([
                        Forms\Components\TextInput::make('certificate_serial')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-serial bexia-scred-mono'])
                            ->label('Número de certificado')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DateTimePicker::make('certificate_valid_from')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-datetime bexia-scred-field-valid-from'])
                            ->label('Válido desde')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DateTimePicker::make('certificate_valid_to')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-datetime bexia-scred-field-valid-to'])
                            ->label('Válido hasta')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('credential_status')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-status'])
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
                            ->extraAttributes(['class' => 'bexia-scred-toggle bexia-scred-toggle-enabled'])
                            ->label('Activo para descarga SAT'),

                        Forms\Components\DateTimePicker::make('last_verified_at')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-datetime bexia-scred-field-verified'])
                            ->label('Última verificación')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('last_error_message')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-error'])
                            ->label('Último error')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Notas')
                    ->extraAttributes(['class' => 'bexia-scred-section bexia-scred-section-notes'])
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-scred-field bexia-scred-field-notes'])
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
                    ->extraHeaderAttributes(['class' => 'bexia-scred-col-company bexia-scred-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-scred-col-company bexia-scred-col-primary'])
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rfc')
                    ->extraHeaderAttributes(['class' => 'bexia-scred-col-rfc bexia-scred-mono'])
                    ->extraCellAttributes(['class' => 'bexia-scred-col-rfc bexia-scred-mono'])
                    ->label('RFC')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('legal_name')
                    ->extraHeaderAttributes(['class' => 'bexia-scred-col-legal-name'])
                    ->extraCellAttributes(['class' => 'bexia-scred-col-legal-name'])
                    ->label('Razón social')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('credential_status')
                    ->extraHeaderAttributes(['class' => 'bexia-scred-col-status bexia-scred-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-scred-col-status bexia-scred-col-badge'])
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'configured' => 'Configurado',
                        'verified' => 'Verificado',
                        'error' => 'Error',
                        'disabled' => 'Deshabilitado',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->extraHeaderAttributes(['class' => 'bexia-scred-col-enabled'])
                    ->extraCellAttributes(['class' => 'bexia-scred-col-enabled'])
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\IconColumn::make('cer_file_path')
                    ->extraHeaderAttributes(['class' => 'bexia-scred-col-file bexia-scred-col-cer'])
                    ->extraCellAttributes(['class' => 'bexia-scred-col-file bexia-scred-col-cer'])
                    ->label('.cer')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->cer_file_path)),

                Tables\Columns\IconColumn::make('key_file_path')
                    ->extraHeaderAttributes(['class' => 'bexia-scred-col-file bexia-scred-col-key'])
                    ->extraCellAttributes(['class' => 'bexia-scred-col-file bexia-scred-col-key'])
                    ->label('.key')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->key_file_path)),

                Tables\Columns\TextColumn::make('certificate_valid_to')
                    ->extraHeaderAttributes(['class' => 'bexia-scred-col-date bexia-scred-col-valid-to'])
                    ->extraCellAttributes(['class' => 'bexia-scred-col-date bexia-scred-col-valid-to'])
                    ->label('Vigencia')
                    ->dateTime('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_verified_at')
                    ->extraHeaderAttributes(['class' => 'bexia-scred-col-date bexia-scred-col-verified'])
                    ->extraCellAttributes(['class' => 'bexia-scred-col-date bexia-scred-col-verified'])
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
                Tables\Actions\Action::make('validateEfirma')
                    ->label('Validar e.firma')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn () => FiscalSatAccess::can('fiscal_sat.credentials.manage'))
                    ->action(function (SatCompanyCredential $record): void {
                        $result = app(SatCredentialInspector::class)->inspectAndUpdate($record);

                        if ($result['ok'] ?? false) {
                            Notification::make()
                                ->success()
                                ->title('e.firma validada')
                                ->body('Certificado y llave privada validados correctamente.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->danger()
                            ->title('No se pudo validar la e.firma')
                            ->body($result['message'] ?? 'Error desconocido.')
                            ->send();
                    }),

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
