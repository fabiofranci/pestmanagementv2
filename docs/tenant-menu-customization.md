# Personalizzazione Menu Tenant

Data aggiornamento: 2026-07-15

Pest Management V2 permette di personalizzare i moduli visibili nel menu Filament e il loro ordine in base al tenant corrente.

La configurazione vive nel database centrale, nella tabella `tenants`.

## Campo `enabled_modules`

Campo:

```text
tenants.enabled_modules
```

Tipo:

```text
JSON nullable
```

Regola di compatibilita:

- se `enabled_modules` e `null`, tutti i moduli sono visibili;
- se `enabled_modules` e un array vuoto, tutti i moduli sono visibili;
- se contiene valori, sono visibili solo i moduli indicati.

## Campo `module_order`

Campo:

```text
tenants.module_order
```

Tipo:

```text
JSON nullable
```

Regole:

- `enabled_modules` decide se un modulo e visibile;
- `module_order` decide l'ordine delle voci visibili;
- `module_order` si gestisce nel form tenant con una lista ordinabile;
- se `module_order` e `null` o vuoto, Filament mantiene l'ordine standard attuale;
- se contiene valori, i moduli indicati vengono ordinati prima seguendo l'array;
- i moduli non presenti in `module_order` finiscono dopo quelli configurati, mantenendo l'ordine standard.

Nel form ogni riga della lista contiene una select `Modulo`. Il dato salvato resta un JSON semplice:

```json
[
  "dashboard",
  "contracts",
  "customers"
]
```

Non viene salvato un array di oggetti.

## Moduli Disponibili

Stringhe stabili disponibili:

```text
dashboard
customers
customer_groups
customer_sites
contracts
areas
monitoring_points
service_types
pest_types
organizations
```

Queste chiavi sono definite in `App\Support\Tenancy\TenantModules`.

## Esempio Demo

Tenant demo con tutti i moduli visibili usando il comportamento compatibile:

```text
enabled_modules = null
module_order = null
```

In alternativa, lista completa esplicita:

```json
[
  "dashboard",
  "customers",
  "customer_groups",
  "customer_sites",
  "contracts",
  "areas",
  "monitoring_points",
  "service_types",
  "pest_types",
  "organizations"
]
```

La stessa lista puo essere usata anche in `module_order` se si vuole rendere esplicito l'ordine completo.

## Esempio AZ

Configurazione possibile per AZ, orientata al lavoro su contratti e anagrafiche collegate:

`enabled_modules`:

```json
[
  "dashboard",
  "contracts",
  "customer_sites",
  "customers",
  "customer_groups",
  "service_types"
]
```

`module_order`:

```json
[
  "dashboard",
  "contracts",
  "customer_sites",
  "customers",
  "customer_groups",
  "service_types"
]
```

Questa configurazione non hardcoda AZ nel codice: e solo una configurazione del record tenant.

## Superuser Senza Tenant

Quando non c'e un tenant corrente, la configurazione non viene applicata. Questo mantiene visibili le voci amministrative necessarie, come `Organizzazioni`, ed evita errori nella navigazione del superuser.

## Nota Sicurezza

Nascondere una voce dal menu non e un controllo di sicurezza completo.

La logica e usata per semplificare l'esperienza Filament del tenant. Dove gia esisteva `canAccess`, e stato aggiunto un controllo minimo sul modulo, ma eventuali regole di sicurezza applicativa devono restare in policy, query scoping e controlli autorizzativi dedicati.
