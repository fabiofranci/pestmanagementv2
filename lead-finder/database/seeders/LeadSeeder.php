<?php

namespace Database\Seeders;

use App\Models\Lead;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Lead::create([
            'company_name' => 'Impresa Pulizie Demo Bologna',
            'city' => 'Bologna',
            'province' => 'Bologna',
            'region' => 'Emilia-Romagna',
            'sector' => 'pulizie',
            'website' => 'https://demo-pulizie-bo.it',
            'email' => 'info@demo-pulizie-bo.it',
            'phone' => '+390512345678',
            'status' => 'new',
            'score' => 55,
            'source_name' => 'Demo import',
        ]);

        Lead::create([
            'company_name' => 'Multiservizi Demo Modena',
            'city' => 'Modena',
            'province' => 'Modena',
            'region' => 'Emilia-Romagna',
            'sector' => 'multiservizi',
            'website' => 'https://demo-multiservizi-mo.it',
            'email' => 'contatti@demo-multiservizi-mo.it',
            'phone' => '+390592345678',
            'status' => 'verified',
            'score' => 60,
            'source_name' => 'Demo import',
        ]);

        Lead::create([
            'company_name' => 'Pulizie Industriali Demo Reggio Emilia',
            'city' => 'Reggio Emilia',
            'province' => 'Reggio Emilia',
            'region' => 'Emilia-Romagna',
            'sector' => 'pulizie industriali',
            'website' => 'https://demo-industriali-re.it',
            'email' => 'info@demo-industriali-re.it',
            'phone' => '+390522345678',
            'status' => 'new',
            'score' => 70,
            'source_name' => 'Demo import',
        ]);

        Lead::create([
            'company_name' => 'Cooperativa Servizi Demo Parma',
            'city' => 'Parma',
            'province' => 'Parma',
            'region' => 'Emilia-Romagna',
            'sector' => 'cooperativa servizi',
            'website' => 'https://demo-servizi-pr.it',
            'email' => 'hello@demo-servizi-pr.it',
            'phone' => '+390521345678',
            'status' => 'verified',
            'score' => 65,
            'source_name' => 'Demo import',
        ]);
    }
}
