# Contratti V2 - interventi programmati e scadenze fatturazione

Data aggiornamento: 2026-06-22

Questo branch rende la scheda contratto il punto operativo per gestire interventi programmati e piano di fatturazione previsto, senza introdurre work order, visite, ispezioni, PDF o fatturazione elettronica.

## Cadenza operativa

La cadenza operativa vive sul servizio contrattuale (`contract_services.operational_frequency`) e viene usata per generare gli interventi programmati.

Frequenze supportate:

- `weekly` / `settimanale`;
- `fortnightly` / `quindicinale`;
- `monthly` / `mensile`;
- `bimonthly` / `bimestrale`;
- `quarterly` / `trimestrale`;
- `four_monthly` / `quadrimestrale`;
- `six_monthly` / `semestrale`;
- `yearly` / `annuale`;
- `one_time` / `una_tantum`.

La generazione usa `starts_on` e `ends_on` del servizio se presenti; in alternativa usa `start_date` e `end_date` del contratto.

## Cadenza fatturazione

La cadenza amministrativa vive sul servizio contrattuale (`contract_services.billing_frequency`) e viene usata per generare `contract_billing_schedules`.

Se il contratto ha un solo servizio attivo, viene usata la sua cadenza di fatturazione. Se il contratto ha più servizi attivi, la generazione automatica usa la cadenza solo quando tutte le cadenze valorizzate coincidono.

Il piano di fatturazione divide `contracts.total_value` sulle scadenze calcolate tra `start_date` e `end_date`; l'ultima rata assorbe eventuali arrotondamenti.

## Generazione interventi

La pagina `ViewContract` espone:

- `Genera interventi`;
- `Rigenera interventi`.

La generazione crea solo record in `scheduled_interventions`; non crea work order, visite o ispezioni.

La rigenerazione elimina prima solo gli interventi futuri con stato `planned` del contratto, poi ricrea le righe. Gli interventi completati, annullati o già storicizzati non vengono eliminati.

Ogni esecuzione registra un evento `scheduled_interventions_generated` in `contract_events`.

## Generazione scadenze

La pagina `ViewContract` espone:

- `Genera scadenze fatturazione`;
- `Rigenera scadenze fatturazione`.

La generazione crea solo record in `contract_billing_schedules`; non crea fatture fiscali, XML o invii elettronici.

La rigenerazione elimina prima solo le scadenze con stato `planned`, poi ricrea il piano. Le scadenze già fatturate o annullate non vengono eliminate.

Se `total_value` è nullo o zero, non vengono create scadenze e l'azione mostra un warning.

Ogni esecuzione registra un evento `billing_schedule_generated` in `contract_events`.

## Stati previsti

Interventi programmati:

- `planned`;
- `confirmed`;
- `completed`;
- `cancelled`.

Scadenze fatturazione:

- `planned`;
- `invoiced`;
- `cancelled`.

Per compatibilità, la tabella può ancora visualizzare stati legacy già presenti come `issued` o `paid`, ma le nuove azioni operative usano `invoiced`.

## Limiti attuali

Restano fuori da questo branch:

- work order;
- visite;
- ispezioni;
- rapportini;
- generazione PDF;
- XML/fatturazione elettronica;
- integrazione Aruba;
- app mobile compatibility;
- import legacy massivo.
