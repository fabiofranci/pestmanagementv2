<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Tenancy\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateTenants extends Command
{
    protected $signature = 'tenants:migrate
        {--tenant= : Slug del tenant da migrare}
        {--fresh : Ricrea le tabelle tenant prima di eseguire le migration, solo in local/testing}
        {--continue-on-error : Continua con gli altri tenant se una migration fallisce}';

    protected $description = 'Esegue le migration operative sui database tenant.';

    public function handle(TenantConnectionManager $tenantConnectionManager): int
    {
        if ($this->option('fresh') && ! app()->environment(['local', 'testing'])) {
            $this->error('L opzione --fresh e consentita solo negli ambienti local/testing.');

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->where('status', 'active')
            ->when(
                $this->option('tenant'),
                fn ($query, string $slug) => $query->where('slug', $slug),
            )
            ->orderBy('name')
            ->get();

        if ($tenants->isEmpty()) {
            $message = $this->option('tenant')
                ? "Nessun tenant attivo trovato con slug [{$this->option('tenant')}]."
                : 'Nessun tenant attivo trovato.';

            $this->error($message);

            return self::FAILURE;
        }

        $successful = 0;
        $failed = 0;
        $continueOnError = (bool) $this->option('continue-on-error');
        $artisanCommand = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->line("Tenant: {$tenant->name} ({$tenant->slug})");
            $this->line('Database: '.($tenant->db_database ?: 'non configurato'));

            try {
                $tenantConnectionManager->activate($tenant);

                Artisan::call($artisanCommand, [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);

                $output = trim(Artisan::output());

                if ($output !== '') {
                    $this->line($output);
                }

                $successful++;
                $this->info('Esito: completato');
            } catch (Throwable $exception) {
                $failed++;
                $this->error('Esito: errore');
                $this->error('Errore: '.$exception->getMessage());

                if (! $continueOnError) {
                    $this->newLine();
                    $this->error('Esecuzione interrotta. Usa --continue-on-error per proseguire sugli altri tenant.');

                    return self::FAILURE;
                }
            } finally {
                DB::purge('tenant');
            }
        }

        $this->newLine();
        $summary = "Migration tenant completate. Successi: {$successful}. Errori: {$failed}.";

        if ($failed > 0) {
            $this->warn($summary);

            return self::FAILURE;
        }

        $this->info($summary);

        return self::SUCCESS;
    }
}
