<?php

namespace App\Services\Leads;

use App\Models\Lead;

class LeadScoringService
{
    public function calculate(Lead $lead): int
    {
        $score = 0;

        $value = strtolower($lead->company_name ?? '');

        if (str_contains($value, 'pulizie')) {
            $score += 20;
        }

        if (str_contains($value, 'multiservizi')) {
            $score += 15;
        }

        if (str_contains($value, 'sanificazione')) {
            $score += 10;
        }

        if (str_contains($value, 'condomini')) {
            $score += 15;
        }

        if (str_contains($value, 'pulizie industriali')) {
            $score += 15;
        }

        if (! empty($lead->website)) {
            $score += 10;
        }

        if (! empty($lead->email)) {
            $score += 10;
        }

        if (! empty($lead->phone)) {
            $score += 10;
        }

        if (! empty($lead->mobile) || ! empty($lead->whatsapp)) {
            $score += 10;
        }

        if (! empty($lead->province) || ! empty($lead->city)) {
            $score += 5;
        }

        return min(100, $score);
    }

    public function updateScore(Lead $lead): Lead
    {
        $lead->score = $this->calculate($lead);
        $lead->save();

        return $lead;
    }
}
