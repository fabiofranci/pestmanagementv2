# Contratti V2 - Filament

Data aggiornamento: 2026-06-19

Questo documento riepiloga il lavoro del branch `feature/contratti-v2-filament`. Il branch migliora l'esperienza Filament del modulo Contratti V2 senza introdurre nuove grandi entita di dominio.

## Implementato

### ContractResource

- Tabella contratti migliorata con:
  - numero contratto;
  - cliente;
  - sede;
  - stato con badge;
  - date inizio/fine;
  - valore totale;
  - condizioni pagamento;
  - rinnovo.
- Filtri aggiunti:
  - stato;
  - cliente;
  - scadenza per data fine contratto.
- Azione `View` aggiunta alla lista.
- Form organizzato in sezioni:
  - Dati principali;
  - Cliente e sede;
  - Date e rinnovo;
  - Valori economici;
  - Note.
- Creazione rapida da popup:
  - nuovo cliente dalla select `Cliente`;
  - nuova sede dalla select `Sede cliente`, vincolata al cliente selezionato.

### Vista riepilogativa contratto

- Aggiunta pagina `ViewContract`.
- La vista mostra:
  - dati contratto;
  - cliente e sede;
  - conteggio servizi;
  - prossimo intervento programmato;
  - prossima scadenza del piano fatturazione;
  - ultimi eventi.
- I Relation Manager restano disponibili nella pagina riepilogativa.

### Azioni leggere

- Aggiunta azione per evento manuale.
- Aggiunta azione per chiudere il contratto.
- Aggiunta azione per riattivare un contratto chiuso.
- Non sono state introdotte azioni automatiche di rinnovo, generazione interventi, generazione piano fatturazione o stampa.

### Relation Manager

Migliorati i Relation Manager gia presenti:

- `ContractServicesRelationManager`
  - stato con select e badge;
  - filtro stato;
  - importi formattati;
  - sede e area filtrate.
- `ScheduledInterventionsRelationManager`
  - stato con select e badge;
  - filtro stato;
  - ordinamento per data prevista.
- `ContractBillingSchedulesRelationManager`
  - stato con select e badge;
  - filtro stato;
  - importi formattati;
  - ordinamento per scadenza.
- `DocumentsRelationManager`
  - tipi documento normalizzati a livello UI;
  - badge tipo documento;
  - ordinamento per creazione recente.
- `ContractEventsRelationManager`
  - tipi evento normalizzati a livello UI;
  - badge tipo evento;
  - ordinamento per evento recente.

### Test

- Aggiunta copertura per:
  - accesso a `ContractResource` da tenant admin;
  - rendering della pagina riepilogativa contratto;
  - rendering di dati collegati nella vista;
  - azioni leggere su contratto: evento manuale, chiusura, riattivazione;
  - tenant/customer scoping gia presente e invariato;
  - blocco dell'accesso cliente a contratti di altri customer.

## Non implementato in questo branch

- `work_orders`.
- `visits`.
- `inspections`.
- Generazione PDF.
- Rinnovo automatico.
- Generazione automatica interventi.
- Generazione automatica fatture.
- Import legacy.
- Compatibilita app mobile.
- Nuove migration o modifiche strutturali al database centrale.

## Note architetturali

- La tenancy esistente non e stata modificata.
- I dati operativi restano nel database tenant.
- La tabella `documents` resta polimorfica.
- Non sono stati introdotti `scheduled_invoices` o `contract_documents`.
- Le strutture di `areas` e `monitoring_points` non sono state modificate.

## Prossimi branch suggeriti

- `feature/contratti-v2-workflow`
  - rinnovo manuale assistito;
  - generazione interventi programmati da servizi.
- `feature/contratti-v2-stampe`
  - template documento contratto;
  - stampe schede e rapportini.
- `feature/contratti-v2-work-orders`
  - introduzione controllata della catena `scheduled_intervention -> work_order -> visit -> inspection`.
