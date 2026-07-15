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

## Uso futuro

Il catalogo potra essere collegato a contratti e interventi per:

- aggiungere materiali/accessori fatturabili a un contratto;
- valorizzare consumabili usati durante un intervento;
- mantenere coerenza dei prezzi nel tempo;
- applicare listini o scontistiche cliente senza duplicare servizi operativi.

## Modulo tenant

Il modulo stabile e:

```text
billable_items
```

Rispetta `tenants.enabled_modules` e `tenants.module_order` come gli altri moduli tenant.
