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
    protected static ?string $navigationGroup = 'Configuración empresa';
    protected static ?int $navigationSort = 10;
    protected static ?string $tenantOwnershipRelationshipName = null;

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

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.branchresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('settings.access')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('settings.access')
            );
    }

    /*
     * BEXIA_BRANCH_RESOURCE_RESPONSIVE_V5_79_64C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos de la sucursal')
                ->extraAttributes(['class' => 'bexia-branch-section bexia-branch-section-main'])
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-name'])
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('code')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-code bexia-branch-code-field'])
                        ->label('Código')
                        ->maxLength(100),

                    Forms\Components\Toggle::make('active')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-active bexia-branch-toggle'])
                        ->label('Activa')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('Dirección')
                ->extraAttributes(['class' => 'bexia-branch-section bexia-branch-section-address'])
                ->schema([
                    Forms\Components\TextInput::make('address_line1')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-address-line1'])
                        ->label('Dirección')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('address_line2')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-address-line2'])
                        ->label('Dirección 2')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('city')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-city'])
                        ->label('Ciudad')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('state')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-state'])
                        ->label('Estado')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('postal_code')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-postal-code bexia-branch-code-field'])
                        ->label('Código postal')
                        ->maxLength(20),

                    Forms\Components\TextInput::make('country')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-country'])
                        ->label('País')
                        ->default('México')
                        ->maxLength(100),
                ])
                ->columns(2),

            Forms\Components\Section::make('Contacto')
                ->extraAttributes(['class' => 'bexia-branch-section bexia-branch-section-contact'])
                ->schema([
                    Forms\Components\TextInput::make('contact_name')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-contact-name'])
                        ->label('Nombre de contacto')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('contact_phone')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-contact-phone bexia-branch-phone-field'])
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(50),

                    Forms\Components\TextInput::make('contact_email')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-contact-email bexia-branch-email-field'])
                        ->label('Correo')
                        ->email()
                        ->maxLength(255),
                ])
                ->columns(2),

            Forms\Components\Section::make('Notas')
                ->extraAttributes(['class' => 'bexia-branch-section bexia-branch-section-notes'])
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->extraAttributes(['class' => 'bexia-branch-field bexia-branch-field-notes'])
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
                    ->extraHeaderAttributes(['class' => 'bexia-branch-col-id bexia-branch-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-branch-col-id bexia-branch-col-number'])
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-branch-col-name bexia-branch-col-primary-text'])
                    ->extraCellAttributes(['class' => 'bexia-branch-col-name bexia-branch-col-primary-text'])
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes(['class' => 'bexia-branch-col-code bexia-branch-col-code-text'])
                    ->extraCellAttributes(['class' => 'bexia-branch-col-code bexia-branch-col-code-text'])
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->extraHeaderAttributes(['class' => 'bexia-branch-col-city bexia-branch-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-branch-col-city bexia-branch-col-context'])
                    ->label('Ciudad')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('contact_name')
                    ->extraHeaderAttributes(['class' => 'bexia-branch-col-contact-name bexia-branch-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-branch-col-contact-name bexia-branch-col-context'])
                    ->label('Contacto')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->extraHeaderAttributes(['class' => 'bexia-branch-col-active bexia-branch-col-icon'])
                    ->extraCellAttributes(['class' => 'bexia-branch-col-active bexia-branch-col-icon'])
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->extraHeaderAttributes(['class' => 'bexia-branch-col-created-at bexia-branch-col-date'])
                    ->extraCellAttributes(['class' => 'bexia-branch-col-created-at bexia-branch-col-date'])
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
