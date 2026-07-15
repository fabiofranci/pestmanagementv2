# Articoli fatturabili

Il catalogo `billable_items` contiene materiali, accessori, contenitori, trappole e consumabili fatturabili al cliente.

Esempi:

- Contenitori esca
- Contenitori per monitoraggio
- Lampada UV
- Cartelli Posizionamento
- Paletti di fissaggio
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

- Contenitori esca x 10;
- Paletti di fissaggio x 10;
- Cartelli posizionamento x 5.

Il collegamento al contratto mantiene:

- articolo;
- quantita;
- prezzo unitario lordo;
- sconto percentuale applicato al totale riga;
- totale;
- note;
- stato.

Quando viene scelto un articolo nel contratto, il sistema propone il prezzo usando `BillableItemPricingService` e il cliente del contratto.

Il prezzo unitario salvato su `contract_billable_items.unit_price` rappresenta il prezzo lordo/listino applicabile al cliente. Se la regola cliente prevede solo uno sconto, il sistema propone il prezzo standard come `unit_price` e lo sconto in `discount_percentage`, evitando di salvare un prezzo gia scontato e poi scontarlo di nuovo.

Se la regola cliente prevede invece un `custom_unit_price`, quel prezzo viene proposto come prezzo unitario lordo effettivo e `discount_percentage` resta vuoto.

Il totale e calcolato come:

```text
quantity * unit_price * (1 - discount_percentage / 100)
```

Se `discount_percentage` e vuoto o pari a zero, il totale resta `quantity * unit_price`.

Se l'utente modifica manualmente `unit_price`, il valore manuale viene rispettato.

Gli elementi fatturabili collegati al contratto non generano ancora fatture XML, non alimentano la fatturazione elettronica e restano distinti dagli extra rilevati durante gli interventi.

Nel totale iniziale del contratto gli articoli attivi in `contract_billable_items` vengono sommati ai servizi contrattuali attivi. Gli extra in `intervention_billable_items` non sono inclusi nel valore iniziale del contratto.

La logica di ricalcolo vive in:

```text
App\Support\Contracts\ContractTotalsService
```

## Extra rilevati durante intervento

Durante un intervento il tecnico puo registrare materiali sostituiti o aggiunti tramite:

```text
intervention_billable_items
```

Esempi:

- sostituito contenitore esca x 1;
- sostituita trappola collante x 3;
- aggiunto cartello posizionamento x 2;
- sostituita lampada UV x 1.

Queste righe nascono da uno specifico `scheduled_intervention`, sono collegate al contratto e possono essere preparate per la prossima scadenza fatturazione.

Stati supportati:

- `pending`: extra rilevato e non ancora collegato a una scadenza;
- `added_to_invoice`: extra collegato a una scadenza fatturazione come dato preparatorio;
- `cancelled`: extra annullato, non da fatturare.

Il collegamento a `contract_billing_schedules` non genera righe fattura vere e non produce XML.

## Uso futuro

Il catalogo potra essere esteso per:

- mantenere coerenza dei prezzi nel tempo;
- applicare listini o scontistiche cliente senza duplicare servizi operativi.

## Modulo tenant

Il modulo stabile e:

```text
billable_items
```

Rispetta `tenants.enabled_modules` e `tenants.module_order` come gli altri moduli tenant.

Il seeder AZ `az:seed-demo-data` abilita questo modulo e crea gli 8 articoli fatturabili iniziali con `default_unit_price` e `vat_rate` non valorizzati finche i prezzi non vengono confermati.
