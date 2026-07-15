<?php

namespace App\Filament\Resources\Contracts\Pages\Concerns;

use App\Models\Contract;
use App\Models\ContractService;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Support\Contracts\ContractTotalsService;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;

trait ManagesPrimaryContractService
{
    protected array $primaryContractServiceData = [];

    protected function extractPrimaryContractServiceData(array $data): array
    {
        $this->primaryContractServiceData = $data['primary_service'] ?? [];

        unset($data['primary_service']);

        return $data;
    }

    protected function primaryContractServiceFormData(Contract $contract): array
    {
        $service = $contract->service()->first();

        if ($service) {
            return [
                'service_type_id' => $service->service_type_id,
                'customer_site_id' => $service->customer_site_id,
                'area_id' => $service->area_id,
                'description' => $service->description,
                'operational_schedule_mode' => $service->operational_schedule_mode ?: 'recurring',
                'operational_frequency' => $service->operational_frequency,
                'scheduled_months' => $this->normalizeScheduledMonths($service->scheduled_months),
                'interventions_per_year' => $service->interventions_per_year,
                'quantity' => $service->quantity,
                'unit_price' => $service->unit_price,
                'total_price' => $service->total_price,
                'currency' => $service->currency ?: ($contract->currency ?: 'EUR'),
                'starts_on' => $this->dateForForm($service->starts_on),
                'ends_on' => $this->dateForForm($service->ends_on),
                'status' => $service->status ?: 'active',
                'notes' => $service->notes,
            ];
        }

        return [
            'customer_site_id' => $contract->customer_site_id,
            'operational_schedule_mode' => 'recurring',
            'total_price' => $contract->total_value,
            'currency' => $contract->currency ?: 'EUR',
            'starts_on' => $this->dateForForm($contract->start_date),
            'ends_on' => $this->dateForForm($contract->end_date),
            'status' => 'active',
        ];
    }

    protected function savePrimaryContractService(): void
    {
        /** @var Contract $contract */
        $contract = $this->getRecord();

        if (! $this->usesSingleContractServiceMode($contract)) {
            return;
        }

        $data = $this->normalizedPrimaryContractServiceData($contract, $this->primaryContractServiceData);

        if ($data === null) {
            return;
        }

        $service = ContractService::query()
            ->where('tenant_id', $contract->tenant_id)
            ->where('contract_id', $contract->getKey())
            ->first();

        $service ??= new ContractService([
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->getKey(),
        ]);

        $service->fill($data);
        $service->save();

        app(ContractTotalsService::class)->updateContractTotal($contract);
        $this->record = $contract->refresh();
    }

    protected function usesSingleContractServiceMode(Contract $contract): bool
    {
        $currentTenant = app(CurrentTenant::class)->get();

        if ($currentTenant && (int) $currentTenant->getKey() === (int) $contract->tenant_id) {
            return $currentTenant->usesSingleContractServiceMode();
        }

        return Tenant::query()
            ->whereKey($contract->tenant_id)
            ->first()
            ?->usesSingleContractServiceMode() ?? false;
    }

    protected function normalizedPrimaryContractServiceData(Contract $contract, array $data): ?array
    {
        $serviceTypeId = $data['service_type_id'] ?? null;

        if (blank($serviceTypeId)) {
            return null;
        }

        $mode = $data['operational_schedule_mode'] ?? 'recurring';
        $quantity = $data['quantity'] ?? null;
        $unitPrice = $data['unit_price'] ?? null;
        $totalPrice = $data['total_price'] ?? null;

        if (blank($totalPrice) && filled($quantity) && filled($unitPrice)) {
            $totalPrice = round(((float) $quantity) * ((float) $unitPrice), 2);
        }

        if (blank($totalPrice) && filled($contract->total_value)) {
            $totalPrice = $contract->total_value;
        }

        return [
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->getKey(),
            'service_type_id' => $serviceTypeId,
            'customer_site_id' => ($data['customer_site_id'] ?? null) ?: $contract->customer_site_id,
            'area_id' => $data['area_id'] ?? null,
            'description' => ($data['description'] ?? null)
                ?: ServiceType::query()->whereKey($serviceTypeId)->value('name')
                ?: 'Servizio principale',
            'operational_schedule_mode' => $mode,
            'operational_frequency' => $mode === 'recurring' ? ($data['operational_frequency'] ?? null) : null,
            'scheduled_months' => $mode === 'custom_months' ? $this->normalizeScheduledMonths($data['scheduled_months'] ?? null) : null,
            'interventions_per_year' => $mode === 'custom_months' ? ($data['interventions_per_year'] ?? null) : null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'currency' => ($data['currency'] ?? null) ?: ($contract->currency ?: 'EUR'),
            'starts_on' => ($data['starts_on'] ?? null) ?: $this->dateForForm($contract->start_date),
            'ends_on' => ($data['ends_on'] ?? null) ?: $this->dateForForm($contract->end_date),
            'status' => ($data['status'] ?? null) ?: 'active',
            'notes' => $data['notes'] ?? null,
        ];
    }

    protected function dateForForm(mixed $date): ?string
    {
        if ($date instanceof Carbon) {
            return $date->toDateString();
        }

        return filled($date) ? (string) $date : null;
    }

    /**
     * @return array<int, int>
     */
    protected function normalizeScheduledMonths(mixed $months): array
    {
        if (is_string($months)) {
            $decoded = json_decode($months, true);
            $months = is_array($decoded) ? $decoded : explode(',', $months);
        }

        if (! is_array($months)) {
            return [];
        }

        return collect($months)
            ->map(fn (mixed $month): int => (int) $month)
            ->filter(fn (int $month): bool => $month >= 1 && $month <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
