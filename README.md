# FITET Ricevute

Plugin WordPress con frontend Angular su `/fitet-ricevute/`. Versione **0.7.1**.

## Pagine del portale

- **Tornei**: una riga per torneo, filtro “Mostra solo tornei con iscrizioni” inizialmente attivo;
  azioni Dettaglio, Gare e Iscrizioni. Le ultime due aprono gli elenchi con il torneo già selezionato.
- **Gare**: elenco delle gare note, filtro con iscrizioni inizialmente attivo, torneo parent cliccabile,
  modifica della quota via API, origine prezzo e confronto con il totale PDF; Dettaglio e Iscrizioni per gara.
  Lasciare vuota una quota rimuove il prezzo manuale e ripristina quello automatico.
- **Iscrizioni**: link al torneo, alla gara e agli atleti; stato della quota e del torneo, aggiunta singola
  o delle righe selezionate al carrello. Le quote già rimborsate non sono aggiungibili.
- **Rimborsi Emessi**: registro delle emissioni con data/ora server, importo, emittente e numero iscrizioni.
- **Carrello**: iscrizioni personali salvate nel database, eliminabili, recap per atleta, scelta PDF
  cumulativo, separato per atleta o entrambi. Il badge nel menu conta tutti gli elementi, anche di altre stagioni.

Le pagine dettaglio di torneo, gara, rimborso e giocatore riusano le stesse tabelle e azioni degli elenchi.
Il dettaglio giocatore mostra iscrizioni e rimborsi; è raggiungibile dai nomi degli atleti.
La directory giocatori resta raggiungibile su `#/giocatori`. Il vecchio URL `#/prezzi` reindirizza a Gare.

La select **Stagione agonistica** è unica per il frontend e salvata per sito/account nel browser.
Le stagioni vanno dal 1° luglio al 30 giugno; “Ultima disponibile” sceglie la più recente nei dati.
La stagione filtra elenchi, dettagli e carrello. Le emissioni conservano sempre il totale originale:
nel dettaglio l'elenco segue il filtro globale, mentre il PDF riproduce l'emissione completa.

AG Grid Community: testi selezionabili, filtri, ordinamento, ricerca e preferenze per tabella/utente/browser.
100 righe iniziali, con precedenza alle preferenze già salvate. Layout responsive e scorrimento interno.
La barra di sincronizzazione e la data dell'ultimo completamento riuscito restano nella pagina Tornei.

## Carrello ed emissione

Il carrello è distinto per utente del portale e società: resiste a logout, cambio browser e riavvio.
Contiene al massimo 1000 quote. Il prezzo è riletto dal server e non congelato dall'aggiunta al carrello.
Una quota rimossa dalla fonte resta visibile come “Da verificare” e può essere eliminata, ma non emessa.

**Emetti rimborsi** prepara prima i file sul client, quindi registra sul server, in transazione,
le iscrizioni/importi/impostazioni, le segna rimborsate e rimuove gli elementi dal proprio carrello.
La richiesta è protetta da sessione, CSRF, controllo società, confronto dei prezzi e chiave idempotente;
richieste concorrenti o ripetute non devono produrre due emissioni per lo stesso invio.
Con una stagione specifica vengono emesse solo le quote visibili; le altre restano nel carrello.

Gli importi del registro sono snapshot: modifiche ai prezzi o sincronizzazioni successive non li riscrivono.
Il dettaglio permette di riscaricare i PDF senza effettuare un'altra emissione. I PDF sono prodotti localmente
dal browser e non archiviati sul server; sono archiviati i dati necessari a rigenerarli.
Se il download è interrotto dopo la registrazione, il rimborso è comunque disponibile nel registro.

Le marcature “rimborsata” delle versioni precedenti sono conservate. Non vengono convertite in emissioni
con una data inventata: il nuovo registro parte dalle emissioni effettuate con la 0.7.0.
Le API precedenti di marcatura operativa restano compatibili, ma non creano documenti nel registro.

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
Le tabelle aggiunte con la 0.7.0 vengono create automaticamente all'aggiornamento, conservando i dati esistenti.

In **wp-admin → FITET Ricevute → Configurazione** impostare società e codice, account del portale e credenziali
extranet. Gli account del portale sono separati dagli utenti WordPress. Le credenziali FITeT sono cifrate
sul backend e non esposte nel frontend. Le preferenze del browser non contengono password o token.
L'interfaccia wp-admin è responsive. La disinstallazione conserva dati e registro, rimuovendo sessioni e segreti.

## Sorgenti e build

`app/src/app/portal.service.ts`: cart, registro e stagione; `entity-grid.component.ts`: tabelle e azioni comuni;
`portal-pages.component.ts`: elenchi/dettagli; `cart.component.ts`: revisione/emissione.
`includes/class-refund-workflow.php`: carrello e registro; `docs/schema.sql`: DDL.
I vecchi componenti di pagina restano nei sorgenti come riferimento, ma non sono collegati alle nuove rotte.

```sh
cd app
npm ci
npm run build
npm run package
```

La build scrive in `assets/app/`; gli ZIP installabile e sorgenti sono in `release/`.
L'API è sul namespace `fitet-ricevute/v1`; le richieste operative usano cookie di sessione e token CSRF.
I nuovi endpoint sono `GET/POST/DELETE /carrello`, `GET/POST /emissioni` e `GET /emissioni/{id}`.

## Test isolati

`python tests/prepare-runtime.py C:/percorso/core-wordpress` prepara solo `tests/.runtime/wordpress`
con il driver ufficiale SQLite per test. Non copia la configurazione o i dati del sito principale.
`php tests/data.php`, `php tests/workflow.php` e `php tests/refunds.php` verificano backend e transazioni.
Per il flusso browser: `php tests/portal-fixture.php`, avviare il server con `tests/router.php`,
poi `node tests/portal.cjs`; al termine `php tests/portal-fixture.php clean` ripristina i dati fittizi.
I test UI delle vecchie rotte precedenti alla 0.7.0 sono storici; la suite corrente è `portal.cjs`.
`FR_PLAYWRIGHT_MODULE` e `FR_BROWSER_PATH` possono indicare Playwright e Chrome locali.
Nessuna credenziale reale viene salvata nei sorgenti o negli archivi distribuiti.

## Firma sulle ricevute (0.7.1)

In **wp-admin → FITET Ricevute → Configurazione → Firma nelle ricevute PDF** caricare un'immagine
PNG o JPG, massimo 1 MB e 4096 × 4096 pixel. PNG è consigliato per conservare lo sfondo trasparente.
Sono disponibili anteprima, sostituzione e rimozione; lasciare vuoto il file conserva la firma esistente.
L'immagine viene adattata senza deformazioni allo spazio sopra la riga della firma, nelle ricevute
cumulative e individuali. Senza immagine rimane lo spazio per la firma manuale.

La firma è in un'opzione privata senza autoload, non nella libreria media pubblica; l'API `/firma`
richiede autenticazione. I form admin richiedono permessi e nonce. Ogni nuova emissione conserva
la propria immagine; sostituzioni o rimozioni valgono per le emissioni future, non per le ristampe.
Le emissioni precedenti prive di firma rimangono senza firma. L'elenco del registro trasferisce solo
il riferimento della firma; i dati completi sono letti al momento del riscaricamento.
Una variazione della firma tra preparazione PDF e registrazione richiede di ripetere l'operazione.
