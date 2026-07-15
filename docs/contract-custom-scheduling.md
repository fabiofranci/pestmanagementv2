# Programmazione interventi personalizzata

Data aggiornamento: 2026-07-15

Questo documento descrive come Pest Management V2 programma gli interventi di un servizio contrattuale quando la cadenza non e sempre una ricorrenza semplice.

## Campi

I campi sono su `contract_services`:

- `operational_schedule_mode`: modalita di programmazione;
- `operational_frequency`: frequenza ricorrente, usata solo in modalita `recurring`;
- `scheduled_months`: array JSON di mesi, usato solo in modalita `custom_months`;
- `interventions_per_year`: numero interventi annui indicativo/manuale.

Valori di `operational_schedule_mode`:

- `recurring`;
- `custom_months`;
- `manual`.

## recurring

Usa la frequenza operativa esistente:

- `weekly`;
- `fortnightly`;
- `monthly`;
- `bimonthly`;
- `quarterly`;
- `four_monthly`;
- `six_monthly`;
- `yearly`;
- `one_time`.

La generazione usa `contract_services.starts_on` e `contract_services.ends_on` se presenti; altrimenti usa `contracts.start_date` e `contracts.end_date`.

## custom_months

Usa `scheduled_months`, un array numerico di mesi da 1 a 12.

Esempio AZ:

```json
[2, 3, 5, 6, 7]
```

Questo genera interventi nei mesi:

- Febbraio;
- Marzo;
- Maggio;
- Giugno;
- Luglio.

Regole:

- genera un intervento per ogni mese selezionato compreso nel periodo effettivo del servizio/contratto;
- usa come giorno il giorno di `contract_services.starts_on`, se presente;
- se `starts_on` del servizio manca, usa il giorno di `contracts.start_date`;
- se il giorno non esiste nel mese, usa l'ultimo giorno del mese;
- mantiene `customer_site_id`, `service_type_id` e `contract_service_id`;
- non duplica interventi gia presenti.

## manual

Non genera interventi automaticamente.

La UI mostra:

```text
Gli interventi saranno inseriti manualmente.
```

La generazione registra un warning informativo `manual_schedule` e crea zero interventi.

## Rigenerazione

Con `replace = true`, la generazione elimina solo interventi futuri del contratto con:

```text
status = planned
```

Non elimina interventi:

- `completed`;
- `cancelled`;
- planned ma gia passati.
