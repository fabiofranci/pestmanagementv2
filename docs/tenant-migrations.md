# Migration Tenant

Data aggiornamento: 2026-06-20

Pest Management V2 usa due livelli di database:

- database centrale: utenti, tenant, ruoli, configurazione;
- database tenant: dati operativi come clienti, sedi, contratti, aree e punti di monitoraggio.

## Migration Database Centrale

Le migration centrali si eseguono con il comando Laravel standard:

```bash
php artisan migrate --force
```

Questo comando aggiorna il database centrale configurato in `.env`.

Non va usato per aggiornare direttamente i database tenant, perché la connessione `tenant` non ha un database fisso: il database viene scelto dinamicamente dal record in tabella `tenants`.

## Migration Database Tenant

Le migration operative tenant si trovano in:

```text
database/migrations/tenant
```

Per aggiornarle si usa:

```bash
php artisan tenants:migrate
```

Il comando:

- seleziona i tenant con `status = active`;
- legge `tenants.db_database`;
- attiva la connessione tramite `App\Support\Tenancy\TenantConnectionManager`;
- esegue le migration su `--database=tenant`;
- stampa tenant, database, esito ed eventuali errori.

## Migrare Un Solo Tenant

Per migrare un solo tenant usare lo slug:

```bash
php artisan tenants:migrate --tenant=azdisinfestazioni
```

Il comando considera solo tenant attivi. Se lo slug non corrisponde a un tenant attivo, l'esecuzione termina con errore.

## Fresh

Per ricreare da zero le tabelle tenant:

```bash
php artisan tenants:migrate --fresh
```

`--fresh` e consentito solo in ambiente `local` o `testing`.

In produzione e bloccato intenzionalmente, perché elimina e ricrea le tabelle del database tenant.

## Errori

Di default il comando si ferma al primo errore.

Per continuare sugli altri tenant:

```bash
php artisan tenants:migrate --continue-on-error
```

Se almeno un tenant fallisce, il comando termina comunque con exit code di errore.

## Uso Nel Deploy

Sequenza consigliata:

```bash
php artisan migrate --force
php artisan tenants:migrate
php artisan optimize:clear
```

Se il deploy deve aggiornare un solo tenant:

```bash
php artisan migrate --force
php artisan tenants:migrate --tenant=azdisinfestazioni
php artisan optimize:clear
```

Non usare `php artisan migrate --database=tenant --path=database/migrations/tenant` direttamente in deploy: senza un tenant attivo la connessione `tenant` non sa quale `db_database` usare.
