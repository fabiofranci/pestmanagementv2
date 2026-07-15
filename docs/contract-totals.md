# Totale contratto

Data aggiornamento: 2026-07-15

Il totale economico iniziale del contratto viene calcolato sommando servizi contrattuali e articoli fatturabili previsti dal contratto.

## Service

La logica e centralizzata in:

```text
App\Support\Contracts\ContractTotalsService
```

Metodi principali:

- `calculateServicesTotal(Contract $contract)`;
- `calculateBillableItemsTotal(Contract $contract)`;
- `calculateContractTotal(Contract $contract)`;
- `updateContractTotal(Contract $contract)`.

## Regole

`services_total` somma i `contract_services` con `status = active`.

`billable_items_total` somma i `contract_billable_items` con `status = active`.

Per ogni riga:

- se `total_price` e valorizzato, usa `total_price`;
- se `total_price` manca e sono valorizzati `quantity` e `unit_price`, usa `quantity * unit_price`;
- altrimenti la riga vale zero.

`contract_total` e:

```text
services_total + billable_items_total
```

Gli `intervention_billable_items` sono esclusi dal totale iniziale del contratto. Sono extra successivi da collegare a una scadenza/fattura.

## UI

In `ViewContract` e `EditContract` e disponibile l'azione:

```text
Ricalcola totale contratto
```

L'azione aggiorna `contracts.total_value` e mostra:

- totale servizi;
- totale articoli fatturabili;
- totale contratto.

Il ricalcolo automatico viene eseguito anche dopo il salvataggio del servizio principale in modalita `single_service` e dopo create, edit o delete dei servizi contrattuali e degli articoli fatturabili del contratto.

## Note operative

Il campo `contracts.total_value` resta modificabile manualmente per import e storico. La fonte di verita per ricalcolare il valore corrente e `ContractTotalsService`.
