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
     * @var array{created: int, skipped: int, deleted: int, skipped_records: array<int, array<string, mixed>>, event_id: int|null, parameters: array<string, mixed>}
     */
    protected array $lastResult = [
        'created' => 0,
        'skipped' => 0,
        'deleted' => 0,
        'skipped_records' => [],
        'event_id' => null,
        'parameters' => [],
    ];

    /**
     * @return array{created: int, skipped: int, deleted: int, skipped_records: array<int, array<string, mixed>>, event_id: int|null, parameters: array<string, mixed>}
     */
    public function lastResult(): array
    {
        return $this->lastResult;
    }

    public function generateScheduledInterventions(Contract $contract, bool $replace = false, ?int $userId = null): int
    {
        $deleted = 0;
        $skipped = [];
        $today = CarbonImmutable::today()->toDateString();
        $pendingInterventions = [];

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
                    ->when($replace, fn ($query) => $query
                        ->where(fn ($query) => $query
                            ->where('status', '!=', 'planned')
                            ->orWhereDate('planned_date', '<', $today)))
                    ->exists();

                if ($exists) {
                    $skipped[] = $this->skippedService($service, 'duplicate', [
                        'planned_date' => $plannedDate,
                    ]);

                    continue;
                }

                $pendingInterventions[] = [
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->getKey(),
                    'contract_service_id' => $service->getKey(),
                    'customer_site_id' => $siteId,
                    'service_type_id' => $service->service_type_id,
                    'planned_date' => $plannedDate,
                    'status' => 'planned',
                    'notes' => 'Generato automaticamente dal contratto.',
                ];
            }
        }

        if ($replace && $pendingInterventions !== []) {
            $deleted = ScheduledIntervention::query()
                ->where('contract_id', $contract->getKey())
                ->where('status', 'planned')
                ->whereDate('planned_date', '>=', $today)
                ->delete();
        }

        foreach ($pendingInterventions as $intervention) {
            ScheduledIntervention::query()->create($intervention);
        }

        $created = count($pendingInterventions);

        $this->lastResult = $this->storeResultAndEvent($contract, 'scheduled_interventions_generated', 'Generazione interventi programmati', [
            'created' => $created,
            'deleted' => $deleted,
            'skipped_records' => $skipped,
            'parameters' => [
                'replace' => $replace,
                'deleted_scope' => 'planned_future',
                'supported_frequencies' => $this->supportedFrequencies(),
            ],
        ], $userId);

        return $created;
    }

    public function generateBillingSchedules(Contract $contract, bool $replace = false, ?int $userId = null): int
    {
        $contract->loadMissing('services');

        return $this->performBillingScheduleGeneration(
            contract: $contract,
            frequency: $this->billingFrequencyFor($contract),
            replace: $replace,
            userId: $userId,
        );
    }

    /**
     * Compatibility wrapper for older callers that still pass the frequency explicitly.
     *
     * @return array{created: int, skipped: int, deleted: int, skipped_records: array<int, array<string, mixed>>, event_id: int|null, parameters: array<string, mixed>}
     */
    public function generateBillingSchedule(Contract $contract, string $frequency, ?int $userId = null): array
    {
        $this->performBillingScheduleGeneration(
            contract: $contract,
            frequency: $frequency,
            replace: false,
            userId: $userId,
        );

        return $this->lastResult();
    }

    protected function performBillingScheduleGeneration(Contract $contract, ?string $frequency, bool $replace, ?int $userId): int
    {
        $frequency = $this->normalizeFrequency($frequency);
        $deleted = 0;
        $skipped = [];
        $pendingSchedules = [];
        $totalAmount = (float) ($contract->total_value ?? 0);
        $currency = $contract->currency ?: 'EUR';
        $start = $this->date($contract->start_date);
        $end = $this->date($contract->end_date);

        if (! $frequency) {
            $skipped[] = [
                'reason' => 'frequency_not_supported',
            ];
        }

        if ($totalAmount <= 0) {
            $skipped[] = [
                'reason' => 'missing_total_value',
                'total_value' => $contract->total_value,
            ];
        }

        $dates = $frequency ? $this->datesForFrequency($start, $end, $frequency) : [];

        if ($frequency && $dates === []) {
            $skipped[] = [
                'reason' => 'missing_or_invalid_dates',
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'frequency' => $frequency,
            ];
        }

        if ($totalAmount > 0 && $frequency && $dates !== []) {
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
                    ->when($replace, fn ($query) => $query->where('status', '!=', 'planned'))
                    ->exists();

                if ($exists) {
                    $skipped[] = [
                        'reason' => 'duplicate',
                        'description' => $description,
                        'due_date' => $dueDate,
                    ];

                    continue;
                }

                $pendingSchedules[] = [
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->getKey(),
                    'description' => $description,
                    'due_date' => $dueDate,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'planned',
                ];
            }
        }

        if ($replace && $pendingSchedules !== []) {
            $deleted = ContractBillingSchedule::query()
                ->where('contract_id', $contract->getKey())
                ->where('status', 'planned')
                ->delete();
        }

        foreach ($pendingSchedules as $schedule) {
            ContractBillingSchedule::query()->create($schedule);
        }

        $created = count($pendingSchedules);

        $this->lastResult = $this->storeResultAndEvent($contract, 'billing_schedule_generated', 'Generazione scadenze fatturazione', [
            'created' => $created,
            'deleted' => $deleted,
            'skipped_records' => $skipped,
            'parameters' => [
                'frequency' => $frequency,
                'replace' => $replace,
                'total_value' => $contract->total_value,
                'currency' => $currency,
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
            ],
        ], $userId);

        return $created;
    }

    protected function billingFrequencyFor(Contract $contract): ?string
    {
        $services = $contract->services->where('status', 'active');

        if ($services->count() === 1) {
            return $services->first()?->billing_frequency;
        }

        $frequencies = $services
            ->map(fn (ContractService $service): ?string => $service->billing_frequency)
            ->filter()
            ->unique()
            ->values();

        return $frequencies->count() === 1 ? $frequencies->first() : null;
    }

    protected function normalizeFrequency(?string $frequency): ?string
    {
        $frequency = str($frequency ?? '')
            ->lower()
            ->replace([' ', '-'], '_')
            ->trim()
            ->toString();

        return match ($frequency) {
            'weekly', 'week', 'settimana', 'settimanale', 'settimanali' => 'weekly',
            'fortnightly', 'biweekly', 'every_two_weeks', 'quindicinale', 'quindicinali' => 'fortnightly',
            'monthly', 'mensile', 'mese', 'mensili' => 'monthly',
            'bimonthly', 'bi_monthly', 'bimestrale', 'bimestrali' => 'bimonthly',
            'quarterly', 'trimestrale', 'trimestre', 'trimestrali' => 'quarterly',
            'four_monthly', 'quadrimestrale', 'quadrimestrali' => 'four_monthly',
            'six_monthly', 'semestrale', 'semestrali' => 'six_monthly',
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

            $nextDate = $this->nextDate($date, $frequency);

            if (! $nextDate || $nextDate->lte($date)) {
                break;
            }

            $date = $nextDate;
        }

        return $dates;
    }

    protected function nextDate(CarbonImmutable $date, string $frequency): ?CarbonImmutable
    {
        return match ($frequency) {
            'weekly' => $date->addWeek(),
            'fortnightly' => $date->addWeeks(2),
            'monthly' => $date->addMonthNoOverflow(),
            'bimonthly' => $date->addMonthsNoOverflow(2),
            'quarterly' => $date->addMonthsNoOverflow(3),
            'four_monthly' => $date->addMonthsNoOverflow(4),
            'six_monthly' => $date->addMonthsNoOverflow(6),
            'yearly' => $date->addYearNoOverflow(),
            default => null,
        };
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

    /**
     * @param  array{created: int, deleted: int, skipped_records: array<int, array<string, mixed>>, parameters: array<string, mixed>}  $result
     * @return array{created: int, skipped: int, deleted: int, skipped_records: array<int, array<string, mixed>>, event_id: int|null, parameters: array<string, mixed>}
     */
    protected function storeResultAndEvent(Contract $contract, string $eventType, string $title, array $result, ?int $userId): array
    {
        $skipped = count($result['skipped_records']);

        $event = $contract->events()->create([
            'tenant_id' => $contract->tenant_id,
            'event_type' => $eventType,
            'title' => $title,
            'payload' => [
                'created' => $result['created'],
                'deleted' => $result['deleted'],
                'skipped' => $skipped,
                'skipped_records' => $result['skipped_records'],
                'parameters' => $result['parameters'],
            ],
            'created_by_user_id' => $userId,
        ]);

        return [
            'created' => $result['created'],
            'skipped' => $skipped,
            'deleted' => $result['deleted'],
            'skipped_records' => $result['skipped_records'],
            'event_id' => $event->getKey(),
            'parameters' => $result['parameters'],
        ];
    }

    protected function billingDescription(string $frequency, int $index, int $total): string
    {
        return match ($frequency) {
            'weekly' => "Rata settimanale {$index}/{$total}",
            'fortnightly' => "Rata quindicinale {$index}/{$total}",
            'monthly' => "Rata mensile {$index}/{$total}",
            'bimonthly' => "Rata bimestrale {$index}/{$total}",
            'quarterly' => "Rata trimestrale {$index}/{$total}",
            'four_monthly' => "Rata quadrimestrale {$index}/{$total}",
            'six_monthly' => "Rata semestrale {$index}/{$total}",
            'yearly' => "Rata annuale {$index}/{$total}",
            default => 'Unica soluzione',
        };
    }

    /**
     * @return array<int, string>
     */
    protected function supportedFrequencies(): array
    {
        return [
            'weekly',
            'fortnightly',
            'monthly',
            'bimonthly',
            'quarterly',
            'four_monthly',
            'six_monthly',
            'yearly',
            'one_time',
        ];
    }
}
