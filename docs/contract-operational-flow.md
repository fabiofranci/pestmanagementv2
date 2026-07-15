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

Nei tenant con `contract_service_mode = single_service`, il form contratto mostra anche la sezione `Servizio principale`.
In creazione e modifica del contratto e quindi possibile compilare il servizio nello stesso passaggio della testata contratto.

La sezione propone:

- sede servizio dalla sede selezionata nel contratto;
- `starts_on` e `ends_on` dalle date inizio/fine contratto;
- `total_price` dal valore totale contratto, se disponibile;
- ricalcolo di `total_price` da `quantity * unit_price`, se entrambi sono valorizzati.

Il totale resta modificabile manualmente. Al salvataggio, se esiste gia un servizio per il contratto, viene aggiornato; se non esiste, viene creato. In modalita `single_service` non viene mai creato un secondo servizio.

Nei tenant con `contract_service_mode = multiple_services`, il form contratto resta concentrato sulla testata e i servizi si gestiscono dal relation manager dopo il salvataggio.

La programmazione operativa vive sul servizio:

- `contract_services.operational_schedule_mode`;
- `contract_services.operational_frequency`;
- `contract_services.scheduled_months`;
- `contract_services.interventions_per_year`;
- fallback legacy: `contract_services.frequency`.

Modalita supportate:

- `recurring`: genera interventi ricorrenti usando `operational_frequency`;
- `custom_months`: genera un intervento per ogni mese indicato in `scheduled_months`;
- `manual`: non genera interventi automaticamente.

Esempio AZ: per 5 disinfestazioni annue in Febbraio, Marzo, Maggio, Giugno e Luglio, impostare:

```json
[2, 3, 5, 6, 7]
```

La generazione crea record in `scheduled_interventions` senza duplicare date gia presenti. La rigenerazione elimina solo interventi futuri con stato `planned`; non elimina interventi `completed` o `cancelled`.

## Elementi fatturabili

Gli elementi fatturabili previsti dal contratto vivono in:

```text
contract_billable_items
```

Sono indipendenti da `contract_service_mode`: funzionano sia per tenant con un solo servizio per contratto sia per tenant con piu servizi.

Un servizio contrattuale descrive la prestazione operativa, per esempio derattizzazione o monitoraggio. Un elemento fatturabile descrive invece un articolo economico collegato al contratto, per esempio contenitori, trappole, lampade, cartelli, paletti, esche o materiali.

Nel contratto la scheda `Elementi fatturabili` permette di inserire:

- articolo;
- quantita;
- prezzo unitario;
- sconto;
- totale;
- note;
- stato.

Il prezzo viene proposto dal catalogo articoli e dalle eventuali condizioni cliente. Il prezzo unitario sul contratto e il prezzo finale unitario applicato; lo sconto resta informativo/manuale e non viene applicato una seconda volta nel totale.

Il riepilogo contratto mostra il numero degli elementi fatturabili attivi e il totale attivo. Se non sono presenti elementi, la vista resta valida e mostra valori pari a zero.

Questi elementi non generano ancora fatture XML, non modificano il piano fatturazione e non sono ancora collegati agli interventi extra.

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
