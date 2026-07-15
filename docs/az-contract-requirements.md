# Requisiti contratti AZ

Data aggiornamento: 2026-07-15

Questo documento descrive gli adeguamenti del modello contratto per AZ Disinfestazioni.

## Numero contratto

`contracts.contract_number` resta una stringa modificabile manualmente per import e storico.

Per i nuovi contratti il sistema propone il prossimo numero progressivo calcolato sul tenant:

- considera solo `contract_number` puramente numerici;
- ignora valori storici come `2026/1`, `AZ-009` o altri codici non numerici;
- propone `MAX(numerico) + 1`.

La logica vive in `App\Support\Contracts\ContractNumberService`.

## Stati contratto

Gli stati operativi AZ sono:

- `active`: contratto attivo;
- `concluded`: contratto concluso, tipicamente per rinnovo;
- `cancelled`: contratto disdetto;
- `expired`: contratto scaduto.

Valori legacy come `draft`, `suspended` o `closed` possono ancora essere letti e mostrati, ma non sono il flusso principale AZ.

## Cadenza fatturazione

La fonte principale per generare il piano fatturazione e `contracts.billing_frequency`.

`contract_services.billing_frequency` resta disponibile solo per compatibilita temporanea con dati gia inseriti, ma non guida piu la generazione di `contract_billing_schedules`.

Campi contratto:

- `billing_frequency`: cadenza amministrativa;
- `billing_installments_count`: numero rate indicativo/manuale;
- `payment_terms`: condizioni pagamento, con opzione `Visto fattura`.

Se `billing_frequency` manca, la generazione scadenze non crea record e restituisce un warning chiaro.

Se `total_value` manca o vale zero, la generazione scadenze non crea record.

## Rinnovo

L'azione `Rinnova contratto`:

- richiede conferma;
- crea un nuovo contratto con numero progressivo;
- imposta `renewed_from_contract_id`;
- copia i servizi contrattuali;
- se `tacit_renewal` e attivo e `renewal_price_increase_percentage` e maggiore di zero, applica l'aumento a valore contratto e prezzi servizio;
- imposta il contratto origine a `concluded`;
- registra eventi su contratto origine e contratto nuovo.

Relazioni:

- `Contract::renewedFrom()`;
- `Contract::renewals()`.

## Disdetta

L'azione `Disdici contratto` richiede conferma, imposta `status = cancelled` e registra un evento `cancelled`.
