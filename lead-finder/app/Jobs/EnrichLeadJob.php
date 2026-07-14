<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\LeadContact;
use App\Services\Leads\LeadExtractorService;
use App\Services\Leads\LeadScoringService;
use Illuminate\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class EnrichLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Lead $lead)
    {
    }

    public function handle(LeadExtractorService $extractor, LeadScoringService $scoring): void
    {
        $result = $extractor->extractFromWebsite($this->lead->website);

        if (! empty($result['title']) && empty($this->lead->company_name)) {
            $this->lead->company_name = $result['title'];
        }

        if (empty($this->lead->email) && ! empty($result['emails'])) {
            $this->lead->email = $result['emails'][0];
        }

        if (empty($this->lead->phone) && ! empty($result['phones'])) {
            $this->lead->phone = $result['phones'][0];
        }

        foreach (array_unique(array_merge($result['emails'], $result['phones'], $result['contact_links'] ?? [])) as $value) {
            if (empty($value)) {
                continue;
            }

            LeadContact::updateOrCreate([
                'lead_id' => $this->lead->id,
                'type' => Str::contains($value, '@') ? 'email' : 'other',
                'value' => $value,
            ], [
                'label' => null,
                'source_url' => $this->lead->website,
                'is_primary' => false,
            ]);
        }

        $this->lead->last_seen_at = now();
        $this->lead->save();

        $scoring->updateScore($this->lead);
    }
}
