# Requisiti cliente da video — Pest Management V2

> Documento di lavoro interno. I fotogrammi servono solo come riferimento visivo per collegare le richieste del cliente alle scelte funzionali di Pest Management V2.

## Obiettivo

Il video mostra un gestionale desktop usato dal cliente come riferimento operativo. L'obiettivo non è copiarne l'interfaccia, ma capire quali funzioni il cliente considera essenziali e tradurle in Pest Management V2 con una struttura Laravel/Filament più pulita.

Nota di correzione: la parte relativa all'invio fattura non viene considerata requisito del gestionale del cliente, perché si riferisce alla fattura di StudioWeb19/Fabio verso il cliente. È quindi fuori perimetro rispetto a Pest Management V2.

---

## 1. Scheda Preventivo / Contratto come centro operativo

![Scheda contratto](assets/01_scheda_contratto.jpg)

### Cosa si vede

La schermata principale raccoglie molte informazioni in un unico punto:

- numero contratto/preventivo;
- cliente;
- luogo intervento;
- referente;
- periodo di validità;
- importo totale;
- tipo pagamento;
- servizi inclusi;
- interventi programmati;
- area legata a stampe, email, schede e rapportini.

### Interpretazione funzionale

Per il cliente il contratto non è solo un'anagrafica. È il centro da cui gestire servizi, scadenze, interventi, documenti e informazioni operative.

### Traduzione in Pest Management V2

In V2 il contratto dovrebbe diventare una schermata Filament operativa con:

- testata contratto;
- relation manager servizi;
- relation manager interventi programmati;
- relation manager piano fatturazione previsto;
- documenti/allegati;
- storico eventi;
- azioni rapide.

### Stato attuale

Parzialmente avviato:

- `contracts` esiste;
- `contract_services` è stato aggiunto;
- `scheduled_interventions` è stato aggiunto;
- `contract_billing_schedules` è stato aggiunto;
- `documents` polimorfica è stata aggiunta;
- `contract_events` è stato aggiunto.

### Gap ancora aperto

- vista riepilogativa contratto più leggibile;
- azioni operative: rinnovo, stampa, generazione interventi, generazione piano fatturazione;
- PDF e documenti reali;
- collegamento successivo con work order, visite e ispezioni.

---

## 2. Elenco contratti con stato, cliente, servizio e scadenze

![Lista contratti](assets/02_lista_contratti.jpg)

### Cosa si vede

Il gestionale mostra una lista contratti con molte colonne operative:

- data;
- numero contratto;
- codice cliente;
- ragione sociale;
- luogo intervento;
- descrizione servizio;
- importo;
- sconto;
- scadenza contratto;
- tipo/stato contratto.

Sono presenti filtri in alto e pulsanti in basso per aggiornare, stampare, cercare, creare nuovo record e chiudere.

### Interpretazione funzionale

Il cliente usa la lista contratti come cruscotto per:

- cercare rapidamente un contratto;
- verificare contratti in corso;
- vedere scadenze;
- controllare importi;
- capire quale servizio è collegato al cliente.

### Traduzione in Pest Management V2

La tabella `ContractResource` dovrebbe mostrare almeno:

- numero contratto;
- cliente;
- sede;
- stato;
- data inizio;
- data fine/scadenza;
- valore totale;
- prossima scadenza fatturazione;
- prossimo intervento programmato.

Filtri consigliati:

- stato contratto;
- cliente;
- sede;
- scadenza nei prossimi X giorni;
- servizio;
- contratti scaduti o in scadenza.

### Stato attuale

Da verificare/migliorare nel branch UI Filament.

### Gap ancora aperto

- filtri operativi;
- badge stato;
- ordinamenti utili;
- viste salvate o tab tipo “In corso”, “In scadenza”, “Scaduti”.

---

## 3. Fatturazione: cosa includere e cosa escludere

### Correzione importante

La schermata di invio fattura vista nel video non rappresenta una funzione del gestionale del cliente da copiare. Si riferisce alla fattura emessa da Fabio/StudioWeb19 verso il cliente.

Quindi non deve essere trattata come requisito di Pest Management V2.

### Cosa resta valido

Resta comunque valida una distinzione funzionale:

- Pest Management V2 può gestire un piano di fatturazione previsto del contratto;
- non deve diventare ora un gestionale fiscale completo;
- eventuali fatture reali o invii fiscali restano fuori perimetro o futura integrazione esterna.

La tabella corretta resta:

```text
contract_billing_schedules
```

Significato:

- scadenza prevista;
- descrizione rata o quota;
- importo;
- stato interno;
- eventuale riferimento futuro a fattura esterna.

---

# Matrice sintetica video → Pest Management V2

| Evidenza dal video | Richiesta funzionale | Entità V2 | Stato | Priorità |
|---|---|---|---|---|
| Scheda contratto completa | Il contratto deve essere il centro operativo | `contracts` + relation manager | avviato | alta |
| Servizi dentro il contratto | Gestire prestazioni incluse, frequenze e importi | `contract_services` | modello fatto, UI da rifinire | alta |
| Interventi programmati | Vedere il calendario/previsione degli interventi | `scheduled_interventions` | modello fatto, logica da sviluppare | alta |
| Scadenze economiche del contratto | Gestire piano di fatturazione previsto, non fattura fiscale | `contract_billing_schedules` | modello fatto, UI da rifinire | media |
| Pulsanti stampa/email/schede | Generare documenti e allegati | `documents` + PDF futuri | base fatta, stampe da fare | media |
| Rinnovo contratto | Duplicare/rinnovare contratti mantenendo dati utili | azione futura su `contracts` | non fatto | media |
| Rapportini/schede | Collegare contratto a lavoro eseguito | `work_orders`, `visits`, `inspections` | da fare dopo | alta, ma branch successivo |
| Schermata desktop densa | Avere tutto sott'occhio | vista riepilogativa Filament | da fare | alta |
| Invio fattura mostrato nel video | Fuori perimetro: riguarda fattura di Fabio verso il cliente | nessuna entità V2 | escluso | nessuna |

---

# Indicazioni per Codex

Codex non vede il video. Per questo va guidato sempre partendo da questa matrice e non da descrizioni generiche.

Regola operativa:

```text
Ogni modifica deve rispondere a una riga della matrice video → V2.
```

Prima di ogni branch chiedere a Codex:

1. quale richiesta del video sta coprendo;
2. quale entità V2 modifica;
3. cosa resta fuori;
4. cosa non deve essere copiato dal vecchio gestionale.

---

# Prossimo branch consigliato

```text
feature/contratti-v2-filament
```

Obiettivo:

- rendere il contratto leggibile e operativo in Filament;
- migliorare lista, form e view contratto;
- rendere usabili i relation manager;
- aggiungere riepilogo contratto stile “scheda”, senza copiare la UI desktop;
- mantenere fuori perimetro la fatturazione fiscale/invio fattura.
