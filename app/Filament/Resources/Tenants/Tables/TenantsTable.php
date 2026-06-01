<?php

namespace App\Filament\Resources\Tenants\Tables;

use App\Filament\Actions\TenantAdminActionGroup;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Tenant;
use App\Support\Filament\PanelAppearance;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('domain')
                    ->label('Dominio')
                    ->searchable(),
                TextColumn::make('db_database')
                    ->label('Database tenant')
                    ->searchable(),
                TextColumn::make('tenantAdmin.name')
                    ->label('Admin tenant')
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'Nessun utente collegato')
                    ->description(fn (Tenant $record): string => $record->tenantAdmin?->email ?: 'Crea l accesso admin per questo tenant.')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('tenantAdmin', function (Builder $query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('panel_palette')
                    ->label('Palette')
                    ->formatStateUsing(fn (?string $state): string => app(PanelAppearance::class)->paletteLabel($state))
                    ->badge(),
                TextColumn::make('panel_theme_mode')
                    ->label('Tema')
                    ->formatStateUsing(fn (?string $state): string => app(PanelAppearance::class)->themeLabel($state))
                    ->badge(),
                TextColumn::make('panel_font_family')
                    ->label('Font')
                    ->formatStateUsing(fn (?string $state): string => app(PanelAppearance::class)->fontLabel($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Stato')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                TenantAdminActionGroup::make(fn (?Tenant $record) => $record),
                Action::make('entraNelTenant')
                    ->label('Entra nel tenant')
                    ->action(function (Tenant $record, Action $action): void {
                        if (blank($record->db_database)) {
                            Notification::make()
                                ->danger()
                                ->title('Tenant non pronto')
                                ->body('Configura e provisiona prima il database del tenant.')
                                ->send();

                            $action->halt();

                            return;
                        }

                        try {
                            app(TenantConnectionManager::class)->activate($record);
                            DB::purge(config('tenancy.database_connection'));
                            app(CurrentTenant::class)->activate($record);
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Connessione tenant non valida')
                                ->body($exception->getMessage())
                                ->send();

                            $action->halt();
                        }
                    })
                    ->successRedirectUrl(fn (): string => CustomerResource::getUrl('index')),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
