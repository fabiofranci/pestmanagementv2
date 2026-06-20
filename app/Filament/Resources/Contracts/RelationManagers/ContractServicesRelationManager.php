<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\Area;
use App\Models\CustomerSite;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContractServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return static::usesSingleServiceModeFor($ownerRecord)
            ? 'Servizio principale'
            : 'Servizi contrattuali';
    }

    protected function canCreate(): bool
    {
        return parent::canCreate() && $this->canCreateAnotherService();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('service_type_id')
                    ->label('Tipo di servizio')
                    ->relationship('serviceType', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('customer_site_id')
                    ->label('Sede cliente')
                    ->options(fn (): array => CustomerSite::query()
                        ->where('customer_id', $this->getOwnerRecord()->customer_id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('area_id', null);
                    })
                    ->native(false),
                Select::make('area_id')
                    ->label('Area')
                    ->options(fn (Get $get): array => Area::query()
                        ->when(
                            $get('customer_site_id'),
                            fn ($query, $siteId) => $query->where('customer_site_id', $siteId),
                            fn ($query) => $query->whereRaw('1 = 0'),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->required()
                    ->columnSpanFull(),
                Select::make('operational_frequency')
                    ->label('Cadenza operativa')
                    ->options([
                        'monthly' => 'Mensile',
                        'quarterly' => 'Trimestrale',
                        'yearly' => 'Annuale',
                        'one_time' => 'Una tantum',
                    ])
                    ->native(false)
                    ->helperText('Usata per generare gli interventi programmati dal contratto.'),
                Select::make('billing_frequency')
                    ->label('Cadenza fatturazione')
                    ->options([
                        'monthly' => 'Mensile',
                        'quarterly' => 'Trimestrale',
                        'yearly' => 'Annuale',
                        'one_time' => 'Unica soluzione',
                    ])
                    ->native(false)
                    ->helperText('Usata come riferimento per il piano fatturazione previsto.'),
                TextInput::make('quantity')
                    ->label('Quantita')
                    ->numeric(),
                TextInput::make('unit_price')
                    ->label('Prezzo unitario')
                    ->numeric(),
                TextInput::make('total_price')
                    ->label('Totale')
                    ->numeric(),
                TextInput::make('currency')
                    ->label('Valuta')
                    ->default('EUR')
                    ->maxLength(3)
                    ->required(),
                DatePicker::make('starts_on')
                    ->label('Decorrenza'),
                DatePicker::make('ends_on')
                    ->label('Fine validita'),
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'active' => 'Attivo',
                        'suspended' => 'Sospeso',
                        'closed' => 'Chiuso',
                    ])
                    ->default('active')
                    ->native(false)
                    ->required(),
                Textarea::make('notes')
                    ->label('Note')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): string => $this->usesSingleServiceMode() ? 'Servizio principale' : 'Servizi contrattuali')
            ->description(fn (): string => $this->usesSingleServiceMode()
                ? 'Questo tenant consente un solo servizio per contratto.'
                : 'Questo tenant consente piu servizi nello stesso contratto.')
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('serviceType.name')
                    ->label('Tipo servizio')
                    ->searchable(),
                TextColumn::make('site.name')
                    ->label('Sede')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('area.name')
                    ->label('Area')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('operational_frequency')
                    ->label('Cadenza operativa')
                    ->state(fn ($record): ?string => $record->operational_frequency ?: $record->frequency)
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'monthly' => 'Mensile',
                        'quarterly' => 'Trimestrale',
                        'yearly' => 'Annuale',
                        'one_time' => 'Una tantum',
                        default => $state ?: '-',
                    })
                    ->placeholder('-'),
                TextColumn::make('billing_frequency')
                    ->label('Cadenza fatturazione')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'monthly' => 'Mensile',
                        'quarterly' => 'Trimestrale',
                        'yearly' => 'Annuale',
                        'one_time' => 'Unica soluzione',
                        default => $state ?: '-',
                    })
                    ->placeholder('-'),
                TextColumn::make('total_price')
                    ->label('Totale')
                    ->money(fn ($record): string => $record->currency ?: 'EUR')
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Valuta')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Attivo',
                        'suspended' => 'Sospeso',
                        'closed' => 'Chiuso',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'closed' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'active' => 'Attivo',
                        'suspended' => 'Sospeso',
                        'closed' => 'Chiuso',
                    ])
                    ->native(false),
            ])
            ->defaultSort('id')
            ->headerActions([
                CreateAction::make()
                    ->label(fn (): string => $this->usesSingleServiceMode() ? 'Aggiungi servizio principale' : 'Aggiungi servizio')
                    ->hidden(fn (): bool => ! $this->canCreate())
                    ->before(function (CreateAction $action): void {
                        if ($this->canCreateAnotherService()) {
                            return;
                        }

                        Notification::make()
                            ->warning()
                            ->title('Servizio gia presente')
                            ->body('Questo tenant consente un solo servizio per contratto.')
                            ->send();

                        $action->halt();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function canCreateAnotherService(): bool
    {
        return ! $this->usesSingleServiceMode()
            || ! $this->getOwnerRecord()->services()->exists();
    }

    protected function usesSingleServiceMode(): bool
    {
        return static::usesSingleServiceModeFor($this->getOwnerRecord());
    }

    protected static function usesSingleServiceModeFor(Model $ownerRecord): bool
    {
        $currentTenant = app(CurrentTenant::class)->get();

        if ($currentTenant && (int) $currentTenant->getKey() === (int) $ownerRecord->tenant_id) {
            return $currentTenant->usesSingleContractServiceMode();
        }

        return Tenant::query()
            ->whereKey($ownerRecord->tenant_id)
            ->first()
            ?->usesSingleContractServiceMode() ?? false;
    }
}
