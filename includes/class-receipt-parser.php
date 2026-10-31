<?php

defined('ABSPATH') || exit;

final class FR_Receipt_Parser {
    private static function normalized(string $value): string {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    private static function date(string $value): string {
        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $value);
        if (!$date || $date->format('d/m/Y') !== $value) { throw new RuntimeException('Data PDF non valida.'); }
        return $date->format('Y-m-d');
    }

    public function parse(string $file): array {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) { throw new RuntimeException('Libreria PDF non disponibile.'); }
        if (!is_file($file) || filesize($file) > 3 * 1024 * 1024) { throw new RuntimeException('PDF mancante o superiore a 3 MB.'); }
        $config = new \Smalot\PdfParser\Config();
        $config->setDecodeMemoryLimit(12 * 1024 * 1024);
        $document = (new \Smalot\PdfParser\Parser([], $config))->parseFile($file);
        $pages = $document->getPages();
        if (count($pages) > 20) { throw new RuntimeException('La ricevuta supera 20 pagine.'); }
        $rows = [];
        foreach ($pages as $index => $page) {
            // Classic FPDF writes direct page streams. The vendor's FPDF position helper
            // assumes imported FPDI XObjects; bypass that assumption for direct streams.
            $content = $page->get('Contents');
            if ($page->isFpdf() && $content instanceof \Smalot\PdfParser\PDFObject && str_contains($content->getContent() ?? '', 'BT')) {
                $page = new class($document, $page->getHeader(), $page->getContent(), $page->getConfig()) extends \Smalot\PdfParser\Page {
                    public function isFpdf(): bool { return false; }
                };
            }
            $lines = [];
            foreach ($page->getDataTm() as [$matrix, $text]) {
                $text = self::normalized($text);
                if ($text === '') { continue; }
                $y = (int) round((float) $matrix[5] * 2);
                $lines[$y][] = ['x' => (float) $matrix[4], 'text' => $text];
            }
            krsort($lines, SORT_NUMERIC);
            foreach ($lines as $line) {
                usort($line, fn($a, $b) => $a['x'] <=> $b['x']);
                $rows[] = ['page' => $index + 1, 'cells' => array_column($line, 'text')];
            }
        }
        return $this->parse_rows($rows);
    }

    /** Public to verify alternate layouts using synthetic PDF cell fixtures. */
    public function parse_rows(array $rows): array {
        $metadata = ['committee_name' => ''];
        $entries = [];
        $section = null;
        $total = null;
        $receipt_headers = 0;
        foreach ($rows as $number => $row) {
            $cells = $row['cells'];
            $text = implode(' ', $cells);
            if (str_contains($text, 'Ricevuta delle iscrizioni')) { $receipt_headers++; }
            if (preg_match('/^Comitato (.+)$/u', $text, $m)) { $metadata['committee_name'] = $m[1]; }
            if (preg_match('/^(.+) del (\d{2}\/\d{2}\/\d{4}) a (.+)$/u', $text, $m)) {
                if (isset($metadata['tournament_name']) && $metadata['tournament_name'] !== $m[1]) { throw new RuntimeException('Il PDF contiene più ricevute.'); }
                $metadata += ['tournament_name' => $m[1], 'tournament_date' => self::date($m[2]), 'tournament_place' => $m[3]];
            }
            if (preg_match('/^per la Societ[aà] (.+) cod:\s*(\d+)$/u', $text, $m)) {
                $metadata['society_name'] = $m[1]; $metadata['society_code'] = ltrim($m[2], '0') ?: '0';
            }
            if (str_starts_with($text, 'Gare di singolare')) { $section = 'single'; continue; }
            if (str_starts_with($text, 'Gare di doppio')) { $section = 'double'; continue; }
            if (str_starts_with($text, 'Gare a squadre')) { $section = 'team'; continue; }
            if (preg_match('/TOTALE QUOTE ISCRIZIONI Euro\s+([\d.]+),(\d{2})/u', $text, $m)) {
                if ($total !== null) { throw new RuntimeException('Più totali ricevuta nel PDF.'); }
                $total = (int) str_replace('.', '', $m[1]) * 100 + (int) $m[2];
                $section = null; continue;
            }
            if (!$section || preg_match('/^Pagina \d+\/\d+$/', $text)) { continue; }
            if ($section === 'team') { throw new RuntimeException('Iscrizioni a squadre: formato da verificare.'); }
            if ($section === 'single') {
                $single = $cells;
                // Some re-saved PDFs combine adjacent cells into one text object.
                if (count($cells) !== 6 && preg_match('/^(.+?)\s+(\d+)\s*(\d{2}\/\d{2}\/\d{4})\s*(.+?)\s+([0-9A-Z])\s*([0-9A-Z])$/u', $text, $a)) {
                    $single = array_slice($a, 1);
                }
                if (count($single) !== 6 || !ctype_digit($single[1]) || !preg_match('/^\d{2}\/\d{2}\/\d{4}$/D', $single[2])) {
                    throw new RuntimeException('Riga di singolare non riconosciuta (pagina ' . $row['page'] . ', riga ' . ($number + 1) . '). Nessuna importazione parziale.');
                }
                $athletes = [['card' => $single[1], 'birth_date' => self::date($single[2]), 'name' => $single[3], 'category' => $single[4], 'sector' => $single[5]]];
                $race_name = $single[0];
            } else {
                if (count($cells) !== 3) { throw new RuntimeException('Formato delle coppie non riconosciuto. Serve verificare questa ricevuta di doppio.'); }
                $athletes = [];
                foreach (array_slice($cells, 1) as $athlete) {
                    if (preg_match('/^(\d+)\s*[-:]?\s+(.+)$/u', $athlete, $m)) { $athletes[] = ['card' => $m[1], 'name' => $m[2]]; }
                    else { $athletes[] = ['card' => null, 'name' => $athlete]; }
                }
                $race_name = $cells[0];
            }
            $race_key = hash('sha256', $section . ':' . mb_strtolower(self::normalized($race_name), 'UTF-8'));
            $identities = array_map(fn($a) => $a['card'] ?? ($a['name'] . ':' . ($a['birth_date'] ?? '')), $athletes);
            sort($identities, SORT_STRING);
            $entries[] = [
                'race_key' => $race_key, 'race_name' => $race_name, 'race_kind' => $section,
                'entry_key' => hash('sha256', implode('|', $identities)), 'athletes' => $athletes,
                'source_page' => $row['page'], 'source_row' => $number + 1, 'cells' => $cells,
            ];
        }
        if ($receipt_headers < 1 || count($metadata) < 6 || $total === null) { throw new RuntimeException('Ricevuta incompleta, raccolta di PDF o formato non riconosciuto.'); }
        if (!$entries && $total !== 0) { throw new RuntimeException('Totale non nullo senza iscrizioni leggibili.'); }
        $keys = array_map(fn($e) => $e['race_key'] . $e['entry_key'], $entries);
        if (count(array_unique($keys)) !== count($keys)) { throw new RuntimeException('Iscrizioni duplicate nel PDF: verifica richiesta.'); }
        return ['metadata' => $metadata, 'total_cents' => $total, 'entries' => $entries];
    }

    public static function inferred_price(array $receipt): array {
        $count = count($receipt['entries']);
        $kinds = array_unique(array_column($receipt['entries'], 'race_kind'));
        if ($count === 0) { return ['cents' => null, 'basis' => 'empty']; }
        if (count($kinds) !== 1) { return ['cents' => null, 'basis' => 'mixed']; }
        if ($receipt['total_cents'] % $count !== 0) { return ['cents' => null, 'basis' => 'not_divisible']; }
        return ['cents' => intdiv($receipt['total_cents'], $count), 'basis' => 'uniform_' . reset($kinds)];
    }
}
