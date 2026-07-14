<?php

namespace App\Console\Commands;

use App\Models\LeadFetchRun;
use App\Services\Leads\LeadSearchQueryBuilderService;
use Illuminate\Console\Command;
use Throwable;

class LeadsSearch extends Command
{
    protected $signature = 'leads:search {--region=} {--province=} {--sector=} {--limit=20}';

    protected $description = 'Generate lead search queries and store the fetch run status.';

    public function handle(): int
    {
        $region = $this->option('region');
        $province = $this->option('province');
        $sector = $this->option('sector');
        $limit = (int) $this->option('limit');

        $run = LeadFetchRun::create([
            'query' => null,
            'region' => $region,
            'province' => $province,
            'sector' => $sector,
            'status' => 'pending',
            'found_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'error_count' => 0,
        ]);

        try {
            $service = new LeadSearchQueryBuilderService();
            $queries = $service->buildQueries($region, $province, $sector, $limit);

            foreach ($queries as $query) {
                $this->info($query);
            }

            $run->update([
                'status' => 'completed',
                'found_count' => count($queries),
                'finished_at' => now(),
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            $this->error('Search failed: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
