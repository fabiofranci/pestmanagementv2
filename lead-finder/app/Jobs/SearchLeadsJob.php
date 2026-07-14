<?php

namespace App\Jobs;

use App\Models\LeadFetchRun;
use App\Services\Leads\LeadSearchQueryBuilderService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable as FoundationDispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SearchLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(public LeadFetchRun $run)
    {
    }

    public function handle(): void
    {
        $service = new LeadSearchQueryBuilderService();
        $queries = $service->buildQueries(
            $this->run->region,
            $this->run->province,
            $this->run->sector,
            20
        );

        foreach ($queries as $query) {
            // Placeholder for future provider integration.
        }

        $this->run->update([
            'status' => 'completed',
            'found_count' => count($queries),
            'finished_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->run->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
