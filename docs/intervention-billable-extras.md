# Extra fatturabili interventi

Data aggiornamento: 2026-07-15

Gli extra fatturabili interventi rappresentano materiali sostituiti, consumabili usati o articoli aggiunti durante uno specifico intervento programmato.

## Tabella

I dati sono salvati nella tabella tenant:

```text
intervention_billable_items
```

Campi principali:

- `tenant_id`;
- `scheduled_intervention_id`;
- `contract_id`;
- `billable_item_id`;
- `contract_billing_schedule_id`;
- `description`;
- `quantity`;
- `unit_price`;
- `total_price`;
- `status`;
- `notes`.

`contract_id` viene derivato dall'intervento quando l'extra nasce nel contesto di uno `scheduled_intervention`.

## Differenza dagli articoli previsti dal contratto

Gli articoli previsti dal contratto vivono in `contract_billable_items` e descrivono la configurazione economica concordata a monte: contenitori previsti, cartelli iniziali, lampade o altri materiali inclusi nel contratto.

Gli extra intervento vivono in `intervention_billable_items` e descrivono cio che viene rilevato durante l'esecuzione: sostituzioni, aggiunte, consumabili o materiali usati.

Esempi AZ:

- sostituito contenitore esca x 1;
- sostituita trappola collante x 3;
- aggiunto cartello posizionamento x 2;
- sostituita lampada UV x 1.

## Prezzo e totale

Quando si seleziona un articolo, il sistema recupera il cliente dal contratto collegato e usa:

```text
App\Support\Billing\BillableItemPricingService
```

La UI propone `unit_price` se disponibile e valorizza `description` con il nome articolo se la descrizione e vuota.

Il totale viene calcolato come:

```text
quantity * unit_price
```

I campi restano modificabili manualmente. Se `unit_price` manca, il totale resta vuoto e non blocca il salvataggio.

La logica di supporto vive in:

```text
App\Support\Billing\InterventionBillableItemService
```

## Stati

`pending` indica un extra rilevato e non ancora collegato a una scadenza fatturazione.

`added_to_invoice` indica un extra collegato a una `contract_billing_schedule` come preparazione amministrativa.

`cancelled` indica un extra annullato. Non viene collegato alle scadenze.

## Collegamento alla scadenza

Nel piano fatturazione del contratto, l'azione `Aggiungi extra pending` prende tutti gli extra `pending` del contratto, li collega alla scadenza selezionata e li imposta a `added_to_invoice`.

L'azione non tocca extra `cancelled` e non ricollega extra gia `added_to_invoice`, quindi puo essere rilanciata senza duplicare il collegamento.

## Limiti attuali

Questo step non genera fatture vere, non genera XML, non integra Aruba e non modifica i rapportini PDF.

Il collegamento alla scadenza e solo un dato preparatorio per un futuro flusso di fatturazione.
