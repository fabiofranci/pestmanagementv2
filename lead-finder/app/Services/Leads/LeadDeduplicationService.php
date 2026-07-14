<?php

namespace App\Services\Leads;

use App\Models\Lead;
use Illuminate\Support\Str;

class LeadDeduplicationService
{
    public function normalizeWebsite(?string $website): ?string
    {
        if (empty($website)) {
            return null;
        }

        return Str::of($website)
            ->lower()
            ->replaceMatches('/https?:\/\//', '')
            ->replaceMatches('/www\./', '')
            ->before('/')
            ->trim()
            ->__toString();
    }

    public function normalizeEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }

        return Str::of($email)
            ->lower()
            ->trim()
            ->__toString();
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        return preg_replace('/[^0-9+]/', '', $phone);
    }

    public function findExistingLead(array $data): ?Lead
    {
        $website = $this->normalizeWebsite($data['website'] ?? null);
        $email = $this->normalizeEmail($data['email'] ?? null);
        $companyName = trim($data['company_name'] ?? '');
        $city = trim($data['city'] ?? '');

        if ($website) {
            $lead = Lead::whereRaw('LOWER(REPLACE(REPLACE(website, "https://", ""), "http://", "")) = ?', [$website])
                ->first();

            if ($lead) {
                return $lead;
            }
        }

        if ($email) {
            $lead = Lead::whereRaw('LOWER(email) = ?', [$email])->first();

            if ($lead) {
                return $lead;
            }
        }

        if ($companyName && $city) {
            return Lead::where('company_name', $companyName)
                ->where('city', $city)
                ->first();
        }

        return null;
    }

    public function createOrUpdate(array $data): Lead
    {
        $existing = $this->findExistingLead($data);

        if ($existing) {
            $existing->fill($data);
            $existing->save();

            return $existing;
        }

        return Lead::create($data);
    }
}
