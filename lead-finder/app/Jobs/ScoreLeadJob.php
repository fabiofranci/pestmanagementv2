<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Leads\LeadScoringService;
use Illuminate\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScoreLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Lead $lead)
    {
    }

    public function handle(LeadScoringService $scoring): void
    {
        $scoring->updateScore($this->lead);
    }
}
