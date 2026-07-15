<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\Concerns\RecalculatesContractTotals;
use App\Models\Contract;
use App\Support\Contracts\ContractNumberService;
use App\Support\Contracts\ContractProgrammingService;
use App\Support\Tenancy\CurrentTenant;
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
    use RecalculatesContractTotals;

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
            $this->recalculateContractTotalAction(),
            $this->renewContractAction(),
            $this->cancelContractAction(),
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
            ->map(fn (string $reason): string => $this->generationReasonLabel($reason))
            ->unique()
            ->implode(', ');

        return $reasons ? "{$body} Motivi: {$reasons}." : $body;
    }

    protected function generationReasonLabel(string $reason): string
    {
        return match ($reason) {
            'missing_contract_billing_frequency' => 'cadenza fatturazione mancante sul contratto',
            'missing_total_value' => 'valore totale mancante o zero',
            'missing_or_invalid_dates' => 'date mancanti o non valide',
            'manual_schedule' => 'programmazione manuale',
            'missing_scheduled_months' => 'mesi personalizzati mancanti',
            'frequency_not_supported' => 'cadenza non supportata',
            'duplicate' => 'record gia presente',
            'missing_customer_site' => 'sede cliente mancante',
            default => $reason,
        };
    }

    protected function renewContractAction(): Action
    {
        return Action::make('renewContract')
            ->label('Rinnova contratto')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Rinnova contratto')
            ->modalDescription('Duplica il contratto, assegna il prossimo numero progressivo, copia il servizio e conclude il contratto corrente.')
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()) && $this->getRecord()->status === 'active')
            ->action(function (): void {
                $record = $this->getRecord();
                $newContract = $this->renewContract($record);
                $this->record = $record->refresh();

                Notification::make()
                    ->success()
                    ->title('Contratto rinnovato')
                    ->body("Creato contratto {$newContract->contract_number}.")
                    ->send();
            });
    }

    protected function cancelContractAction(): Action
    {
        return Action::make('cancelContract')
            ->label('Disdici contratto')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Disdici contratto')
            ->modalDescription('Imposta il contratto come annullato e registra un evento di disdetta.')
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()) && ! in_array($this->getRecord()->status, ['cancelled', 'concluded'], true))
            ->action(function (): void {
                $record = $this->getRecord();
                $record->update(['status' => 'cancelled']);
                $this->record = $record->refresh();

                $record->events()->create([
                    'tenant_id' => $record->tenant_id,
                    'event_type' => 'cancelled',
                    'title' => 'Contratto disdetto',
                    'created_by_user_id' => auth()->id(),
                ]);

                Notification::make()
                    ->success()
                    ->title('Contratto disdetto')
                    ->send();
            });
    }

    protected function renewContract(Contract $record): Contract
    {
        return $record->getConnection()->transaction(function () use ($record): Contract {
            $newContract = $record->replicate([
                'contract_number',
                'status',
                'renewed_from_contract_id',
                'created_at',
                'updated_at',
            ]);

            $increasePercentage = $this->renewalIncreasePercentage($record);

            $newContract->forceFill([
                'contract_number' => app(ContractNumberService::class)->nextForTenant(app(CurrentTenant::class)->get()),
                'status' => 'active',
                'renewed_from_contract_id' => $record->getKey(),
                'total_value' => $this->applyIncrease($record->total_value, $increasePercentage),
            ]);
            $newContract->save();

            foreach ($record->services()->get() as $service) {
                $newService = $service->replicate(['created_at', 'updated_at']);
                $newService->forceFill([
                    'contract_id' => $newContract->getKey(),
                    'unit_price' => $this->applyIncrease($service->unit_price, $increasePercentage),
                    'total_price' => $this->applyIncrease($service->total_price, $increasePercentage),
                ]);
                $newService->save();
            }

            $record->update(['status' => 'concluded']);

            $record->events()->create([
                'tenant_id' => $record->tenant_id,
                'event_type' => 'renewed',
                'title' => 'Contratto rinnovato',
                'payload' => [
                    'new_contract_id' => $newContract->getKey(),
                    'new_contract_number' => $newContract->contract_number,
                    'increase_percentage' => $increasePercentage,
                ],
                'created_by_user_id' => auth()->id(),
            ]);

            $newContract->events()->create([
                'tenant_id' => $newContract->tenant_id,
                'event_type' => 'created_from_renewal',
                'title' => 'Contratto creato da rinnovo',
                'payload' => [
                    'renewed_from_contract_id' => $record->getKey(),
                    'renewed_from_contract_number' => $record->contract_number,
                    'increase_percentage' => $increasePercentage,
                ],
                'created_by_user_id' => auth()->id(),
            ]);

            return $newContract;
        });
    }

    protected function renewalIncreasePercentage(Contract $record): float
    {
        $percentage = (float) ($record->renewal_price_increase_percentage ?? 0);

        return $record->tacit_renewal && $percentage > 0 ? $percentage : 0.0;
    }

    protected function applyIncrease(mixed $amount, float $percentage): mixed
    {
        if ($amount === null || $percentage <= 0) {
            return $amount;
        }

        return round((float) $amount * (1 + ($percentage / 100)), 2);
    }
}
