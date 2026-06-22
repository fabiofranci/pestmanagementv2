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
use Throwable;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->addManualEventAction(),
            $this->generateInterventionsAction(),
            $this->regenerateInterventionsAction(),
            $this->generateBillingSchedulesAction(),
            $this->regenerateBillingSchedulesAction(),
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

    protected function generateInterventionsAction(): Action
    {
        return Action::make('generateInterventions')
            ->label('Genera interventi')
            ->color('info')
            ->modalHeading('Genera interventi programmati')
            ->modalDescription('Crea solo interventi programmati dal contratto. Non crea work order, visite o ispezioni.')
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()))
            ->action(function (): void {
                $this->runScheduledInterventionsGeneration(false);
            });
    }

    protected function regenerateInterventionsAction(): Action
    {
        return Action::make('regenerateInterventions')
            ->label('Rigenera interventi')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Rigenera interventi programmati')
            ->modalDescription('Elimina gli interventi planned futuri del contratto e li ricrea dalle cadenze operative. Non tocca work order, visite o ispezioni.')
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()))
            ->action(function (): void {
                $this->runScheduledInterventionsGeneration(true);
            });
    }

    protected function generateBillingSchedulesAction(): Action
    {
        return Action::make('generateBillingSchedules')
            ->label('Genera scadenze fatturazione')
            ->color('info')
            ->modalHeading('Genera scadenze fatturazione')
            ->modalDescription('Crea solo scadenze previste del contratto. Non crea fatture fiscali o invii elettronici.')
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()))
            ->action(function (): void {
                $this->runBillingSchedulesGeneration(false);
            });
    }

    protected function regenerateBillingSchedulesAction(): Action
    {
        return Action::make('regenerateBillingSchedules')
            ->label('Rigenera scadenze fatturazione')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Rigenera scadenze fatturazione')
            ->modalDescription('Elimina le scadenze planned non fatturate del contratto e le ricrea dalla cadenza di fatturazione.')
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()))
            ->action(function (): void {
                $this->runBillingSchedulesGeneration(true);
            });
    }

    protected function runScheduledInterventionsGeneration(bool $replace): void
    {
        try {
            $service = app(ContractProgrammingService::class);
            $created = $service->generateScheduledInterventions($this->getRecord(), $replace, auth()->id());
            $result = $service->lastResult();

            $this->record = $this->getRecord()->refresh();

            $notification = Notification::make()
                ->title($replace ? 'Rigenerazione interventi completata' : 'Generazione interventi completata')
                ->body($this->generationBody('Interventi creati', $created, 'Interventi eliminati', $result));

            ($created > 0 ? $notification->success() : $notification->warning())->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Generazione interventi fallita')
                ->body($exception->getMessage())
                ->send();
        }
    }

    protected function runBillingSchedulesGeneration(bool $replace): void
    {
        try {
            $service = app(ContractProgrammingService::class);
            $created = $service->generateBillingSchedules($this->getRecord(), $replace, auth()->id());
            $result = $service->lastResult();

            $this->record = $this->getRecord()->refresh();

            $notification = Notification::make()
                ->title($replace ? 'Rigenerazione scadenze completata' : 'Generazione scadenze completata')
                ->body($this->generationBody('Scadenze create', $created, 'Scadenze eliminate', $result));

            ($created > 0 ? $notification->success() : $notification->warning())->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Generazione scadenze fallita')
                ->body($exception->getMessage())
                ->send();
        }
    }

    /**
     * @param  array{skipped: int, deleted: int, skipped_records: array<int, array<string, mixed>>}  $result
     */
    protected function generationBody(string $createdLabel, int $created, string $deletedLabel, array $result): string
    {
        $body = "{$createdLabel}: {$created}. {$deletedLabel}: {$result['deleted']}. Record saltati: {$result['skipped']}.";
        $reasons = collect($result['skipped_records'])
            ->pluck('reason')
            ->filter()
            ->unique()
            ->implode(', ');

        return $reasons ? "{$body} Motivi: {$reasons}." : $body;
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
