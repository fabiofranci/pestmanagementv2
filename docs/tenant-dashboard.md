# Dashboard Tenant

Data aggiornamento: 2026-06-22

La dashboard Filament del tenant usa widget operativi al posto dei widget standard Filament.

Sono stati rimossi dalla dashboard:

- widget account/benvenuto standard;
- widget documentazione Filament / GitHub.

## Widget Operativi

La dashboard mostra tre widget tabellari:

- `Contratti in scadenza`;
- `Scadenze fatturazione`;
- `Interventi programmati`.

I widget usano la connessione tenant gia attivata dal middleware e non contengono logica specifica per AZ.

## Contratti In Scadenza

Model:

```text
App\Models\Contract
```

Mostra:

- contratti con `status = active`;
- `end_date` valorizzata;
- scadenza entro i prossimi 90 giorni;
- massimo 10 record.

Colonne:

- numero contratto;
- cliente;
- sede;
- data fine;
- rinnovo tacito;
- valore totale.

## Scadenze Fatturazione

Model:

```text
App\Models\ContractBillingSchedule
```

Mostra:

- scadenze con `due_date` entro i prossimi 30 giorni;
- massimo 10 record.

Colonne:

- contratto;
- cliente;
- data scadenza;
- importo;
- stato.

Queste scadenze restano piano previsto, non fattura fiscale.

## Interventi Programmati

Model:

```text
App\Models\ScheduledIntervention
```

Mostra:

- interventi con `planned_date` entro i prossimi 30 giorni;
- massimo 10 record.

Colonne:

- data intervento;
- ora;
- contratto;
- cliente;
- sede;
- servizio;
- stato.

## Tabelle Mancanti O Vuote

Se una tabella tenant non esiste ancora o non contiene record, il widget mostra uno stato vuoto pulito.

Questo evita errori su tenant appena creati o non ancora migrati.

## Visibilita

I widget sono collegati al modulo `contracts`.

Se il modulo contratti e disabilitato per il tenant, i widget contrattuali non vengono mostrati.

La dashboard resta compatibile con tenant admin e superuser. Senza tenant corrente, i widget non interrogano tabelle operative tenant.
