# Analisi video cliente - area contratti

Data analisi: 2026-06-18

Obiettivo della fase: tradurre le esigenze emerse dal gestionale desktop mostrato dal cliente in una proposta coerente con Pest Management V2, senza copiare la vecchia UI, senza stravolgere Laravel + Filament e senza rompere la tenancy esistente.

## 1. Stato attuale del progetto

### Stack e architettura

- Progetto Laravel con Filament:
  - `laravel/framework` `^13.8`;
  - `filament/filament` `^5.6`;
  - `spatie/laravel-permission` `^7.4`.
- Il pannello Filament principale e `admin`, configurato in `app/Providers/Filament/AdminPanelProvider.php`.
- La tenancy e basata su:
  - database centrale per `tenants`, `users`, ruoli, sessioni e configurazione;
  - database tenant per dati operativi: clienti, sedi, servizi, contratti, aree e punti di monitoraggio.
- Il middleware `App\Http\Middleware\BootstrapTenantContext` risolve il tenant dall'utente o dalla sessione del superuser, attiva la connessione tenant e imposta il team Spatie Permissions.
- I model operativi usano `App\Models\Concerns\UsesTenantConnection`, quindi cambiano connessione in base al tenant attivo.
- `TenantScopedResource` applica accesso Filament tenant-aware e filtra i dati per gli utenti cliente.

### Model esistenti

- `Tenant`
  - Model centrale.
  - Contiene anagrafica tenant, configurazione database tenant, aspetto pannello e relazioni verso dati operativi.
  - Ha relazione `tenantAdmin()` verso il primo utente non superuser senza `customer_id`.
- `User`
  - Model centrale.
  - Supporta `tenant_id`, `customer_id`, `is_superuser`.
  - Distingue:
    - superuser;
    - tenant admin;
    - utente cliente area riservata.
- `Customer`
  - Model tenant.
  - Anagrafica cliente: nome, ragione sociale, tax id, contatti, indirizzo, note, stato.
  - Relazioni verso `sites()` e `contracts()`.
- `CustomerSite`
  - Model tenant.
  - Sede cliente con indirizzo, referente, codice sede, note, stato.
  - Relazioni verso `customer()` e `areas()`.
- `ServiceType`
  - Model tenant.
  - Catalogo tipi di servizio: nome, codice, descrizione, stato.
  - Relazione verso `areas()`.
- `PestType`
  - Model tenant.
  - Catalogo infestanti: nome, codice, descrizione, stato.
- `Area`
  - Model tenant.
  - Area interna a una sede cliente.
  - Collegata a `customer_site_id` e `service_type_id`.
  - Include `thresholds` JSON e stato.
- `MonitoringPoint`
  - Model tenant.
  - Punto di monitoraggio collegato ad area e tipo servizio.
  - Include codice, nome, tipo, modello, prodotto, coordinate, posizione mappa JSON e stato.
- `Contract`
  - Model tenant.
  - Contratto base collegato a cliente e sede.
  - Campi attuali: numero contratto, stato, data inizio/fine, rinnovo, durata, condizioni pagamento, valore totale, valuta, note.
  - Relazioni attuali: `tenant()`, `customer()`, `site()`.

### Migration esistenti

Migration centrali attive:

- `0001_01_01_000000_create_users_table.php`
- `0001_01_01_000001_create_cache_table.php`
- `0001_01_01_000002_create_jobs_table.php`
- `2026_05_26_023032_create_tenants_table.php`
- `2026_05_26_073931_create_permission_tables.php`
- `2026_05_26_074116_add_tenant_id_to_users_table.php`
- `2026_05_26_081500_add_tenant_id_to_permission_tables.php`
- `2026_05_26_150000_add_database_settings_to_tenants_table.php`
- `2026_05_26_150100_add_is_superuser_to_users_table.php`
- `2026_05_26_170000_add_panel_branding_to_tenants_table.php`
- `2026_06_01_100000_add_customer_id_to_users_table.php`

Migration operative centrali mantenute come compatibilita:

- `2026_05_26_023033_create_customers_table.php`
- `2026_05_26_023033_create_customer_sites_table.php`
- `2026_05_26_023033_create_service_types_table.php`
- `2026_05_26_023033_create_pest_types_table.php`
- `2026_05_26_023033_create_areas_table.php`
- `2026_05_26_023034_create_contracts_table.php`
- `2026_05_26_023034_create_monitoring_points_table.php`

Queste migration operative centrali fanno `return` in `up()` e `down()`: non creano piu le tabelle nel database centrale.

Migration tenant attiva:

- `database/migrations/tenant/2026_05_26_150200_create_tenant_tables.php`

Questa crea sul database tenant:

- `customers`;
- `customer_sites`;
- `service_types`;
- `pest_types`;
- `areas`;
- `contracts`;
- `monitoring_points`.

Nota operativa: ogni nuova tabella di dominio operativo per contratti/interventi/fatture/documenti deve stare nel percorso tenant, non nel database centrale, salvo dati esplicitamente globali.

### Filament Resources esistenti

- `Tenants/TenantResource`
  - Solo superuser.
  - Gestisce organizzazioni, database tenant, provisioning, ingresso nel tenant e utente admin tenant.
- `Customers/CustomerResource`
  - Tenant-scoped.
  - Accessibile anche agli utenti cliente, con filtro sul proprio `customer_id`.
  - Include azioni per creare, vedere, modificare e cancellare accesso area riservata cliente.
- `CustomerSites/CustomerSiteResource`
  - Tenant-scoped.
  - Accessibile anche agli utenti cliente.
- `ServiceTypes/ServiceTypeResource`
  - Tenant-scoped.
  - Catalogo interno, non accessibile agli utenti cliente.
- `PestTypes/PestTypeResource`
  - Tenant-scoped.
  - Catalogo infestanti.
- `Areas/AreaResource`
  - Tenant-scoped.
  - Accessibile anche agli utenti cliente, filtrato passando dalla sede.
- `MonitoringPoints/MonitoringPointResource`
  - Tenant-scoped.
  - Accessibile anche agli utenti cliente, filtrato passando da area e sede.
- `Contracts/ContractResource`
  - Tenant-scoped.
  - Accessibile anche agli utenti cliente.
  - Oggi e un CRUD base.
  - Non ha Relation Manager.
  - Non ha azioni di rinnovo, stampa, programmazione interventi, generazione fatture o gestione documenti.

### Stato delle entita richieste dal cliente

- `contracts`
  - Esiste come testata contratto.
  - Mancano righe servizio, programmazione interventi, programmazione fatture, documenti, rinnovi strutturati, storico operativo e legame con work order/visite/ispezioni.
- `customers`
  - Esiste come anagrafica cliente.
  - Copre i dati principali, ma non separa ancora contatti multipli, recapiti amministrativi/operativi o preferenze documentali.
- `customer_sites`
  - Esiste come sede di intervento.
  - Copre indirizzo e referente base.
  - E gia collegata ad aree e quindi ai punti di monitoraggio.
- `service_types`
  - Esiste come catalogo tipi servizio.
  - Non esiste ancora il concetto di servizio incluso in un contratto, con frequenza, prezzo, note operative e sede/area di applicazione.
- `areas`
  - Esiste come area della sede.
  - Buona base per impianti e monitoraggi, ma non e ancora storicizzata.
- `monitoring_points`
  - Esiste come punto di monitoraggio.
  - Buona base per impianti/dispositivi, ma non e ancora storicizzato e non e collegato a visite/ispezioni.

## 2. Gap rispetto al gestionale mostrato dal cliente

### Cosa esiste gia

- Anagrafica cliente.
- Sede di intervento.
- Contratto come record principale.
- Catalogo tipi servizio.
- Aree e punti di monitoraggio.
- Tenant-scoping solido per dati operativi.
- Area riservata cliente con accesso filtrato.
- Base Filament CRUD per tutte le entita principali gia presenti.

### Cosa manca

- Righe servizi incluse nel contratto.
- Frequenze operative dei servizi.
- Interventi programmati collegati al contratto.
- Fatture programmate collegate al contratto.
- Documenti/allegati del contratto.
- Stampe contratto, schede tecniche, rapportini.
- Rinnovo contratto come workflow strutturato.
- Materiale utilizzato.
- Work order, visite e ispezioni.
- Storico eventi contratto.
- Stato avanzamento contratto oltre al semplice campo `status`.
- Dati economici piu granulari: imponibile, IVA, sconti, rate, scadenze, stato fatturazione, stato pagamento.
- UI riepilogativa unica che consenta a un operatore di lavorare sul contratto senza entrare in molte schermate separate.

### Cosa va modellato meglio

- `Contract`
  - Oggi contiene campi testuali liberi come `renewal`, `term`, `payment_terms`.
  - Per sostenere flussi reali servono dati piu strutturati, almeno per scadenze, rinnovi, fatturazione e stato.
- Servizi contrattualizzati
  - `ServiceType` e un catalogo, non una riga contratto.
  - Serve una tabella dedicata per dire quali servizi sono inclusi in un contratto, con frequenza, prezzo, descrizione e note operative.
- Programmazione interventi
  - Non deve essere confusa con `work_order`.
  - La catena da rispettare e:
    - `contract`;
    - `scheduled_intervention`;
    - `work_order`;
    - `visit`;
    - `inspection`.
- Fatturazione programmata
  - Non e la fattura contabile finale.
  - Serve una pianificazione rate/scadenze collegata al contratto, che in futuro potra generare documenti o integrarsi con contabile.
- Documenti
  - Vanno distinti da semplici note.
  - Serve almeno metadato: tipo documento, visibilita cliente, file, data generazione/caricamento, autore.
- Impianti e punti di monitoraggio
  - Oggi i punti sono modificabili direttamente.
  - Per storicizzare gli impianti bisogna evitare che una modifica distrugga il contesto storico di una visita o ispezione passata.

### Cosa e solo UI

- Una schermata riepilogativa stile gestionale cliente.
- Tab come "Panoramica", "Servizi", "Interventi", "Fatturazione", "Documenti", "Storico".
- Pulsanti rapidi per stampe e rinnovo.
- Layout compatto per operatori abituati al gestionale desktop.
- Badge, filtri, indicatori di stato e sezioni collassabili.
- Select al posto degli ID numerici nelle form.

Questi elementi migliorano l'uso, ma non bastano se sotto non esistono le entita di dominio.

### Cosa e dominio applicativo

- Servizio incluso nel contratto.
- Programmazione intervento.
- Generazione o apertura di un work order.
- Visita eseguita da un operatore.
- Ispezione su punto di monitoraggio.
- Materiale utilizzato durante visita/intervento.
- Fattura programmata/scadenza di pagamento.
- Documento contrattuale o tecnico.
- Rinnovo contratto.
- Storico modifiche/eventi.
- Versionamento o storicizzazione di impianti, aree e punti.

## 3. Proposta funzionale per la schermata Contratto V2

La proposta non copia la vecchia UI desktop. Usa invece una pagina contratto Filament piu completa, con sezioni e Relation Manager, mantenendo il contratto come centro operativo.

### Panoramica

Contenuto:

- numero contratto;
- stato;
- cliente;
- sede principale;
- date inizio/fine;
- durata;
- rinnovo;
- valore totale;
- valuta;
- condizioni di pagamento;
- note interne;
- riepilogo prossime scadenze;
- riepilogo servizi inclusi;
- riepilogo prossimo intervento programmato;
- riepilogo prossima fattura programmata.

Scopo:

- dare all'operatore un colpo d'occhio unico;
- ridurre navigazione tra CRUD separati;
- mantenere comunque dati normalizzati.

### Servizi

Contenuto:

- elenco servizi inclusi nel contratto;
- tipo servizio;
- descrizione contrattuale;
- frequenza;
- periodo di validita;
- quantita o numero passaggi, se necessario;
- importo o quota del servizio;
- note operative;
- eventuale sede/area di riferimento.

Entita proposta:

- `contract_services`.

### Interventi programmati

Contenuto:

- calendario o tabella degli interventi pianificati;
- data prevista;
- servizio;
- sede;
- stato;
- note operative;
- eventuale generazione work order.

Entita proposta:

- `scheduled_interventions`.

La programmazione resta distinta dall'esecuzione:

- `scheduled_intervention`: previsione;
- `work_order`: incarico operativo;
- `visit`: visita effettiva;
- `inspection`: rilievo tecnico su punti/aree.

### Fatturazione programmata

Contenuto:

- scadenze previste;
- descrizione rata;
- importo;
- valuta;
- IVA o aliquota, se si decide di gestirla;
- data prevista;
- stato: programmata, emessa, pagata, annullata;
- riferimento futuro a fattura/documento esterno.

Entita proposta:

- `scheduled_invoices` o `contract_billing_schedules`.

Nome consigliato: `scheduled_invoices`, se l'obiettivo operativo e vicino alla fattura. In alternativa `contract_billing_schedules`, se si vuole rimarcare che non e ancora fattura fiscale.

### Documenti

Contenuto:

- contratto generato;
- schede servizio;
- schede sicurezza;
- rapportini;
- allegati cliente;
- documenti visibili o non visibili in area riservata.

Entita proposta:

- `contract_documents`;
- in futuro, tabella piu generica `documents` polimorfica se anche visite, clienti e sedi avranno allegati.

### Storico

Contenuto:

- creazione contratto;
- cambio stato;
- rinnovo;
- generazione interventi;
- generazione fatture programmate;
- stampa/generazione documenti;
- note operative importanti.

Entita proposta:

- fase iniziale: eventi semplici su `contract_events`;
- alternativa successiva: activity log package o implementazione audit piu ampia.

### Vista riepilogativa unica stile gestionale cliente

Si puo prevedere una pagina Filament dedicata dentro `ContractResource`, ad esempio:

- `view`: pagina riepilogativa;
- `edit`: modifica testata;
- Relation Manager sotto la pagina per servizi, interventi, fatture, documenti.

La vista unica dovrebbe:

- mostrare dati cliente e sede in alto;
- mostrare stato contratto e scadenze;
- avere tab o sezioni per i blocchi principali;
- avere azioni rapide nella testata;
- evitare una replica 1:1 del gestionale desktop.

## 4. Proposta tecnica

### Nuove tabelle tenant eventualmente necessarie

Le seguenti tabelle dovrebbero essere create in `database/migrations/tenant`, non nelle migration centrali.

#### `contract_services`

Scopo: righe servizio incluse nel contratto.

Campi indicativi:

- `id`;
- `tenant_id`;
- `contract_id`;
- `service_type_id`;
- `customer_site_id` nullable;
- `area_id` nullable;
- `description`;
- `frequency`;
- `quantity` nullable;
- `unit_price` nullable;
- `total_price` nullable;
- `currency`;
- `starts_on` nullable;
- `ends_on` nullable;
- `notes`;
- `status`;
- timestamps.

#### `scheduled_interventions`

Scopo: interventi previsti da contratto.

Campi indicativi:

- `id`;
- `tenant_id`;
- `contract_id`;
- `contract_service_id` nullable;
- `customer_site_id`;
- `service_type_id`;
- `planned_date`;
- `planned_time` nullable;
- `status`;
- `notes`;
- timestamps.

#### `scheduled_invoices`

Scopo: scadenze/fatture programmate.

Campi indicativi:

- `id`;
- `tenant_id`;
- `contract_id`;
- `description`;
- `due_date`;
- `amount`;
- `currency`;
- `vat_rate` nullable;
- `status`;
- `invoice_reference` nullable;
- `notes`;
- timestamps.

#### `contract_documents`

Scopo: documenti e allegati del contratto.

Campi indicativi:

- `id`;
- `tenant_id`;
- `contract_id`;
- `type`;
- `title`;
- `file_path`;
- `mime_type` nullable;
- `visible_to_customer` boolean;
- `generated_at` nullable;
- `uploaded_by` nullable;
- timestamps.

#### `contract_events`

Scopo: storico leggero del contratto.

Campi indicativi:

- `id`;
- `tenant_id`;
- `contract_id`;
- `event_type`;
- `title`;
- `payload` JSON nullable;
- `created_by` nullable;
- timestamps.

#### Tabelle successive per la catena operativa

Da introdurre dopo la prima versione contratto:

- `work_orders`;
- `visits`;
- `inspections`;
- `visit_materials` o `work_order_materials`;
- eventuali `monitoring_point_versions` o `monitoring_point_installations` per storicizzazione impianti.

### Modifiche ai model

`Contract`:

- aggiungere relazioni:
  - `services()`;
  - `scheduledInterventions()`;
  - `scheduledInvoices()`;
  - `documents()`;
  - `events()`;
  - in futuro `workOrders()`.
- valutare enum o costanti per `status`.
- strutturare meglio campi rinnovo, durata e pagamento senza rimuovere subito i campi esistenti.

`Customer`:

- relazione gia presente `contracts()`;
- utile aggiungere Relation Manager in Filament per sedi e contratti.

`CustomerSite`:

- aggiungere relazione `contracts()`;
- mantenere `areas()`;
- in futuro relazione verso interventi programmati e work order.

`ServiceType`:

- aggiungere relazione `contractServices()`.

`Area`:

- relazione futura verso storico impianti o versioni punti.

`MonitoringPoint`:

- relazione futura verso `inspections()`;
- evitare hard delete o modifiche distruttive quando ci saranno ispezioni storiche.

Nuovi model:

- `ContractService`;
- `ScheduledIntervention`;
- `ScheduledInvoice`;
- `ContractDocument`;
- `ContractEvent`;
- successivamente `WorkOrder`, `Visit`, `Inspection`.

Tutti i nuovi model operativi devono usare `UsesTenantConnection`.

### Modifiche alle Filament Resources

`ContractResource`:

- aggiungere una pagina `ViewContract` o una edit page piu ricca;
- sostituire campi FK numerici con `Select::relationship()` filtrati per tenant;
- organizzare il form in sezioni:
  - dati contratto;
  - cliente e sede;
  - scadenze;
  - pagamento;
  - note.
- aggiungere Relation Manager:
  - servizi;
  - interventi programmati;
  - fatture programmate;
  - documenti;
  - storico.

`CustomerResource`:

- aggiungere Relation Manager:
  - sedi;
  - contratti;
  - documenti cliente, eventualmente piu avanti.

`CustomerSiteResource`:

- aggiungere Relation Manager:
  - aree;
  - punti di monitoraggio;
  - contratti della sede;
  - interventi programmati della sede, quando esisteranno.

`ServiceTypeResource`:

- restare catalogo.
- Non trasformarlo in contratto servizio.

`AreasResource` e `MonitoringPointResource`:

- sostituire FK numeriche con select relazionali;
- preparare viste/azioni che non cancellino accidentalmente dati storici quando saranno legate alle ispezioni.

### Azioni Filament utili

Su contratto:

- `Rinnova contratto`;
- `Duplica contratto`;
- `Genera interventi programmati`;
- `Genera fatture programmate`;
- `Stampa contratto`;
- `Stampa schede servizio`;
- `Carica allegato`;
- `Condividi con cliente`;
- `Chiudi contratto`;
- `Annulla contratto`.

Su intervento programmato:

- `Crea work order`;
- `Posticipa`;
- `Annulla`;
- `Segna come da ripianificare`.

Su documento:

- `Scarica`;
- `Rigenera`;
- `Rendi visibile al cliente`;
- `Nascondi al cliente`.

### Relation Manager necessari

Per `ContractResource`:

- `ContractServicesRelationManager`;
- `ScheduledInterventionsRelationManager`;
- `ScheduledInvoicesRelationManager`;
- `ContractDocumentsRelationManager`;
- `ContractEventsRelationManager`.

Per `CustomerResource`:

- `CustomerSitesRelationManager`;
- `ContractsRelationManager`.

Per `CustomerSiteResource`:

- `AreasRelationManager`;
- `MonitoringPointsRelationManager`;
- `ContractsRelationManager`.

In futuro:

- `WorkOrdersRelationManager`;
- `VisitsRelationManager`;
- `InspectionsRelationManager`;
- `MaterialsRelationManager`.

## 5. Priorita

### Subito

- Documentare e confermare il modello Contratto V2.
- Introdurre, con migration tenant piccole e separate, le entita:
  - `contract_services`;
  - `scheduled_interventions`;
  - `scheduled_invoices`;
  - `contract_documents`;
  - eventualmente `contract_events`.
- Aggiungere model e relazioni minime.
- Migliorare `ContractResource` con sezioni e select relazionali.
- Aggiungere Relation Manager sul contratto.
- Aggiungere test feature per tenant scoping dei nuovi dati.
- Garantire che gli utenti cliente vedano solo dati del proprio `customer_id`.

### Dopo

- Implementare generazione documenti/stampe.
- Implementare azione rinnovo contratto.
- Implementare generazione automatica interventi programmati da righe servizio.
- Implementare generazione automatica fatture programmate.
- Introdurre `work_orders`.
- Collegare `scheduled_intervention -> work_order`.
- Estendere area riservata cliente per documenti e rapportini.

### Da rimandare

- Import legacy.
- Compatibilita app mobile.
- Contabilita completa.
- Firma digitale.
- Integrazioni esterne con gestionali fiscali.
- Mappe avanzate e planimetrie interattive.
- Motore ricorrenze complesso.
- Audit log completo di tutta l'applicazione.

### Da evitare

- Copiare la UI desktop 1:1.
- Spostare dati operativi nel database centrale.
- Rompere o bypassare `BootstrapTenantContext`.
- Collassare `scheduled_intervention`, `work_order`, `visit` e `inspection` in una singola tabella generica.
- Usare JSON generico per dati centrali come servizi, fatture o interventi.
- Fare una modifica massiva di tutte le resource insieme.
- Cancellare fisicamente impianti o punti che potranno servire allo storico.
- Trasformare `ServiceType` in riga contrattuale: deve restare catalogo.

## 6. Piano branch Git

Branch di lavoro consigliati:

- `feature/contratti-v2-analisi`
  - Solo documento di analisi e decisioni.
- `feature/contratti-v2-modello`
  - Migration tenant e model per righe contratto, interventi programmati, fatture programmate e documenti.
- `feature/contratti-v2-filament`
  - Resource, relation manager, azioni Filament e UI contratto.
- `feature/contratti-v2-stampe`
  - Generazione/stampa documenti.
- `feature/contratti-v2-work-orders`
  - Avvio catena operativa `scheduled_intervention -> work_order -> visit -> inspection`.

Commit previsti per la prima implementazione:

1. `docs: analizza requisiti contratti da video cliente`
2. `feat: aggiunge modello tenant per servizi contratto`
3. `feat: aggiunge programmazione interventi contratto`
4. `feat: aggiunge programmazione fatture contratto`
5. `feat: aggiunge documenti contratto`
6. `feat: espone relation manager contratto in Filament`
7. `test: copre tenant scoping dati contratto v2`

Ordine di implementazione consigliato:

1. Approvare il documento di analisi.
2. Creare branch `feature/contratti-v2-modello`.
3. Aggiungere una tabella alla volta in migration tenant.
4. Aggiungere model e relazioni.
5. Aggiungere test tenant-scoped per ogni nuova entita.
6. Creare Relation Manager su `ContractResource`.
7. Migliorare form contratto con select relazionali.
8. Aggiungere azioni Filament solo dopo che il modello e stabile.
9. Rimandare stampe, import legacy e mobile compatibility a branch successivi.

## Nota finale

Il progetto ha gia una buona base per cliente, sede, servizi, aree, punti e tenancy. Il gap principale non e riprodurre la schermata desktop, ma introdurre le entita operative mancanti intorno al contratto. La direzione piu sicura e costruire Contratto V2 come centro funzionale in Filament, con tabelle tenant dedicate e relation manager incrementali.
