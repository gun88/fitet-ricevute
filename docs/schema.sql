-- DDL proposta. {prefix} è il prefisso WordPress; {charset} è il charset/collation del sito.
-- Una riga in gare rappresenta una gara del torneo (singolare o doppio).
-- Torneo con PDF vuoto: una riga segnaposto race_kind=empty, senza iscrizioni né prezzo.
-- Anche il catalogo usa race_kind=empty, con pdf_file/pdf_sha256/source_year vuoti: nessun PDF o ANNO ipotizzato.
-- Il segnaposto viene disattivato quando compaiono gare reali; i prezzi storici restano conservati.
CREATE TABLE {prefix}fitet_ricevute_gare (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    tournament_key char(64) NOT NULL,
    tournament_ref text NOT NULL,
    race_key char(64) NOT NULL,
    society_code varchar(20) NOT NULL,
    source_year varchar(20) NOT NULL,
    source_committee varchar(20) NOT NULL,
    source_tournament varchar(20) NOT NULL,
    source_query longtext NOT NULL,
    source_url text NOT NULL,
    tournament_name text NOT NULL,
    tournament_date date NOT NULL,
    tournament_place varchar(255) NOT NULL,
    committee_name varchar(255) NOT NULL,
    society_name varchar(255) NOT NULL,
    race_name text NOT NULL,
    race_kind varchar(10) NOT NULL,
    receipt_total_cents bigint(20) unsigned NOT NULL,
    receipt_entries int(11) unsigned NOT NULL,
    pdf_file varchar(255) NOT NULL,
    pdf_sha256 char(64) NOT NULL,
    source_json longtext NOT NULL,
    active tinyint(1) NOT NULL DEFAULT 1,
    synced_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY source_race (society_code,tournament_key,race_key),
    KEY tournament (tournament_key),
    KEY listing (society_code,active,tournament_date)
) ENGINE=InnoDB {charset};

-- Una riga = una iscrizione originale. Nei doppi è una coppia, non due quote.
CREATE TABLE {prefix}fitet_ricevute_iscrizioni (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    race_id bigint(20) unsigned NOT NULL,
    entry_key char(64) NOT NULL,
    athlete1_card varchar(32) DEFAULT NULL,
    athlete1_name varchar(255) NOT NULL,
    athlete1_birth_date date DEFAULT NULL,
    athlete1_category varchar(20) DEFAULT NULL,
    athlete1_sector varchar(20) DEFAULT NULL,
    athlete2_card varchar(32) DEFAULT NULL,
    athlete2_name varchar(255) DEFAULT NULL,
    athlete2_birth_date date DEFAULT NULL,
    athlete2_category varchar(20) DEFAULT NULL,
    athlete2_sector varchar(20) DEFAULT NULL,
    source_page int(11) unsigned NOT NULL,
    source_row int(11) unsigned NOT NULL,
    source_json longtext NOT NULL,
    active tinyint(1) NOT NULL DEFAULT 1,
    synced_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY source_entry (race_id,entry_key),
    KEY listing (race_id,active),
    KEY athlete1 (athlete1_card),
    KEY athlete2 (athlete2_card)
) ENGINE=InnoDB {charset};

-- Solo questa tabella contiene prezzi. Una sincronizzazione aggiorna esclusivamente automatic_cents/auto_basis.
CREATE TABLE {prefix}fitet_ricevute_prezzi (
    race_id bigint(20) unsigned NOT NULL,
    automatic_cents bigint(20) unsigned DEFAULT NULL,
    manual_cents bigint(20) unsigned DEFAULT NULL,
    auto_basis varchar(40) NOT NULL,
    auto_updated_at datetime NOT NULL,
    manual_updated_by bigint(20) unsigned DEFAULT NULL,
    manual_updated_at datetime DEFAULT NULL,
    PRIMARY KEY  (race_id)
) ENGINE=InnoDB {charset};

-- Relazioni logiche (gestite dal repository per compatibilità dbDelta):
-- Stato operativo separato dalle iscrizioni originali. Una quota di doppio è per coppia.
CREATE TABLE {prefix}fitet_ricevute_rimborsi (
    entry_id bigint(20) unsigned NOT NULL,
    reimbursed tinyint(1) NOT NULL DEFAULT 0,
    paid_cents bigint(20) unsigned DEFAULT NULL,
    updated_at datetime NOT NULL,
    updated_by bigint(20) unsigned NOT NULL,
    PRIMARY KEY  (entry_id)
) ENGINE=InnoDB {charset};

-- rimborsi.entry_id -> iscrizioni.id; rimborsi.updated_by -> users.id.
-- paid_cents conserva la quota al momento della marcatura, anche se i prezzi cambiano.
-- iscrizioni.race_id -> gare.id; prezzi.race_id -> gare.id.
-- Prezzo effettivo: COALESCE(prezzi.manual_cents, prezzi.automatic_cents).
-- receipt_total_cents è il totale della ricevuta, ripetuto sulle sue gare: NON sommarlo per gara.
