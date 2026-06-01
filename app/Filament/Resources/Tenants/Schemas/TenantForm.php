<?php

namespace App\Filament\Resources\Tenants\Schemas;

use App\Support\Filament\PanelAppearance;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        $appearance = app(PanelAppearance::class);

        return $schema
            ->components([
                Section::make('Anagrafica tenant')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required(),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required(),
                        TextInput::make('domain')
                            ->label('Dominio'),
                        TextInput::make('status')
                            ->label('Stato')
                            ->required()
                            ->default('active'),
                    ])
                    ->columns(2),
                Section::make('Database tenant')
                    ->description('Credenziali e database dedicato del tenant.')
                    ->schema([
                        TextInput::make('db_host')
                            ->label('Host DB tenant')
                            ->placeholder(env('TENANT_DB_HOST', '127.0.0.1')),
                        TextInput::make('db_port')
                            ->label('Porta DB tenant')
                            ->numeric()
                            ->placeholder((string) env('TENANT_DB_PORT', '3306')),
                        TextInput::make('db_database')
                            ->label('Database tenant')
                            ->helperText('Se vuoto, viene generato automaticamente dal codice tenant.'),
                        TextInput::make('db_username')
                            ->label('Utente DB tenant')
                            ->placeholder(env('TENANT_DB_USERNAME', 'root'))
                            ->autocomplete(false)
                            ->extraInputAttributes([
                                'data-lpignore' => 'true',
                                'data-1p-ignore' => 'true',
                            ])
                            ->helperText('Lascia vuoto per usare l utente MySQL predefinito configurato nel server tenant.'),
                        TextInput::make('db_password')
                            ->label('Password DB tenant')
                            ->password()
                            ->autocomplete('new-password')
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->extraInputAttributes([
                                'data-lpignore' => 'true',
                                'data-1p-ignore' => 'true',
                            ])
                            ->helperText('Lascia vuoto per usare o mantenere la password del server tenant gia configurata.'),
                    ])
                    ->columns(2),
                Section::make('Aspetto del pannello')
                    ->description('Determina il colpo d occhio del tenant nella testata, nella sidebar e nei colori di sfondo.')
                    ->schema([
                        Select::make('panel_palette')
                            ->label('Palette sfondo')
                            ->options($appearance->paletteOptions())
                            ->default($appearance->defaultTenantPalette())
                            ->native(false)
                            ->required()
                            ->helperText('Scegli una delle palette disponibili per topbar, sidebar e fondi.'),
                        Select::make('panel_theme_mode')
                            ->label('Tema')
                            ->options($appearance->themeOptions())
                            ->default($appearance->defaultTenantThemeMode())
                            ->native(false)
                            ->required(),
                        Select::make('panel_font_family')
                            ->label('Font')
                            ->options($appearance->fontOptions())
                            ->default($appearance->defaultTenantFontFamily())
                            ->native(false)
                            ->required()
                            ->helperText('Il font viene caricato da Google Fonts.'),
                    ])
                    ->columns(3),
            ]);
    }
}
