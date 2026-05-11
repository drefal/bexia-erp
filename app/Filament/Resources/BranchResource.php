<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Models\Branch;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;
    protected static bool $isScopedToTenant = false;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 40;
    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('company.view');
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('company.view');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('company.update');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('company.update');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->check() && auth()->user()->can('company.update');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('company.update');
    }

    public static function getNavigationLabel(): string
    {
        return 'Sucursales';
    }

    public static function getModelLabel(): string
    {
        return 'Sucursal';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Sucursales';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos de la sucursal')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('code')
                        ->label('Código')
                        ->maxLength(100),

                    Forms\Components\Toggle::make('active')
                        ->label('Activa')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('Dirección')
                ->schema([
                    Forms\Components\TextInput::make('address_line1')
                        ->label('Dirección')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('address_line2')
                        ->label('Dirección 2')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('city')
                        ->label('Ciudad')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('state')
                        ->label('Estado')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('postal_code')
                        ->label('Código postal')
                        ->maxLength(20),

                    Forms\Components\TextInput::make('country')
                        ->label('País')
                        ->default('México')
                        ->maxLength(100),
                ])
                ->columns(2),

            Forms\Components\Section::make('Contacto')
                ->schema([
                    Forms\Components\TextInput::make('contact_name')
                        ->label('Nombre de contacto')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('contact_phone')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(50),

                    Forms\Components\TextInput::make('contact_email')
                        ->label('Correo')
                        ->email()
                        ->maxLength(255),
                ])
                ->columns(2),

            Forms\Components\Section::make('Notas')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contacto')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->before(function () {
                        Notification::make()
                            ->title('Sucursal eliminada')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
