# Sede predefinita da cliente

Data aggiornamento: 2026-07-15

Alcuni clienti hanno una sola sede operativa e i dati della sede coincidono con l'anagrafica cliente.

Per evitare inserimenti duplicati, il cliente puo essere marcato con:

```text
customers.default_site_same_as_customer = true
```

Nel form cliente il campo e mostrato come `Sede coincidente con il cliente`.

## Creazione automatica

Quando il flag e attivo e il cliente viene salvato, il sistema puo creare una sede collegata usando i dati anagrafici del cliente.

La sede viene creata con:

- `name`: nome cliente, oppure `Sede principale` se mancante;
- indirizzo, citta, CAP, provincia e paese copiati dal cliente;
- referente: nome cliente;
- telefono referente: telefono, cellulare o telefono secondario del cliente;
- email referente: email cliente;
- `status = active`;
- `auto_created_from_customer = true`;
- note: `Sede creata automaticamente dai dati cliente.`

## Aggiornamento

Se il flag resta attivo:

- se il cliente non ha sedi, viene creata la sede automatica;
- se esiste gia una sede con `auto_created_from_customer = true`, viene aggiornata dai dati cliente;
- le sedi manuali non vengono sovrascritte.

Se il flag viene disattivato, la sede esistente non viene eliminata. Il sistema smette solo di mantenerla sincronizzata con i dati cliente.

## Regola operativa

La sede automatica serve come scorciatoia per i casi semplici.

Quando il cliente ha piu sedi o sedi con dati diversi dall'anagrafica, usare sedi manuali dedicate.
