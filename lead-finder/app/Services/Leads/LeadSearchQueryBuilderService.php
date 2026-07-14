<?php

namespace App\Services\Leads;

class LeadSearchQueryBuilderService
{
    public function buildQueries(?string $region, ?string $province, ?string $sector, int $limit = 20): array
    {
        $province = trim($province ?? '');
        $sector = trim($sector ?? '');
        $region = trim($region ?? '');

        $baseQueries = [
            'impresa di pulizie %s email telefono',
            'azienda multiservizi %s contatti',
            'pulizie industriali %s contatti',
            'cooperativa pulizie %s email',
            'pulizie condomini %s contatti',
        ];

        $queries = [];

        foreach ($baseQueries as $template) {
            if ($province !== '') {
                $queries[] = sprintf($template, $province);
            }

            if ($region !== '' && $province === '') {
                $queries[] = sprintf($template, $region);
            }
        }

        return array_slice(array_unique($queries), 0, $limit);
    }
}
