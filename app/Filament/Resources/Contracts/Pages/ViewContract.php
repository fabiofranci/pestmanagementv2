<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Support\Contracts\ContractProgrammingService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->addManualEventAction(),
            $this->generateScheduledInterventionsAction(),
            $this->generateBillingScheduleAction(),
            $this->closeContractAction(),
            $this->reactivateContractAction(),
        ];
    }

    protected function addManualEventAction(): Action
    {
        return Action::make('addManualEvent')
            ->label('Aggiungi evento')
            ->schema([
                Select::make('event_type')
                    ->label('Tipo evento')
                    ->options([
                        'manual' => 'Manuale',
                        'note' => 'Nota',
                        'status_changed' => 'Cambio stato',
                    ])
                    ->default('manual')
                    ->native(false)
                    ->required(),
                TextInput::make('title')
                    ->label('Titolo')
                    ->required()
                    ->maxLength(255),
                KeyValue::make('payload')
                    ->label('Dati')
                    ->columnSpanFull(),
            ])
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()))
            ->action(function (array $data): void {
                $this->getRecord()->events()->create([
                    'tenant_id' => $this->getRecord()->tenant_id,
                    'event_type' => $data['event_type'],
                    'title' => $data['title'],
                    'payload' => $data['payload'] ?? null,
                    'created_by_user_id' => auth()->id(),
                ]);

                Notification::make()
                    ->success()
                    ->title('Evento aggiunto')
                    ->send();
            });
    }

    protected function generateScheduledInterventionsAction(): Action
    {
        return Action::make('generateScheduledInterventions')
            ->label('Genera interventi programmati')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Genera interventi programmati')
            ->modalDescription('Crea solo interventi programmati dal contratto. Non crea work order, visite o ispezioni.')
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()))
            ->action(function (): void {
                $result = app(ContractProgrammingService::class)
                    ->generateScheduledInterventions($this->getRecord(), auth()->id());

                $this->record = $this->getRecord()->refresh();

                $notification = Notification::make()
                    ->title('Generazione interventi completata')
                    ->body("Interventi creati: {$result['created']}. Record saltati: {$result['skipped']}.");

                ($result['created'] > 0 ? $notification->success() : $notification->warning())->send();
            });
    }

    protected function generateBillingScheduleAction(): Action
    {
        return Action::make('generateBillingSchedule')
            ->label('Genera piano fatturazione')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Genera piano fatturazione')
            ->modalDescription('Crea solo scadenze previste del contratto. Non crea fatture fiscali o invii elettronici.')
            ->schema([
                Select::make('frequency')
                    ->label('Modalita')
                    ->options([
                        'one_time' => 'Unica soluzione',
                        'monthly' => 'Mensile',
                        'quarterly' => 'Trimestrale',
                        'yearly' => 'Annuale',
                    ])
                    ->default(fn (): string => $this->getRecord()->service()->value('billing_frequency') ?: 'one_time')
                    ->native(false)
                    ->required(),
            ])
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()))
            ->action(function (array $data): void {
                $result = app(ContractProgrammingService::class)
                    ->generateBillingSchedule($this->getRecord(), $data['frequency'], auth()->id());

                $this->record = $this->getRecord()->refresh();

                $notification = Notification::make()
                    ->title('Generazione piano fatturazione completata')
                    ->body("Scadenze create: {$result['created']}. Record saltati: {$result['skipped']}.");

                ($result['created'] > 0 ? $notification->success() : $notification->warning())->send();
            });
    }

    protected function closeContractAction(): Action
    {
        return Action::make('closeContract')
            ->label('Chiudi contratto')
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()) && $this->getRecord()->status !== 'closed')
            ->action(function (): void {
                $record = $this->getRecord();
                $record->update(['status' => 'closed']);
                $this->record = $record->refresh();

                $record->events()->create([
                    'tenant_id' => $record->tenant_id,
                    'event_type' => 'closed',
                    'title' => 'Contratto chiuso',
                    'created_by_user_id' => auth()->id(),
                ]);

                Notification::make()
                    ->success()
                    ->title('Contratto chiuso')
                    ->send();
            });
    }

    protected function reactivateContractAction(): Action
    {
        return Action::make('reactivateContract')
            ->label('Riattiva contratto')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()) && $this->getRecord()->status === 'closed')
            ->action(function (): void {
                $record = $this->getRecord();
                $record->update(['status' => 'active']);
                $this->record = $record->refresh();

                $record->events()->create([
                    'tenant_id' => $record->tenant_id,
                    'event_type' => 'reactivated',
                    'title' => 'Contratto riattivato',
                    'created_by_user_id' => auth()->id(),
                ]);

                Notification::make()
                    ->success()
                    ->title('Contratto riattivato')
                    ->send();
            });
    }
}
