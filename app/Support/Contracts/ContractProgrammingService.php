<?php

namespace App\Support\Contracts;

use App\Models\Contract;
use App\Models\ContractBillingSchedule;
use App\Models\ContractService;
use App\Models\ScheduledIntervention;
use Carbon\CarbonImmutable;

class ContractProgrammingService
{
    /**
     * @return array{created: int, skipped: int, skipped_records: array<int, array<string, mixed>>, event_id: int|null}
     */
    public function generateScheduledInterventions(Contract $contract, ?int $userId = null): array
    {
        $created = 0;
        $skipped = [];

        $contract->loadMissing('services');

        foreach ($contract->services->where('status', 'active') as $service) {
            $frequency = $this->normalizeFrequency($service->operational_frequency ?: $service->frequency);

            if (! $frequency) {
                $skipped[] = $this->skippedService($service, 'frequency_not_supported', [
                    'frequency' => $service->operational_frequency ?: $service->frequency,
                ]);

                continue;
            }

            $siteId = $service->customer_site_id ?: $contract->customer_site_id;

            if (! $siteId) {
                $skipped[] = $this->skippedService($service, 'missing_customer_site');

                continue;
            }

            $start = $this->date($service->starts_on) ?: $this->date($contract->start_date);
            $end = $this->date($service->ends_on) ?: $this->date($contract->end_date);
            $dates = $this->datesForFrequency($start, $end, $frequency);

            if ($dates === []) {
                $skipped[] = $this->skippedService($service, 'missing_or_invalid_dates', [
                    'starts_on' => $service->starts_on?->toDateString(),
                    'ends_on' => $service->ends_on?->toDateString(),
                    'contract_start_date' => $contract->start_date?->toDateString(),
                    'contract_end_date' => $contract->end_date?->toDateString(),
                ]);

                continue;
            }

            foreach ($dates as $date) {
                $plannedDate = $date->toDateString();

                $exists = ScheduledIntervention::query()
                    ->where('contract_id', $contract->getKey())
                    ->where('contract_service_id', $service->getKey())
                    ->where('customer_site_id', $siteId)
                    ->where('service_type_id', $service->service_type_id)
                    ->whereDate('planned_date', $plannedDate)
                    ->whereNull('planned_time')
                    ->exists();

                if ($exists) {
                    $skipped[] = $this->skippedService($service, 'duplicate', [
                        'planned_date' => $plannedDate,
                    ]);

                    continue;
                }

                ScheduledIntervention::query()->create([
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->getKey(),
                    'contract_service_id' => $service->getKey(),
                    'customer_site_id' => $siteId,
                    'service_type_id' => $service->service_type_id,
                    'planned_date' => $plannedDate,
                    'status' => 'planned',
                    'notes' => 'Generato automaticamente dal contratto.',
                ]);

                $created++;
            }
        }

        $event = $contract->events()->create([
            'tenant_id' => $contract->tenant_id,
            'event_type' => 'scheduled_interventions_generated',
            'title' => 'Generazione interventi programmati',
            'payload' => [
                'created' => $created,
                'skipped' => count($skipped),
                'skipped_records' => $skipped,
                'parameters' => [
                    'supported_frequencies' => ['monthly', 'quarterly', 'yearly', 'one_time'],
                ],
            ],
            'created_by_user_id' => $userId,
        ]);

        return [
            'created' => $created,
            'skipped' => count($skipped),
            'skipped_records' => $skipped,
            'event_id' => $event->getKey(),
        ];
    }

    /**
     * @return array{created: int, skipped: int, skipped_records: array<int, array<string, mixed>>, event_id: int|null}
     */
    public function generateBillingSchedule(Contract $contract, string $frequency, ?int $userId = null): array
    {
        $frequency = $this->normalizeFrequency($frequency) ?? 'one_time';
        $created = 0;
        $skipped = [];
        $totalAmount = (float) ($contract->total_value ?? 0);
        $currency = $contract->currency ?: 'EUR';
        $start = $this->date($contract->start_date);
        $end = $this->date($contract->end_date);
        $dates = $this->datesForFrequency($start, $end, $frequency);

        if ($totalAmount <= 0) {
            $skipped[] = [
                'reason' => 'missing_total_value',
                'total_value' => $contract->total_value,
            ];
        }

        if ($dates === []) {
            $skipped[] = [
                'reason' => 'missing_or_invalid_dates',
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'frequency' => $frequency,
            ];
        }

        if ($totalAmount > 0 && $dates !== []) {
            $installments = count($dates);
            $baseAmount = round($totalAmount / $installments, 2);
            $allocatedAmount = 0.0;

            foreach ($dates as $index => $date) {
                $amount = ($index + 1) === $installments
                    ? round($totalAmount - $allocatedAmount, 2)
                    : $baseAmount;
                $allocatedAmount += $amount;

                $description = $this->billingDescription($frequency, $index + 1, $installments);
                $dueDate = $date->toDateString();

                $exists = ContractBillingSchedule::query()
                    ->where('contract_id', $contract->getKey())
                    ->whereDate('due_date', $dueDate)
                    ->where('description', $description)
                    ->exists();

                if ($exists) {
                    $skipped[] = [
                        'reason' => 'duplicate',
                        'description' => $description,
                        'due_date' => $dueDate,
                    ];

                    continue;
                }

                ContractBillingSchedule::query()->create([
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->getKey(),
                    'description' => $description,
                    'due_date' => $dueDate,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'planned',
                ]);

                $created++;
            }
        }

        $event = $contract->events()->create([
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->getKey(),
            'event_type' => 'billing_schedule_generated',
            'title' => 'Generazione piano fatturazione',
            'payload' => [
                'created' => $created,
                'skipped' => count($skipped),
                'skipped_records' => $skipped,
                'parameters' => [
                    'frequency' => $frequency,
                    'total_value' => $contract->total_value,
                    'currency' => $currency,
                    'start_date' => $contract->start_date?->toDateString(),
                    'end_date' => $contract->end_date?->toDateString(),
                ],
            ],
            'created_by_user_id' => $userId,
        ]);

        return [
            'created' => $created,
            'skipped' => count($skipped),
            'skipped_records' => $skipped,
            'event_id' => $event->getKey(),
        ];
    }

    protected function normalizeFrequency(?string $frequency): ?string
    {
        $frequency = str($frequency ?? '')
            ->lower()
            ->replace([' ', '-'], '_')
            ->trim()
            ->toString();

        return match ($frequency) {
            'monthly', 'mensile', 'mese', 'mensili' => 'monthly',
            'quarterly', 'trimestrale', 'trimestre', 'trimestrali' => 'quarterly',
            'yearly', 'annual', 'annuale', 'anno', 'annuali' => 'yearly',
            'one_time', 'once', 'una_tantum', 'unica_soluzione', 'singolo' => 'one_time',
            default => null,
        };
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    protected function datesForFrequency(?CarbonImmutable $start, ?CarbonImmutable $end, string $frequency): array
    {
        if (! $start) {
            return [];
        }

        if ($end && $end->lt($start)) {
            return [];
        }

        if ($frequency !== 'one_time' && ! $end) {
            return [];
        }

        $dates = [];
        $date = $start;

        while (true) {
            if ($end && $date->gt($end)) {
                break;
            }

            $dates[] = $date;

            if ($frequency === 'one_time') {
                break;
            }

            $date = match ($frequency) {
                'monthly' => $date->addMonthNoOverflow(),
                'quarterly' => $date->addMonthsNoOverflow(3),
                'yearly' => $date->addYearNoOverflow(),
                default => null,
            };

            if (! $date) {
                break;
            }
        }

        return $dates;
    }

    protected function date(mixed $date): ?CarbonImmutable
    {
        if (blank($date)) {
            return null;
        }

        return CarbonImmutable::parse($date);
    }

    /**
     * @return array<string, mixed>
     */
    protected function skippedService(ContractService $service, string $reason, array $extra = []): array
    {
        return [
            'contract_service_id' => $service->getKey(),
            'reason' => $reason,
            ...$extra,
        ];
    }

    protected function billingDescription(string $frequency, int $index, int $total): string
    {
        return match ($frequency) {
            'monthly' => "Rata mensile {$index}/{$total}",
            'quarterly' => "Rata trimestrale {$index}/{$total}",
            'yearly' => "Rata annuale {$index}/{$total}",
            default => 'Unica soluzione',
        };
    }
}
