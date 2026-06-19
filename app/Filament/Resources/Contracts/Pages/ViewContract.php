<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
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
            $this->closeContractAction(),
            $this->reactivateContractAction(),
            $this->duplicateContractAction(),
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

    protected function duplicateContractAction(): Action
    {
        return Action::make('duplicateContract')
            ->label('Duplica contratto')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()))
            ->action(function (): void {
                $record = $this->getRecord();

                $copy = $record->replicate([
                    'contract_number',
                    'status',
                ]);
                $copy->contract_number = $record->contract_number.'-copy-'.now()->format('YmdHis');
                $copy->status = 'draft';
                $copy->save();

                $copy->events()->create([
                    'tenant_id' => $copy->tenant_id,
                    'event_type' => 'duplicated',
                    'title' => 'Contratto duplicato da '.$record->contract_number,
                    'created_by_user_id' => auth()->id(),
                ]);

                Notification::make()
                    ->success()
                    ->title('Contratto duplicato')
                    ->send();

                $this->redirect(ContractResource::getUrl('edit', ['record' => $copy]));
            });
    }
}
