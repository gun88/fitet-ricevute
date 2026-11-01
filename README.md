# FITET Ricevute

Plugin WordPress con frontend Angular su `/fitet-ricevute/`. Versione **0.9.0**.

## Pagine del portale

- **Tornei**: una riga per torneo, filtro “Mostra solo tornei con iscrizioni” inizialmente attivo;
  azioni Dettaglio, Gare e Iscrizioni. Le ultime due aprono gli elenchi con il torneo già selezionato.
- **Gare**: elenco delle gare note, filtro con iscrizioni inizialmente attivo, torneo parent cliccabile,
  modifica della quota via API, origine prezzo e confronto con il totale PDF; Dettaglio e Iscrizioni per gara.
  Lasciare vuota una quota rimuove il prezzo manuale e ripristina quello automatico.
- **Iscrizioni**: link al torneo, alla gara e agli atleti; stato della quota e del torneo, aggiunta singola
  o delle righe selezionate alla bozza rimborso. Le quote già rimborsate non sono aggiungibili.
- **Rimborsi Emessi**: registro delle emissioni con data/ora server, importo, emittente e numero iscrizioni.
- **Bozza rimborso**: iscrizioni personali salvate nel database, eliminabili, note modificabili per riga
  e recap per atleta. Il badge nel menu conta tutti gli elementi, anche di altre stagioni.
  L’emissione apre il dettaglio del rimborso; qui si sceglie cosa stampare.

Le pagine dettaglio di torneo, gara, rimborso e giocatore riusano le stesse tabelle e azioni degli elenchi.
Il dettaglio giocatore mostra iscrizioni e rimborsi; è raggiungibile dai nomi degli atleti.
La directory giocatori resta raggiungibile su `#/giocatori`. Il vecchio URL `#/prezzi` reindirizza a Gare; `#/carrello` apre Bozza rimborso.

La select della stagione è unica per il frontend, nell’intestazione desktop o nel menu mobile,
e salvata per sito/account nel browser. Non ha un’etichetta visibile, ma mantiene il nome accessibile.
La voce **- seleziona stagione -** svuota le viste e disabilita il pulsante di sincronizzazione;
anche questa scelta viene conservata. Le etichette delle stagioni sono compatte, per esempio 2026/27.
Le stagioni vanno dal 1° luglio al 30 giugno; “Ultima disponibile” sceglie la più recente nei dati.
La stagione filtra elenchi, dettagli e bozza rimborso. Le emissioni conservano sempre il totale originale:
nel dettaglio l'elenco segue il filtro globale, mentre il PDF riproduce l'emissione completa.

AG Grid Community: testi selezionabili, filtri, ordinamento, ricerca e preferenze per tabella/utente/browser.
100 righe iniziali, con precedenza alle preferenze già salvate. Layout responsive e scorrimento interno.
La barra di sincronizzazione e la data dell'ultimo completamento riuscito restano nella pagina Tornei.

## Intestazione e spazi delle pagine

Su desktop, logo, nome e navigazione sono a sinistra; stagione, nome e avatar dell’utente a destra,
in un’unica barra. Il menu del nome/avatar apre **Profilo utente** e **Log out**.
La pagina `#/utente` mostra nome, username di accesso e pulsante di uscita.

Su mobile e tablet la barra mostra logo/nome e hamburger. Il menu scorre da sinistra e lascia visibile
una parte oscurata della pagina. Contiene navigazione, stagione e due azioni utente affiancate.
Si chiude dopo la scelta di una pagina o stagione, premendo hamburger, Escape o l’area oscurata.
Lo sfondo resta inattivo e la navigazione da tastiera resta nel menu finché è aperto.

Le pagine condividono margini e intestazioni compatti. In Tornei e Gare il filtro con iscrizioni è
accanto alla ricerca su desktop e sopra di essa su mobile. L’ultima sincronizzazione rimane vicina
al pulsante; avanzamento ed errori della sincronizzazione compaiono nella pagina Tornei.
Le azioni fisse a destra nelle griglie diventano scorrevoli su mobile per lasciare visibili i dati.

## Bozza rimborso, emissione e stampa

La bozza è distinta per utente del portale e società: resiste a logout, cambio browser e riavvio.
Contiene al massimo 1000 quote. Il prezzo è riletto dal server e non congelato dall’aggiunta alla bozza.
Una quota rimossa dalla fonte resta visibile come “Da verificare” e può essere eliminata, ma non emessa.
La colonna **Note riga** è modificabile e salvata via API (massimo 1000 caratteri per iscrizione).
**Note generali** è un campo multilinea, massimo 2000 caratteri.
La società emittente proviene sempre dalla configurazione sul server e non è modificabile nella bozza.
La ripartizione dei doppi rimane selezionabile: metà per atleta, tutta al primo oppure tutta al secondo.

**Emetti rimborsi** registra sul server, in transazione, iscrizioni, importi, note e impostazioni,
segna le quote rimborsate e le rimuove dalla propria bozza. Poi apre il dettaglio del rimborso.
La richiesta è protetta da sessione, CSRF, controllo società, confronto dei prezzi e chiave idempotente;
richieste concorrenti o ripetute non devono produrre due emissioni per lo stesso invio.
Con una stagione specifica vengono emesse solo le quote visibili; le altre restano nella bozza.

Nel dettaglio, **Stampa ricevute** offre **Cumulativa**, **Per singolo atleta** o **Tutto insieme (ZIP)**.
Il formato non si sceglie durante l’emissione. Si può ristampare cambiando modalità senza registrare
un nuovo rimborso. I PDF sono prodotti localmente dal browser; sul server rimangono i dati per rigenerarli.
Prezzi, note, firma e timbro dell’emissione sono snapshot: successive modifiche non cambiano lo storico.
Se la stampa si interrompe, il rimborso resta disponibile nel registro.

Le marcature “rimborsata” delle versioni precedenti sono conservate, senza date di emissione inventate.
Il registro parte dalle emissioni effettuate con la 0.7.0. Le API precedenti di marcatura operativa
restano compatibili, ma non creano documenti nel registro.

## Sincronizzazione FITeT

Il backend effettua un solo login per leggere:

- `/login/iscrizioni/testa_iscrizioni_reg.asp` (catalogo territoriale)
- `/login/iscrizioni/testa_iscrizioni.asp` (catalogo nazionale)
- `/login/iscrizioni/lista_partecipazione_tornei.asp` (ricevute della società)

I tornei sono salvati dai cataloghi; solo i PDF effettivamente elencati nel riepilogo partecipazioni vengono
scaricati, senza autenticazione/cookie e senza aprire un dettaglio per ogni torneo. Un PDF per avanzamento API.
File in `wp-content/uploads/fitet-ricevute/ricevute-pdf/`. Angular deve restare aperto per proseguire gli avanzamenti.
Prezzi manuali e stato dei rimborsi sono conservati. Le quote dei singolari omogenei si inferiscono dal totale;
le ricevute miste o con divisione non esatta richiedono prezzi manuali. I doppi sono per coppia.

I cataloghi danno i tornei, non l'elenco completo delle gare offerte: vengono mostrate solo le gare note
dalle ricevute, senza inventare gare per i tornei con zero iscrizioni. Il formato di un PDF reale con doppi
resta da verificare; le iscrizioni a squadre non vuote richiedono un parser dedicato.
L'import/export Excel resta da implementare.

## Installazione e configurazione

WordPress 6.5+, PHP 8.1+ (DOM, mbstring, iconv, zlib, OpenSSL), MySQL/MariaDB supportato da WordPress.
Installazione singolo sito; HTTPS in produzione. Caricare lo ZIP installabile della cartella `release/`.
Le tabelle e la colonna note della bozza vengono aggiornate automaticamente, conservando i dati esistenti.

In **wp-admin → FITET Ricevute → Configurazione** impostare società e codice, account del portale e credenziali
extranet. Gli account del portale sono separati dagli utenti WordPress. Le credenziali FITeT sono cifrate
sul backend e non esposte nel frontend. Le preferenze del browser non contengono password o token.
Un unico pulsante **Salva configurazione** salva impostazioni, credenziali, firma e timbro.
Se un’immagine non è valida, nessuna parte della configurazione viene salvata.
I campi file vuoti conservano le immagini; la password vuota conserva quella esistente.
L’interfaccia wp-admin è responsive. La disinstallazione conserva dati e registro, rimuovendo sessioni e segreti.

## Sorgenti e build

`app/src/app/portal.service.ts`: cart, registro e stagione; `entity-grid.component.ts`: tabelle e azioni comuni;
`portal-pages.component.ts`: elenchi/dettagli; `cart.component.ts`: revisione/emissione.
`includes/class-refund-workflow.php`: bozza e registro; `docs/schema.sql`: DDL.
I vecchi componenti di pagina restano nei sorgenti come riferimento, ma non sono collegati alle nuove rotte.

```sh
cd app
npm ci
npm run build
npm run package
```

La build scrive in `assets/app/`; gli ZIP installabile e sorgenti sono in `release/`.
L'API è sul namespace `fitet-ricevute/v1`; le richieste operative usano cookie di sessione e token CSRF.
Gli endpoint operativi sono `GET/POST/DELETE /carrello`, `PATCH /carrello/{entry_id}`,
`GET/POST /emissioni` e `GET /emissioni/{id}`. Il nome tecnico `/carrello` resta compatibile.

## Test isolati

`python tests/prepare-runtime.py C:/percorso/core-wordpress` prepara solo `tests/.runtime/wordpress`
con il driver ufficiale SQLite per test. Non copia la configurazione o i dati del sito principale.
`php tests/data.php`, `php tests/workflow.php` e `php tests/refunds.php` verificano backend e transazioni.
Per l’interfaccia corrente: generare le immagini con `python tests/signature-fixtures.py`, avviare
il server con `tests/router.php`, eseguire `php tests/admin-ui-fixture.php`, poi `node tests/admin-ui.cjs`.
Al termine `php tests/admin-ui-fixture.php clean` ripristina i dati fittizi.
`php tests/admin-backup.php` verifica salvataggio unico, note e backup/ripristino completo.
Le suite browser `portal.cjs` e `signature.cjs` descrivono i flussi precedenti alla 0.8.1;
la suite corrente per configurazione, backup, bozza e stampa è `admin-ui.cjs`.
`node tests/layout.cjs` verifica intestazione, stagioni, profilo e menu responsive; usa
`portal-fixture.php` per preparare/ripristinare le stagioni fittizie.
`FR_PLAYWRIGHT_MODULE` e `FR_BROWSER_PATH` possono indicare Playwright e Chrome locali.
Nessuna credenziale reale viene salvata nei sorgenti o negli archivi distribuiti.

## Firma e timbro sulle ricevute

In **wp-admin → FITET Ricevute → Configurazione** caricare firma e timbro PNG o JPG,
massimo 1 MB e 4096 × 4096 pixel ciascuno. PNG conserva lo sfondo trasparente.
Anteprima, sostituzione e rimozione usano lo stesso pulsante **Salva configurazione**.
Le immagini vengono adattate senza deformazioni nelle ricevute cumulative e individuali.
Senza firma rimane lo spazio per quella manuale; senza timbro non viene disegnata alcuna immagine.

Le immagini sono opzioni private senza autoload, non file nella libreria media pubblica.
Le API `/firma` e `/timbro` richiedono autenticazione; i form admin richiedono permessi e nonce.
Ogni emissione conserva le immagini canoniche presenti sul server al momento dell’emissione:
sostituzioni o rimozioni valgono per le emissioni future. Le emissioni precedenti senza immagini
rimangono senza immagini. Il registro trasferisce i riferimenti; il dettaglio legge lo snapshot completo.

## Backup completo

**wp-admin → FITET Ricevute → Importa / esporta dati** esporta un archivio `.json.gz` con tornei/gare,
iscrizioni, prezzi, stati di rimborso, bozze con note, emissioni, impostazioni, firma, timbro,
date di ultima sincronizzazione e PDF originali archiviati. I PDF mancanti vengono segnalati.
Le identità degli account del portale sono incluse per attribuire bozze ed emissioni.
Password, credenziali extranet e sessioni sono escluse. Le preferenze del browser non sono nel backup.

L’importazione verifica il file e mostra un’anteprima. Solo la conferma **Ripristina backup** sostituisce
i dati del plugin e le relative impostazioni. Non modifica altri dati WordPress e conserva password
e credenziali extranet del sito di destinazione. Gli account si associano per username; quelli mancanti
vengono creati disabilitati con password casuale, da impostare prima dell’abilitazione.
Il ripristino termina le sessioni del portale e invalida le sincronizzazioni già avviate:
non utilizzare il portale mentre è in corso.

Limiti attuali: archivio compresso 16 MB (o limite upload PHP inferiore), contenuto non compresso 64 MB,
100.000 righe totali, PDF originali 32 MB complessivi e 3 MB per file.
Il backup non è un file Excel: import/export Excel rimane una funzione distinta da implementare.
