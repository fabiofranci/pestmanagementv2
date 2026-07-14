<?php

namespace App\Console\Commands;

use App\Jobs\EnrichLeadJob;
use App\Models\Lead;
use Illuminate\Console\Command;

class LeadsEnrich extends Command
{
    protected $signature = 'leads:enrich {--limit=20}';

    protected $description = 'Enrich existing leads by extracting contact data from their websites.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $leads = Lead::whereNotNull('website')
            ->where(function ($query) {
                $query->whereNull('email')
                    ->orWhereNull('phone');
            })
            ->limit($limit)
            ->get();

        foreach ($leads as $lead) {
            EnrichLeadJob::dispatch($lead);
            $this->info('Queued enrichment for lead #' . $lead->id);
        }

        $this->info('Dispatched ' . $leads->count() . ' leads for enrichment.');

        return self::SUCCESS;
    }
}
