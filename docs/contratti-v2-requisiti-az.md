# Contratti V2 - Requisiti AZ

Data aggiornamento: 2026-07-15

Questo documento recepisce i requisiti chiariti da AZ Disinfestazioni sulla gestione contrattuale. Le modifiche restano nel perimetro Contratti V2 e non introducono PDF, XML, Aruba, visite, ispezioni o app mobile.

## Numero Contratto Storico

Il campo `contract_number` resta una stringa libera per supportare numerazioni storiche/importabili, ad esempio:

- `2025/1`;
- `2026/1`;
- `1072`;
- `9999`.

Il numero contratto e univoco per tenant tramite vincolo `tenant_id + contract_number`. Questo permette a tenant diversi di usare lo stesso numero, ma impedisce duplicati dentro la stessa organizzazione.

La UI Filament non applica formati rigidi: slash e vecchie numerazioni restano validi. Questo consente agli operatori di ricreare progressivamente i vecchi contratti mantenendo il numero originario.

## Un Contratto, Un Servizio

Per AZ ogni contratto rappresenta un solo servizio contrattuale. Questo comportamento non e hardcoded nel prodotto: viene attivato tramite configurazione del tenant.

Configurazione tenant:

```text
contract_service_mode = single_service
```

Con questa modalita:

- `contracts` resta la testata contratto;
- `contract_services` resta la tabella dei servizi del contratto;
- Filament impedisce l'aggiunta del secondo servizio;
- la relazione viene presentata in UI come `Servizio principale`.

Conseguenza operativa:

- lo stesso cantiere/sede puo avere piu contratti;
- ogni contratto rappresenta un servizio diverso;
- ogni servizio puo avere cadenze proprie;
- non si mescolano servizi diversi nello stesso contratto.

## Modello Generale Pest Management V2

Il default del prodotto resta multi-servizio:

```text
contract_service_mode = multiple_services
```

In questa modalita:

- un contratto puo avere piu `contract_services`;
- la UI usa la label `Servizi contrattuali`;
- la relazione `Contract::services()` resta il modello principale;
- `Contract::service()` resta solo una scorciatoia tecnica al primo servizio, utile nei riepiloghi o nelle configurazioni single-service.

Questo permette di usare Pest Management V2 sia per tenant come AZ, che vogliono un contratto per ogni servizio, sia per tenant che preferiscono contratti con piu servizi inclusi.

## Cadenze Separate

Sono state distinte le cadenze operative e amministrative:

- `contract_services.operational_frequency`: cadenza operativa usata per programmare gli interventi;
- `contracts.billing_frequency`: cadenza amministrativa usata come fonte principale per il piano fatturazione previsto.

Il vecchio campo `contract_services.frequency` resta per compatibilita con dati gia inseriti. La generazione interventi usa `operational_frequency` e, se assente, ricade su `frequency`.

`contract_services.billing_frequency` resta solo per compatibilita temporanea, ma non guida piu la generazione delle scadenze.

## Rinnovo Tacito

Il contratto ora puo memorizzare:

- `tacit_renewal`: flag rinnovo tacito;
- `renewal_price_increase_percentage`: percentuale aumento rinnovo, default `4.00`;
- `renewal_notice_days`: giorni di preavviso, default `30`.

L'azione `Rinnova contratto` duplica il contratto, assegna un nuovo numero progressivo, copia il servizio, conclude il vecchio contratto e crea gli eventi di storico. Se il rinnovo tacito prevede un aumento percentuale, l'aumento viene applicato al valore del nuovo contratto e ai prezzi del servizio copiato.

## Import Guidato Vecchi Contratti

Non viene introdotto import massivo.

La scelta attuale e preparare la UI per inserimento manuale guidato:

- l'operatore crea cliente e sede se mancanti;
- inserisce il contratto con il numero storico originale;
- aggiunge il servizio principale per tenant AZ, oppure i servizi contrattuali per tenant multi-servizio;
- imposta cadenza operativa sul servizio e cadenza fatturazione sul contratto;
- compila rinnovo tacito e condizioni economiche.

Questo permette di ricostruire progressivamente l'archivio senza rischiare import distruttivi o mappature legacy premature.

## Fuori Perimetro

Restano fuori da questo branch:

- generazione PDF contratto;
- rapportini PDF;
- XML;
- API Aruba;
- fatture fiscali o elettroniche;
- `work_orders`;
- `visits`;
- `inspections`;
- app mobile compatibility;
- import legacy massivo.
