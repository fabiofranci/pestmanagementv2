# Seed dati AZ Disinfestazioni

Il comando `az:seed-demo-data` configura il tenant AZ Disinfestazioni e carica i dati iniziali ANGIPLAST usati per validare il flusso contratti.

## Prerequisiti

Il tenant centrale deve esistere con slug:

```bash
azdisinfestazioni
```

Il database tenant deve essere configurato nel record `tenants.db_database` e deve avere le migration tenant aggiornate.

## Esecuzione locale

```bash
php artisan tenants:migrate --tenant=azdisinfestazioni
php artisan az:seed-demo-data
```

## Esecuzione produzione

```bash
php artisan tenants:migrate --tenant=azdisinfestazioni --force
php artisan az:seed-demo-data
```

Il comando non usa id tenant hardcoded: cerca sempre il tenant dallo slug `azdisinfestazioni` e attiva la connessione tenant tramite `TenantConnectionManager`.

## Dati caricati

Il tenant AZ viene configurato con:

- `contract_service_mode = single_service`
- moduli: `dashboard`, `contracts`, `customer_sites`, `customers`, `service_types`

Il modulo `customer_groups` viene ignorato finche non esiste tra i moduli supportati dal codice.

Nel database tenant vengono creati o aggiornati:

- 20 tipi di servizio AZ
- cliente `ANGIPLAST SRL` con codice storico AZ `1858`
- sede cliente `OSTUNI` con `site_code = 1858`
- contratti ANGIPLAST `2569`, `2570`, `2571`, `2572`, `2573`
- un solo servizio contrattuale per ciascun contratto

I contratti sono caricati come attivi, con rinnovo tacito, aumento rinnovo 4%, preavviso 30 giorni e valuta EUR.

## Idempotenza

Il comando puo essere eseguito piu volte. Le chiavi di aggiornamento sono:

- tipo servizio: `tenant_id + name`
- cliente: `tenant_id + legacy_customer_code`, se disponibile
- sede: `tenant_id + customer_id + site_code`, se disponibile
- contratto: `tenant_id + contract_number`
- servizio contrattuale: primo record associato al contratto

La seconda esecuzione aggiorna i record esistenti senza duplicare service types, cliente, sede, contratti o servizi contrattuali.

## Cadenza fatturazione

`billing_frequency` e `billing_installments_count` dei contratti restano `null`: la cadenza reale AZ non e ancora confermata. Anche il campo legacy `contract_services.billing_frequency`, se presente, viene mantenuto a `null` sui servizi caricati dal seed.
