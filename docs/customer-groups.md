# Gruppi clienti

I gruppi cliente permettono di collegare piu clienti dello stesso tenant a una struttura comune.

Esempi:

- gruppo `ANGIPLAST`
- condomini gestiti dallo stesso amministratore
- clienti collegati alla stessa proprieta o azienda

Il gruppo oggi serve per organizzare anagrafiche collegate. In futuro potra diventare una base per listini, scontistiche, riepiloghi e logiche di prezzo.

## Struttura dati

La tabella tenant `customer_groups` contiene:

- `tenant_id`
- `name`
- `code`
- `description`
- `status`
- timestamps

Il nome e univoco per tenant tramite vincolo `tenant_id + name`.

La tabella tenant `customers` contiene il campo nullable:

```text
customer_group_id
```

La foreign key punta a `customer_groups.id` e usa `nullOnDelete`: eliminando un gruppo, i clienti restano disponibili e il riferimento al gruppo viene azzerato.

## Relazioni

Model:

- `App\Models\CustomerGroup`
- `CustomerGroup::customers()`
- `Customer::customerGroup()`

## Filament

La resource `CustomerGroupResource` gestisce i gruppi dal menu tenant con label:

- singolare: `Gruppo cliente`
- plurale/menu: `Gruppi clienti`

Il form espone:

- nome
- codice
- descrizione
- stato

La tabella mostra:

- nome
- codice
- stato
- numero clienti collegati
- data ultimo aggiornamento

Nel form cliente e disponibile il campo `Gruppo`, nullable, searchable e precaricato.

## Modulo tenant

Il modulo stabile e:

```text
customer_groups
```

Rispetta `tenants.enabled_modules` e `tenants.module_order` come gli altri moduli tenant.
