<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CompanyResource\Pages;
use App\Filament\Admin\Resources\CompanyResource\RelationManagers\UsersRelationManager;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $navigationLabel = 'Empresas';
    protected static ?int $navigationSort = 30;
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon  = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Configuración Bexia';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'Empresa';
    protected static ?string $pluralModelLabel = 'Empresas';

    // BEXIA_V57917C_ADMIN_COMPANY_PERMISSIONS
    protected static function bexiaCanManageGlobalCompanies(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanManageGlobalCompanies();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::bexiaCanManageGlobalCompanies();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::bexiaCanManageGlobalCompanies();
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanManageGlobalCompanies();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos generales')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Empresa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->helperText('Identificador interno. Ejemplo: tintoreria-estrella')
                            ->required()
                            ->rule('alpha_dash')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(6),

                        Forms\Components\Select::make('membership_status')
                            ->label('Estatus de membresía')
                            ->options([
                                'active' => 'Activa',
                                'trial' => 'Prueba',
                                'suspended' => 'Suspendida',
                                'canceled' => 'Cancelada',
                            ])
                            ->default('active')
                            ->required()
                            ->columnSpan(6),

                        Forms\Components\DatePicker::make('paid_until')
                            ->label('Pagado hasta')
                            ->native(false)
                            ->columnSpan(6),

                        Forms\Components\DatePicker::make('last_payment_at')
                            ->label('Último pago')
                            ->native(false)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('max_branches')
                            ->label('Máximo de sucursales')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('max_users')
                            ->label('Máximo de usuarios')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->columnSpan(6),

                        Forms\Components\Toggle::make('free_trial')
                            ->label('Tiempo libre')
                            ->default(false)
                            ->columnSpan(6),

                        Forms\Components\Toggle::make('active')
                            ->label('Activa')
                            ->default(true)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('tax_id')
                            ->label('RFC')
                            ->maxLength(255)
                            ->columnSpan(6),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(5)
                            ->columnSpan(12),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Branding')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo principal')
                            ->image()
                            ->disk('public')
                            ->directory('companies/logos')
                            ->visibility('public')
                            ->imagePreviewHeight('120')
                            ->helperText('Logo horizontal para encabezados y branding general.')
                            ->columnSpan(6),

                        Forms\Components\FileUpload::make('logo_compact_path')
                            ->label('Logo compacto')
                            ->image()
                            ->disk('public')
                            ->directory('companies/icons')
                            ->visibility('public')
                            ->imagePreviewHeight('100')
                            ->helperText('Ícono cuadrado para sidebar colapsado.')
                            ->columnSpan(6),
                    ])
                    ->columns(12),
            ])
            ->columns(12);
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('membership_status')
                    ->label('Membresía')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'active' => 'Activa',
                        'trial' => 'Prueba',
                        'suspended' => 'Suspendida',
                        'canceled' => 'Cancelada',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'active',
                        'warning' => 'trial',
                        'danger' => 'suspended',
                        'gray' => 'canceled',
                    ]),

                Tables\Columns\TextColumn::make('max_branches')
                    ->label('Sucursales')
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_users')
                    ->label('Usuarios')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_until')
                    ->label('Pagado hasta')
                    ->date()
                    ->sortable(),

                Tables\Columns\IconColumn::make('free_trial')
                    ->label('Prueba')
                    ->boolean(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit'   => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
