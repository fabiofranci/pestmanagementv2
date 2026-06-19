# Contratti V2 - Programmazione

Data aggiornamento: 2026-06-19

Questo documento riepiloga il lavoro del branch `feature/contratti-v2-programmazione`. Il branch aggiunge prime azioni operative sicure sul contratto, senza introdurre work order, visite, ispezioni, PDF o fatturazione fiscale.

## Generazione Interventi Programmati

La pagina riepilogativa contratto include l'azione `Genera interventi programmati`.

L'azione:

- parte dai `contract_services` attivi del contratto;
- crea record in `scheduled_interventions`;
- usa la sede del servizio contrattuale, oppure la sede del contratto come fallback;
- usa il `service_type_id` del servizio contrattuale;
- usa `starts_on` / `ends_on` del servizio, oppure `start_date` / `end_date` del contratto;
- supporta frequenze semplici:
  - `monthly`;
  - `quarterly`;
  - `yearly`;
  - `one_time`;
- evita duplicati su stesso contratto, servizio, sede, tipo servizio e data prevista;
- registra un evento `scheduled_interventions_generated`.

L'azione non crea:

- `work_orders`;
- `visits`;
- `inspections`;
- compatibilita app mobile.

## Generazione Piano Fatturazione

La pagina riepilogativa contratto include l'azione `Genera piano fatturazione`.

L'azione:

- crea record in `contract_billing_schedules`;
- usa `total_value`, `currency`, `start_date` ed `end_date` del contratto;
- supporta modalita:
  - unica soluzione;
  - mensile;
  - trimestrale;
  - annuale;
- divide l'importo totale sulle scadenze generate;
- evita duplicati su stesso contratto, descrizione e data scadenza;
- registra un evento `billing_schedule_generated`.

L'azione non crea:

- fatture fiscali;
- fatture elettroniche;
- invii contabili;
- documenti PDF.

## Eventi

Ogni generazione registra un evento contratto con payload JSON.

Per gli interventi:

- `event_type`: `scheduled_interventions_generated`;
- payload con:
  - numero record creati;
  - numero record saltati;
  - dettagli dei record saltati;
  - frequenze supportate.

Per il piano fatturazione:

- `event_type`: `billing_schedule_generated`;
- payload con:
  - numero record creati;
  - numero record saltati;
  - dettagli dei record saltati;
  - frequenza usata;
  - valore totale;
  - valuta;
  - date contratto.

## Limiti Attuali

- Le ricorrenze sono volutamente semplici.
- Non esiste ancora un motore calendario avanzato.
- Le frequenze non riconosciute vengono saltate e tracciate nel payload evento.
- Se mancano date o importi necessari, la generazione non forza valori arbitrari.
- Il piano fatturazione e solo previsionale, non fiscale.
- Gli interventi programmati restano previsioni operative, non incarichi di lavoro.

## Rimandato

- Rinnovo automatico contratto.
- Generazione work order da intervento programmato.
- Esecuzione visite.
- Ispezioni su punti di monitoraggio.
- Materiali utilizzati.
- PDF contratto, schede e rapportini.
- Integrazione fatturazione elettronica.
- Import legacy.
- Compatibilita app mobile.
