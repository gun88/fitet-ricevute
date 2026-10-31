<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['HTTP_HOST'] = '127.0.0.1:8095'; $_SERVER['REQUEST_URI'] = '/';
require __DIR__ . '/.runtime/wordpress/wp-load.php';
$checks = 0;
function check_refund(bool $value, string $message): void {
    global $checks;
    if (!$value) throw new RuntimeException($message);
    echo "PASS $message\n"; $checks++;
}
$repo = new FR_Data_Repository();
$original_config = FR_Settings::all();
$rows = $repo->listing('iscrizioni')['items'];
$ids = array_map(fn($row) => (int) $row['id'], $rows);
$races = $repo->listing('gare')['items'];
$race_id = (int) $races[0]['id'];
$price_table = FR_Data_Repository::table('prezzi');
try {
    foreach ($races as $race) $repo->set_manual_price((int) $race['id'], null, 1);
    $repo->set_reimbursed($ids, false, 1);
    $summary = $repo->listing('gare')['items'];
    check_refund(count($summary) === 2 && $summary[0]['reconciliation'] === 'match' && $summary[0]['calculated_total_cents'] === 9000, 'reconciliation counts receipt once across both races');
    $repo->set_manual_price($race_id, 1700, 1);
    $summary = $repo->listing('gare')['items'];
    $count = (int) $races[0]['entry_count'];
    check_refund($summary[0]['price_origin'] === 'manual' && $summary[0]['difference_cents'] === $count * 200 && $summary[1]['reconciliation'] === 'mismatch', 'manual edit updates receipt-wide difference on every race');
    $repo->set_manual_price($race_id, 0, 1);
    check_refund($repo->listing('gare')['items'][0]['difference_cents'] === -$count * 1500, 'manual zero participates in reconciliation');
    $repo->set_manual_price($race_id, null, 1);
    check_refund($repo->listing('gare')['items'][0]['price_origin'] === 'calculated', 'price origin reverts after clearing manual price');
    $id = $ids[0];
    $paid = $repo->set_reimbursed([$id], true, 1)['items'][0];
    check_refund((int) $paid['reimbursed'] === 1 && (int) $paid['paid_cents'] === 1500, 'marking paid snapshots the actual effective amount');
    $repo->set_manual_price((int) $rows[0]['race_id'], 1600, 1);
    $again = $repo->set_reimbursed([$id], true, 2)['items'][0];
    check_refund((int) $again['paid_cents'] === 1500 && (int) $again['price_cents'] === 1600, 'repeated paid marking never overwrites the original paid amount');
    $source = FR_Receipt_Source::identify('http://portale.fitet.org/fpdf2/iscrizioni_gara_societa_reg.php?TORNEO=8&ANNO=41&COMITATO=4&SOCIETA=718');
    $pdf = __DIR__ . '/.runtime/ricevuta-esempio.pdf';
    $repo->import($source, (new FR_Receipt_Parser())->parse($pdf), 'fixture.pdf', hash_file('sha256', $pdf));
    check_refund((int) $repo->refund_preview([$id])['items'][0]['paid_cents'] === 1500, 'source resynchronization preserves operational paid status');
    $repo->set_reimbursed([$id], false, 1);
    $open = $repo->refund_preview([$id])['items'][0];
    check_refund((int) $open['reimbursed'] === 0 && $open['paid_cents'] === null, 'paid status can explicitly be reopened');
    foreach ($races as $race) $repo->set_manual_price((int) $race['id'], null, 1);
    $wpdb->update($price_table, ['automatic_cents' => null], ['race_id' => $race_id]);
    $summary = $repo->listing('gare')['items'];
    check_refund($summary[0]['reconciliation'] === 'incomplete' && $summary[0]['difference_cents'] === null && $summary[0]['calculated_total_cents'] === null, 'missing prices are incomplete rather than zero or matching');
    try { $repo->set_reimbursed($ids, true, 1); check_refund(false, 'atomic bulk update'); }
    catch (RuntimeException $error) {
        check_refund(!array_filter($repo->refund_preview($ids)['items'], fn($row) => (bool) $row['reimbursed']), 'bulk refund update rolls back if any amount is undefined');
    }
    try { $repo->refund_preview([$id, 99999999]); check_refund(false, 'missing selection'); }
    catch (RuntimeException $error) { check_refund(true, 'stale or nonexistent selection is rejected in full'); }
    $config = $original_config; $config['society_code'] = '999'; update_option(FR_Settings::OPTION, $config);
    try { $repo->set_reimbursed([$id], true, 1); check_refund(false, 'society isolation'); }
    catch (RuntimeException $error) { check_refund(true, 'refund mutation cannot access another society'); }
} finally {
    update_option(FR_Settings::OPTION, $original_config);
    $wpdb->update($price_table, ['automatic_cents' => 1500], ['race_id' => $race_id]);
    foreach ($races as $race) $repo->set_manual_price((int) $race['id'], null, 1);
    $repo->set_reimbursed($ids, false, 1);
}
echo "$checks refund/reconciliation checks passed.\n";
