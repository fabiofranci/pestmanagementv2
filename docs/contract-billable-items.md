# Elementi fatturabili su contratto

Data aggiornamento: 2026-07-15

Gli elementi fatturabili su contratto permettono di indicare materiali e articoli previsti nella configurazione economica del contratto.

## Tabella

I dati sono salvati nella tabella tenant:

```text
contract_billable_items
```

Campi principali:

- `tenant_id`;
- `contract_id`;
- `billable_item_id`;
- `quantity`;
- `unit_price`;
- `discount_percentage`;
- `total_price`;
- `notes`;
- `status`.

La cancellazione del contratto elimina anche le righe collegate, secondo la foreign key `contract_id`.

## Differenza dai servizi

I servizi contrattuali vivono in `contract_services` e descrivono cosa viene eseguito operativamente: derattizzazione, disinfestazione, monitoraggio o altre prestazioni.

Gli elementi fatturabili descrivono invece articoli economici collegati al contratto: contenitori, trappole, lampade, cartelli, paletti, esche e materiali.

Questa separazione evita di trasformare materiali e accessori in servizi operativi.

## Esempio AZ

Contratto 2569 - Derattizzazione:

- Contenitore esca x 10;
- Paletto di fissaggio x 10;
- Cartelli posizionamento x 5.

Queste righe sono accessorie al contratto e restano modificabili manualmente.

## Prezzo

Quando si seleziona un articolo nel contratto, il sistema recupera il cliente del contratto e usa:

```text
App\Support\Billing\BillableItemPricingService
```

Se esiste un prezzo personalizzato cliente, viene proposto quello e lo sconto non viene compilato. Se esiste uno sconto cliente e l'articolo ha un prezzo standard, viene proposto il prezzo standard come prezzo unitario lordo e lo sconto viene compilato in `discount_percentage`. In assenza di regole cliente, viene proposto il prezzo standard dell'articolo.

La logica specifica per lo stato del form contratto e incapsulata in:

```text
App\Support\Billing\ContractBillableItemPricingService
```

## Totale

La scelta funzionale e:

```text
unit_price = prezzo unitario lordo/listino applicato al cliente
total_price = quantity * unit_price * (1 - discount_percentage / 100)
```

Se `discount_percentage` e vuoto o pari a zero, `total_price` e `quantity * unit_price`.

`discount_percentage` viene applicato al totale riga. Il sistema evita il doppio sconto usando il prezzo lordo come `unit_price` quando la regola cliente e basata su percentuale, mentre usa il `custom_unit_price` senza sconto quando la regola cliente e un prezzo personalizzato.

Se l'utente modifica manualmente `unit_price`, il valore manuale viene rispettato.

## Limiti attuali

Gli elementi fatturabili su contratto non generano ancora fatture XML, non alimentano la fatturazione elettronica e non sono collegati agli interventi extra.

La futura generazione automatica di fatture dovra decidere se usare queste righe come dettaglio economico del contratto, come righe separate o come supporto ai riepiloghi.
