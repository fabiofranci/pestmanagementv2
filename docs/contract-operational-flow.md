# Flusso operativo contratto

Data aggiornamento: 2026-07-15

Il contratto e la testata operativa da cui si gestiscono servizio, programmazione interventi, piano fatturazione e storico eventi.

## Creazione

In creazione il campo `Numero contratto` viene precompilato con il prossimo progressivo numerico del tenant, ma resta modificabile per inserire numeri storici o importati.

Il contratto contiene:

- cliente e sede;
- date di validita;
- stato;
- condizioni pagamento;
- cadenza fatturazione;
- numero rate;
- valore totale;
- rinnovo tacito e aumento rinnovo.

## Servizio contrattuale

I servizi restano in `contract_services`.

Per AZ il tenant puo lavorare in modalita un solo servizio per contratto. In quel caso la UI presenta la relazione come `Servizio principale`.

La cadenza operativa vive sul servizio:

- `contract_services.operational_frequency`;
- fallback legacy: `contract_services.frequency`.

Questa cadenza genera `scheduled_interventions`.

## Piano fatturazione

La cadenza fatturazione vive sul contratto:

- `contracts.billing_frequency`.

La generazione di `contract_billing_schedules` usa questa cadenza e divide `contracts.total_value` sulle scadenze calcolate tra `start_date` e `end_date`.

Non vengono create scadenze se:

- `billing_frequency` manca;
- `total_value` e nullo o zero;
- date e cadenza non producono scadenze valide.

`contract_services.billing_frequency` resta solo come campo legacy di compatibilita.

## Rinnovo

Da `ViewContract` l'azione `Rinnova contratto` duplica il contratto corrente in un nuovo contratto attivo.

Il vecchio contratto viene marcato `concluded`; il nuovo contratto mantiene il riferimento al precedente tramite `renewed_from_contract_id`.

Se il rinnovo tacito prevede un aumento percentuale, l'aumento viene applicato al valore totale del contratto nuovo e ai prezzi del servizio copiato.

## Disdetta

Da `ViewContract` l'azione `Disdici contratto` imposta il contratto a `cancelled` e registra l'evento in storico.

## Eventi

Le azioni automatiche scrivono in `contract_events`:

- `billing_schedule_generated`;
- `scheduled_interventions_generated`;
- `renewed`;
- `created_from_renewal`;
- `cancelled`.
