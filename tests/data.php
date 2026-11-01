<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['HTTP_HOST'] = '127.0.0.1:8095'; $_SERVER['REQUEST_URI'] = '/';
require __DIR__ . '/.runtime/wordpress/wp-load.php';
$checks = 0;
function check_data(bool $value, string $message): void {
    global $checks;
    if (!$value) { throw new RuntimeException($message); }
    echo 'PASS ' . $message . "\n"; $checks++;
}
$url = 'http://portale.fitet.org/fpdf2/iscrizioni_gara_societa_reg.php?TORNEO=8&ANNO=41&COMITATO=4&SOCIETA=718';
$source = FR_Receipt_Source::identify($url);
$equivalent = FR_Receipt_Source::identify('https://portale.fitet.org/fpdf2/iscrizioni_gara_societa_reg.php?SOCIETA=999&COMITATO=4&ANNO=41&TORNEO=8');
check_data($source['tournament_key'] === $equivalent['tournament_key'] && $source['tournament_ref'] === 'ANNO=41&COMITATO=4&TORNEO=8', 'tournament ID ignores society and query ordering');
check_data(FR_Receipt_Source::identify($url . '&TIPO=1')['tournament_key'] !== $source['tournament_key'], 'additional source parameters are part of identity');
try { FR_Receipt_Source::identify(str_replace('portale.fitet.org', '127.0.0.1', $url)); check_data(false, 'SSRF restriction'); }
catch (RuntimeException $e) { check_data(true, 'receipt downloader rejects foreign hosts'); }
$parser = new FR_Receipt_Parser();
$pdf = __DIR__ . '/.runtime/ricevuta-esempio.pdf';
$receipt = $parser->parse($pdf);
check_data(count($receipt['entries']) === 6 && $receipt['total_cents'] === 9000, 'live example parses six entries and 90 euro');
check_data($receipt['entries'][3]['athletes'][0]['card'] === '22210' && $receipt['entries'][4]['athletes'][0]['card'] === '1003156', 'overlapping race description does not corrupt athlete IDs');
check_data(FR_Receipt_Parser::inferred_price($receipt)['cents'] === 1500, 'single-only price is total divided by entries');
$mixed = $receipt;
$mixed['entries'][0]['race_kind'] = 'double';
check_data(FR_Receipt_Parser::inferred_price($mixed)['cents'] === null, 'mixed singles/doubles do not invent a price');
$double = $receipt; foreach ($double['entries'] as &$item) { $item['race_kind'] = 'double'; } unset($item);
check_data(FR_Receipt_Parser::inferred_price($double)['cents'] === 1500, 'double-only price divides by pair registrations');
$fraction = $receipt; $fraction['total_cents'] = 9001;
check_data(FR_Receipt_Parser::inferred_price($fraction)['cents'] === null, 'nondivisible totals are flagged instead of rounded');
$config = FR_Settings::all(); $config['society_code'] = '718'; update_option(FR_Settings::OPTION, $config);
$repo = new FR_Data_Repository();
$filename = $source['tournament_key'] . '-718-' . hash_file('sha256', $pdf) . '.pdf';
$repo->import($source, $receipt, $filename, hash_file('sha256', $pdf));
$races = $repo->listing('gare'); $entries = $repo->listing('iscrizioni');
check_data($races['total'] === 2 && $entries['total'] === 6, 'three-table model stores two races and six entries');
$race_id = (int) $races['items'][0]['id'];
$repo->set_manual_price($race_id, 1234, 1);
$repo->import($source, $receipt, $filename, hash_file('sha256', $pdf));
$races = $repo->listing('gare');
$race = array_values(array_filter($races['items'], fn($r) => (int) $r['id'] === $race_id))[0];
check_data((int) $race['manual_cents'] === 1234 && (int) $race['price_cents'] === 1234, 'resync preserves manual price and JOIN precedence');
check_data($repo->listing('iscrizioni')['total'] === 6, 'resync is idempotent and creates no duplicate entries');
$repo->set_manual_price($race_id, 0, 1); $repo->import($source, $receipt, $filename, hash_file('sha256', $pdf));
$races = $repo->listing('gare'); $race = array_values(array_filter($races['items'], fn($r) => (int) $r['id'] === $race_id))[0];
check_data((int) $race['price_cents'] === 0 && $race['manual_cents'] !== null, 'manual zero remains an override');
$changed = $receipt; array_pop($changed['entries']); $changed['total_cents'] = 7500;
$repo->import($source, $changed, $filename, hash_file('sha256', $pdf));
check_data($repo->listing('iscrizioni')['total'] === 5, 'removed source entries become inactive');
$repo->import($source, $receipt, $filename, hash_file('sha256', $pdf));
check_data($repo->listing('iscrizioni')['total'] === 6, 'returning source entries reuse their original identity');
$repo->set_manual_price($race_id, null, 1);
$races = $repo->listing('gare'); $race = array_values(array_filter($races['items'], fn($r) => (int) $r['id'] === $race_id))[0];
check_data($race['manual_cents'] === null && (int) $race['price_cents'] === 1500, 'clearing manual price restores automatic value');
check_data($repo->listing('gare', 1, 'no-matching-tournament')['total'] === 0, 'joined listing supports filtering');
$bad = $receipt; $bad['metadata']['tournament_name'] = null;
$wpdb->suppress_errors(true);
try { $repo->import($source, $bad, $filename, hash_file('sha256', $pdf)); check_data(false, 'rollback'); }
catch (Throwable $error) { check_data($repo->listing('iscrizioni')['total'] === 6, 'failed import rolls back authoritative source changes'); }
$wpdb->suppress_errors(false);
FR_Extranet_Credentials::save(['username' => 'Societa.Fixture', 'password' => 'Fixture-FITeT-2026!']);
update_option('fr_test_mock_extranet', true, false); update_option('fr_test_http_trace', [], false); delete_option('fr_test_bad_pdf');
$sync = new FR_Sync_Service($repo); $job = $sync->start(1);
check_data($job['total'] === 1, 'catalog discovery deduplicates observed tournament links');
$trace = get_option('fr_test_http_trace');
check_data(!str_contains(serialize($trace), 'elimina_iscrizione'), 'discovery never follows observed destructive links');
try { $sync->step($job['id'], 2); check_data(false, 'job ownership'); }
catch (RuntimeException $e) { check_data(true, 'sync job is restricted to its creating user'); }
$job = $sync->step($job['id'], 1);while(!$job['done'])$job=$sync->step($job['id'],1);
check_data($job['done'] && $job['imported'] === 1 && !$job['errors'], 'automatic backend login, discovery, download, parsing and persistence complete with fixture');
$uploads = wp_upload_dir();
check_data(is_file($uploads['basedir'] . '/fitet-ricevute/ricevute-pdf/' . $filename), 'PDF is saved under the requested uploads directory');
update_option('fr_test_bad_pdf', true, false);
try { $sync->download_import($url, '718'); check_data(false, 'HTML response rejection'); }
catch (RuntimeException $e) { check_data($repo->listing('iscrizioni')['total'] === 6, 'HTML login/error responses cannot replace imported data'); }
delete_option('fr_test_bad_pdf');
echo "$checks data/synchronization checks passed.\n";
