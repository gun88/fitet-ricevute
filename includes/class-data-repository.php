<?php

defined('ABSPATH') || exit;

final class FR_Data_Repository {
    public static function table(string $name): string {
        global $wpdb;
        if (!in_array($name, ['gare', 'iscrizioni', 'prezzi', 'rimborsi'], true)) { throw new InvalidArgumentException('Tabella non valida.'); }
        return $wpdb->prefix . 'fitet_ricevute_' . $name;
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = file_get_contents(FITET_RICEVUTE_DIR . 'docs/schema.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        dbDelta(str_replace(['{prefix}', '{charset}'], [$wpdb->prefix, $wpdb->get_charset_collate()], $sql));
    }

    private function check($result): void {
        if ($result === false) { throw new RuntimeException('Errore database: nessuna modifica parziale salvata.'); }
    }

    /** Persist observed catalog metadata without fetching or inventing a receipt. */
    public function import_catalog(array $tournaments, string $society): void {
        global $wpdb;
        $gare = self::table('gare'); $prices = self::table('prezzi'); $now = current_time('mysql', true);
        $this->check($wpdb->query('START TRANSACTION'));
        try {
            foreach ($tournaments as $t) {
                // A catalog never replaces an existing authoritative receipt snapshot.
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $gare WHERE society_code=%s AND source_committee=%s AND source_tournament=%s AND tournament_date=%s AND active=1 AND pdf_file<>'' LIMIT 1", $society,$t['committee'],$t['number'],$t['date']));
                if ($existing) { continue; }
                $record = [
                    'tournament_key'=>$t['key'], 'tournament_ref'=>'Catalogo: '.$t['url'], 'race_key'=>hash('sha256','empty-tournament'),
                    'society_code'=>$society, 'source_year'=>'', 'source_committee'=>$t['committee'], 'source_tournament'=>$t['number'],
                    'source_query'=>wp_json_encode(['COMITATO'=>$t['committee'],'TORNEO'=>$t['number']]), 'source_url'=>$t['url'],
                    'tournament_name'=>$t['name'], 'tournament_date'=>$t['date'], 'tournament_place'=>$t['place'],
                    'committee_name'=>'', 'society_name'=>'', 'race_name'=>'Nessuna iscrizione', 'race_kind'=>'empty',
                    'receipt_total_cents'=>0, 'receipt_entries'=>0, 'pdf_file'=>'', 'pdf_sha256'=>'',
                    'source_json'=>wp_json_encode(['origin'=>'catalog','data'=>$t]), 'active'=>1, 'synced_at'=>$now,
                ];
                $id=$wpdb->get_var($wpdb->prepare("SELECT id FROM $gare WHERE society_code=%s AND tournament_key=%s AND pdf_file='' LIMIT 1",$society,$t['key']));
                if ($id) { $this->check($wpdb->update($gare,$record,['id'=>(int)$id])); }
                else {
                    $this->check($wpdb->insert($gare,$record)); $id=(int)$wpdb->insert_id;
                    $this->check($wpdb->insert($prices,['race_id'=>$id,'automatic_cents'=>null,'auto_basis'=>'catalog','auto_updated_at'=>$now]));
                }
            }
            $this->check($wpdb->query('COMMIT'));
        } catch (Throwable $error) { $wpdb->query('ROLLBACK'); throw $error; }
    }

    /** Atomic receipt snapshot; manual prices are never part of any sync UPDATE. */
    public function import(array $source, array $receipt, string $pdf_file, string $sha): array {
        global $wpdb;
        $gare = self::table('gare'); $entries = self::table('iscrizioni'); $prices = self::table('prezzi');
        if ($receipt['metadata']['society_code'] !== $source['society_code']) { throw new RuntimeException('Società nel PDF diversa da quella richiesta.'); }
        $now = current_time('mysql', true);
        $price = FR_Receipt_Parser::inferred_price($receipt);
        $this->check($wpdb->query('START TRANSACTION'));
        try {
            // Join a receipt to its catalog entry using observed committee, ID and date, never a guessed ANNO.
            $this->check($wpdb->query($wpdb->prepare("UPDATE $gare SET active=0 WHERE society_code=%s AND source_committee=%s AND source_tournament=%s AND tournament_date=%s AND pdf_file=''", $source['society_code'],$source['params']['COMITATO']??'',$source['params']['TORNEO'],$receipt['metadata']['tournament_date'])));
            $old = $wpdb->get_col($wpdb->prepare("SELECT id FROM $gare WHERE tournament_key = %s AND society_code = %s", $source['tournament_key'], $source['society_code']));
            foreach ($old as $race_id) {
                $this->check($wpdb->update($entries, ['active' => 0], ['race_id' => (int) $race_id]));
            }
            $this->check($wpdb->update($gare, ['active' => 0], ['tournament_key' => $source['tournament_key'], 'society_code' => $source['society_code']]));
            $races = [];
            $source_rows = $receipt['entries'] ?: [['race_key' => hash('sha256', 'empty-tournament'), 'race_name' => 'Nessuna iscrizione', 'race_kind' => 'empty']];
            foreach ($source_rows as $entry) {
                if (!isset($races[$entry['race_key']])) {
                    $record = array_merge($receipt['metadata'], [
                        'tournament_key' => $source['tournament_key'], 'tournament_ref' => $source['tournament_ref'],
                        'race_key' => $entry['race_key'], 'race_name' => $entry['race_name'], 'race_kind' => $entry['race_kind'],
                        'source_year' => $source['params']['ANNO'], 'source_committee' => $source['params']['COMITATO'] ?? '',
                        'source_tournament' => $source['params']['TORNEO'], 'source_query' => wp_json_encode($source['params']),
                        'source_url' => $source['url'], 'receipt_total_cents' => $receipt['total_cents'],
                        'receipt_entries' => count($receipt['entries']), 'pdf_file' => $pdf_file, 'pdf_sha256' => $sha,
                        'source_json' => wp_json_encode($receipt['metadata']), 'active' => 1, 'synced_at' => $now,
                    ]);
                    $race_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $gare WHERE society_code = %s AND tournament_key = %s AND race_key = %s", $source['society_code'], $source['tournament_key'], $entry['race_key']));
                    if ($race_id) { $this->check($wpdb->update($gare, $record, ['id' => (int) $race_id])); }
                    else { $this->check($wpdb->insert($gare, $record)); $race_id = $wpdb->insert_id; }
                    $race_id = (int) $race_id;
                    $races[$entry['race_key']] = $race_id;
                    $auto = ['automatic_cents' => $price['cents'], 'auto_basis' => $price['basis'], 'auto_updated_at' => $now];
                    if ($wpdb->get_var($wpdb->prepare("SELECT race_id FROM $prices WHERE race_id = %d", $race_id))) {
                        $this->check($wpdb->update($prices, $auto, ['race_id' => $race_id]));
                    } else { $this->check($wpdb->insert($prices, array_merge(['race_id' => $race_id], $auto))); }
                }
                if ($entry['race_kind'] === 'empty') { continue; }
                $race_id = $races[$entry['race_key']];
                $record = ['race_id' => $race_id, 'entry_key' => $entry['entry_key'], 'active' => 1, 'synced_at' => $now,
                    'source_page' => $entry['source_page'], 'source_row' => $entry['source_row'], 'source_json' => wp_json_encode($entry['cells'])];
                foreach ([1, 2] as $i) {
                    $athlete = $entry['athletes'][$i - 1] ?? [];
                    foreach (['card', 'name', 'birth_date', 'category', 'sector'] as $key) { $record['athlete' . $i . '_' . $key] = $athlete[$key] ?? null; }
                }
                $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $entries WHERE race_id = %d AND entry_key = %s", $race_id, $entry['entry_key']));
                $this->check($id ? $wpdb->update($entries, $record, ['id' => (int) $id]) : $wpdb->insert($entries, $record));
            }
            $this->check($wpdb->query('COMMIT'));
            return ['races' => count($races), 'entries' => count($receipt['entries']), 'auto_basis' => $price['basis']];
        } catch (Throwable $error) { $wpdb->query('ROLLBACK'); throw $error; }
    }

    private function scope(string $search): array {
        global $wpdb;
        $where = 'g.active = 1'; $params = [];
        $society = (string) FR_Settings::all()['society_code'];
        if ($society !== '') { $where .= ' AND g.society_code = %s'; $params[] = ltrim($society, '0') ?: '0'; }
        if ($search !== '') {
            $where .= ' AND (g.tournament_name LIKE %s OR g.race_name LIKE %s OR g.tournament_place LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%'; array_push($params, $like, $like, $like);
        }
        return [$where, $params];
    }

    public function listing(string $kind, int $page = 1, string $search = ''): array {
        global $wpdb;
        $gare = self::table('gare'); $entries = self::table('iscrizioni'); $prices = self::table('prezzi'); $refunds = self::table('rimborsi');
        [$where, $params] = $this->scope($search);
        $price_columns = 'p.automatic_cents, p.manual_cents, p.auto_basis, COALESCE(p.manual_cents,p.automatic_cents) AS price_cents';
        if ($kind === 'gare') {
            $from = "$gare g LEFT JOIN $prices p ON p.race_id = g.id";
            $select = "g.id, g.society_code, g.source_committee, g.committee_name, g.tournament_key, g.tournament_ref, g.tournament_name, g.tournament_date, g.tournament_place, g.race_name, g.race_kind, g.receipt_total_cents, g.receipt_entries, g.synced_at, (g.pdf_file<>'') AS has_receipt, $price_columns, (SELECT COUNT(*) FROM $entries e WHERE e.race_id = g.id AND e.active = 1) AS entry_count";
            $order = 'g.tournament_date DESC, g.id DESC';
        } else {
            $from = "$entries e JOIN $gare g ON e.race_id = g.id LEFT JOIN $prices p ON p.race_id = e.race_id";
            $where .= ' AND e.active = 1';
            $from .= " LEFT JOIN $refunds r ON r.entry_id = e.id";
            $select = "e.id, e.race_id, e.athlete1_card, e.athlete1_name, e.athlete1_birth_date, e.athlete1_category, e.athlete1_sector, e.athlete2_card, e.athlete2_name, g.society_code, g.source_committee, g.committee_name, g.tournament_key, g.tournament_name, g.tournament_date, g.tournament_place, g.race_name, g.race_kind, COALESCE(r.reimbursed,0) AS reimbursed, r.paid_cents, r.updated_at AS refund_updated_at, $price_columns";
            $order = 'g.tournament_date DESC, g.id DESC, e.athlete1_name';
        }
        $sql = "SELECT $select FROM $from WHERE $where ORDER BY $order LIMIT %d OFFSET %d";
        $items = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [50, (max(1, $page) - 1) * 50])), ARRAY_A);
        $count_sql = "SELECT COUNT(*) FROM $from WHERE $where";
        $count = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);
        if ($wpdb->last_error) { throw new RuntimeException('Impossibile leggere i dati delle ricevute.'); }
        if ($kind === 'gare') {
            $totals = $this->receipt_totals();
            foreach ($items as &$row) {
                $summary = $totals[$row['tournament_key'] . ':' . $row['society_code']] ?? ['calculated_total_cents' => null, 'difference_cents' => null, 'reconciliation' => 'incomplete'];
                $row = array_merge($row, $summary);
                $row['price_origin'] = $row['manual_cents'] !== null ? 'manual' : ($row['automatic_cents'] !== null ? 'calculated' : 'undefined');
                $row['has_receipt'] = (bool) $row['has_receipt'];
                if (!$row['has_receipt']) {
                    $row['reconciliation']='not_available'; $row['calculated_total_cents']=null; $row['difference_cents']=null;
                }
            }
            unset($row);
        }
        foreach ($items as &$item) {
            $item['tournament_scope'] = self::tournament_scope($item['source_committee']);
            $item['committee_label'] = self::committee_label($item['source_committee']);
        }
        unset($item);
        return ['items' => $items, 'total' => $count, 'page' => max(1, $page), 'per_page' => 50];
    }

    public static function tournament_scope(string $committee): string {
        if ($committee === '') { return 'unknown'; }
        return (ltrim($committee, '0') ?: '0') === '30' ? 'national' : 'territorial';
    }

    public static function committee_label(string $committee): string {
        if ($committee === '') { return 'Non indicato'; }
        static $names = null;
        $names ??= require __DIR__ . '/committees.php';
        $code = ltrim($committee, '0') ?: '0';
        return $names[$code] ?? ('Comitato ' . $code);
    }

    /** Receipt totals are compared once per tournament, never added once per race. */
    private function receipt_totals(): array {
        global $wpdb;
        $g = self::table('gare'); $e = self::table('iscrizioni'); $p = self::table('prezzi');
        [$where, $params] = $this->scope('');
        $sql = "SELECT g.tournament_key, g.society_code, MAX(g.receipt_total_cents) AS original_total,
            SUM(CASE WHEN g.race_kind <> 'empty' AND COALESCE(p.manual_cents,p.automatic_cents) IS NULL THEN 1 ELSE 0 END) AS missing_prices,
            SUM(COALESCE(p.manual_cents,p.automatic_cents,0) * (SELECT COUNT(*) FROM $e e WHERE e.race_id = g.id AND e.active = 1)) AS calculated
            FROM $g g LEFT JOIN $p p ON p.race_id = g.id WHERE $where GROUP BY g.tournament_key,g.society_code";
        $rows = $wpdb->get_results($params ? $wpdb->prepare($sql, $params) : $sql, ARRAY_A);
        if ($wpdb->last_error) { throw new RuntimeException('Impossibile confrontare le quote con le ricevute.'); }
        $result = [];
        foreach ($rows as $row) {
            $incomplete = (int) $row['missing_prices'] > 0;
            $difference = $incomplete ? null : (int) $row['calculated'] - (int) $row['original_total'];
            $result[$row['tournament_key'] . ':' . $row['society_code']] = [
                'calculated_total_cents' => $incomplete ? null : (int) $row['calculated'],
                'difference_cents' => $difference,
                'reconciliation' => $incomplete ? 'incomplete' : ($difference === 0 ? 'match' : 'mismatch'),
            ];
        }
        return $result;
    }

    public function refund_preview(array $ids): array {
        global $wpdb;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids || count($ids) > 1000 || min($ids) < 1) { throw new RuntimeException('Seleziona da 1 a 1000 iscrizioni valide.'); }
        $g = self::table('gare'); $e = self::table('iscrizioni'); $p = self::table('prezzi'); $r = self::table('rimborsi');
        [$where, $params] = $this->scope('');
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "SELECT e.id, e.race_id, e.athlete1_name, e.athlete1_card, e.athlete2_name, e.athlete2_card,
            g.society_code,g.source_committee,g.committee_name,g.tournament_key,g.tournament_name,g.tournament_date,g.tournament_place,g.race_name,g.race_kind,
            COALESCE(p.manual_cents,p.automatic_cents) AS price_cents, COALESCE(r.reimbursed,0) AS reimbursed,
            r.paid_cents,r.updated_at AS refund_updated_at
            FROM $e e JOIN $g g ON g.id = e.race_id LEFT JOIN $p p ON p.race_id = g.id LEFT JOIN $r r ON r.entry_id = e.id
            WHERE $where AND e.active = 1 AND e.id IN ($placeholders) ORDER BY g.tournament_date,g.id,e.athlete1_name";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, $ids)), ARRAY_A);
        if ($wpdb->last_error || count($rows) !== count($ids)) { throw new RuntimeException('Una o più iscrizioni non sono più disponibili. Torna alla pagina Iscrizioni e aggiorna la selezione.'); }
        foreach ($rows as &$row) {
            $row['tournament_scope'] = self::tournament_scope($row['source_committee']);
            $row['committee_label'] = self::committee_label($row['source_committee']);
        }
        unset($row);
        return ['items' => $rows];
    }

    public function set_reimbursed(array $ids, bool $reimbursed, int $user_id): array {
        global $wpdb;
        $table = self::table('rimborsi');
        $this->check($wpdb->query('START TRANSACTION'));
        try {
            $rows = $this->refund_preview($ids)['items'];
            foreach ($rows as $row) {
                if ((bool) $row['reimbursed'] === $reimbursed) { continue; }
                if ($reimbursed && $row['price_cents'] === null) { throw new RuntimeException('Definisci tutte le quote prima di marcarle come rimborsate.'); }
                $record = ['entry_id' => (int) $row['id'], 'reimbursed' => (int) $reimbursed,
                    'paid_cents' => $reimbursed ? (int) $row['price_cents'] : null,
                    'updated_at' => current_time('mysql', true), 'updated_by' => $user_id];
                $exists = $wpdb->get_var($wpdb->prepare("SELECT entry_id FROM $table WHERE entry_id = %d", (int) $row['id']));
                $this->check($exists ? $wpdb->update($table, $record, ['entry_id' => (int) $row['id']]) : $wpdb->insert($table, $record));
            }
            $result = $this->refund_preview($ids);
            $this->check($wpdb->query('COMMIT'));
            return $result;
        } catch (Throwable $error) { $wpdb->query('ROLLBACK'); throw $error; }
    }

    public function set_manual_price(int $race_id, ?int $cents, int $user_id): void {
        global $wpdb;
        $gare = self::table('gare');
        $society = (string) FR_Settings::all()['society_code'];
        $race = $wpdb->get_row($wpdb->prepare("SELECT id, society_code FROM $gare WHERE id = %d AND active = 1 AND race_kind <> 'empty'", $race_id), ARRAY_A);
        if (!$race || ($society !== '' && $race['society_code'] !== (ltrim($society, '0') ?: '0'))) { throw new RuntimeException('Gara non trovata.'); }
        $this->check($wpdb->update(self::table('prezzi'), [
            'manual_cents' => $cents, 'manual_updated_by' => $user_id,
            'manual_updated_at' => current_time('mysql', true),
        ], ['race_id' => $race_id]));
    }
}
