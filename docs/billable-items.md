# Articoli fatturabili

Il catalogo `billable_items` contiene materiali, accessori, contenitori, trappole e consumabili fatturabili al cliente.

Esempi:

- Contenitore esca
- Contenitore per monitoraggio
- Lampada UV
- Cartello posizionamento
- Paletto di fissaggio
- Trappola collante
- Esca
- Consumabile generico

## Differenza dai servizi

I servizi operativi restano in `service_types`.

Un `service_type` descrive una prestazione operativa, per esempio derattizzazione, disinfestazione o monitoraggio.

Un `billable_item` descrive invece un articolo fatturabile separato dal servizio, come un contenitore, una lampada, una trappola o un consumabile.

Questa separazione evita di trattare materiali e accessori come servizi operativi e mantiene il catalogo prezzi piu chiaro.

## Prezzo standard

Ogni articolo puo avere:

- `default_unit_price`
- `vat_rate`

Se il prezzo standard non e noto, `default_unit_price` resta `null`.

## Prezzi personalizzati cliente

La tabella `customer_billable_item_prices` permette di definire condizioni specifiche per cliente e articolo:

- `custom_unit_price`
- `discount_percentage`
- `notes`

La coppia `tenant_id + customer_id + billable_item_id` e univoca, quindi un cliente puo avere una sola regola prezzo per ciascun articolo.

## Priorita prezzo

Il calcolo prezzo segue questa priorita:

1. se `custom_unit_price` e valorizzato, usa il prezzo personalizzato;
2. altrimenti, se `discount_percentage` e valorizzato e l'articolo ha un prezzo standard, applica lo sconto;
3. altrimenti usa `default_unit_price`;
4. se non esiste alcun prezzo disponibile, ritorna `null`.

La logica e centralizzata in:

```text
App\Support\Billing\BillableItemPricingService
```

## Uso nei contratti

Gli articoli fatturabili possono essere collegati ai contratti tramite:

```text
contract_billable_items
```

Questa tabella rappresenta materiali, contenitori, trappole, lampade, cartelli, paletti, esche o altri articoli previsti dalla configurazione economica del contratto.

Esempio AZ per un contratto di derattizzazione:

- Contenitore esca x 10;
- Paletto di fissaggio x 10;
- Cartelli posizionamento x 5.

Il collegamento al contratto mantiene:

- articolo;
- quantita;
- prezzo unitario;
- sconto percentuale informativo/manuale;
- totale;
- note;
- stato.

Quando viene scelto un articolo nel contratto, il sistema propone il prezzo usando `BillableItemPricingService` e il cliente del contratto.

Il prezzo unitario salvato su `contract_billable_items.unit_price` rappresenta il prezzo finale unitario gia applicato al cliente. Per questo motivo `discount_percentage` non viene riapplicato nel totale, ma resta disponibile come dato informativo o manuale.

Il totale e calcolato come:

```text
quantity * unit_price
```

Se l'utente modifica manualmente `unit_price`, il valore manuale viene rispettato.

Gli elementi fatturabili collegati al contratto non generano ancora fatture XML, non alimentano la fatturazione elettronica e non sono ancora usati negli interventi extra.

## Uso futuro

Il catalogo potra essere collegato anche a interventi per:

- valorizzare consumabili usati durante un intervento;
- mantenere coerenza dei prezzi nel tempo;
- applicare listini o scontistiche cliente senza duplicare servizi operativi.

## Modulo tenant

Il modulo stabile e:

```text
billable_items
```

Rispetta `tenants.enabled_modules` e `tenants.module_order` come gli altri moduli tenant.
