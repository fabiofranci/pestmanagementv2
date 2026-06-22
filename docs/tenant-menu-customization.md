# Personalizzazione Menu Tenant

Data aggiornamento: 2026-06-22

Pest Management V2 permette di personalizzare i moduli visibili nel menu Filament in base al tenant corrente.

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

## Moduli Disponibili

Stringhe stabili disponibili:

```text
dashboard
customers
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

Tenant demo con tutti i moduli esplicitati:

```json
[
  "dashboard",
  "customers",
  "customer_sites",
  "contracts",
  "areas",
  "monitoring_points",
  "service_types",
  "pest_types",
  "organizations"
]
```

In alternativa si puo lasciare `enabled_modules = null` per ottenere lo stesso effetto con comportamento compatibile.

## Esempio AZ

Configurazione possibile per AZ, orientata al lavoro su clienti, sedi, contratti e impianti:

```json
[
  "dashboard",
  "customers",
  "customer_sites",
  "contracts",
  "areas",
  "monitoring_points",
  "service_types",
  "pest_types",
  "organizations"
]
```

Se AZ non deve vedere i cataloghi nel menu operativo, si puo usare:

```json
[
  "dashboard",
  "customers",
  "customer_sites",
  "contracts",
  "areas",
  "monitoring_points"
]
```

## Superuser Senza Tenant

Quando non c'e un tenant corrente, la configurazione non viene applicata. Questo mantiene visibili le voci amministrative necessarie, come `Organizzazioni`, ed evita errori nella navigazione del superuser.

## Nota Sicurezza

Nascondere una voce dal menu non e un controllo di sicurezza completo.

La logica e usata per semplificare l'esperienza Filament del tenant. Dove gia esisteva `canAccess`, e stato aggiunto un controllo minimo sul modulo, ma eventuali regole di sicurezza applicativa devono restare in policy, query scoping e controlli autorizzativi dedicati.
