<?php

namespace App\Console\Commands;

use App\Jobs\ScoreLeadJob;
use App\Models\Lead;
use Illuminate\Console\Command;

class LeadsScore extends Command
{
    protected $signature = 'leads:score {--only-empty}';

    protected $description = 'Recalculate lead scores for all leads or only leads with an empty score.';

    public function handle(): int
    {
        $query = Lead::query();

        if ($this->option('only-empty')) {
            $query->where('score', 0);
        }

        $leads = $query->get();

        foreach ($leads as $lead) {
            ScoreLeadJob::dispatch($lead);
            $this->info('Queued score recalculation for lead #' . $lead->id);
        }

        $this->info('Dispatched ' . $leads->count() . ' leads for scoring.');

        return self::SUCCESS;
    }
}
