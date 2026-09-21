<?php
declare(strict_types=1);

function sc_export_columns(): array
{
    return [
        'naziv', 'sifra', 'marka', 'neto_kolicina', 'jedinica_mjere',
        'cijena', 'posebna_prodaja', 'naziv_posebne_prodaje',
        'cijena_po_jedinici', 'valuta', 'barkod', 'kategorija',
        'sidrena_cijena', 'sidrena_cijena_datum', 'dostupnost',
    ];
}

function sc_export_amount($amount, string $label): string
{
    if (strpos(mb_strtolower($label), 'besplat') !== false) {
        return '0.00';
    }
    return is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : '';
}

function sc_export_rows(string $catalog): array
{
    $data = sc_catalog_load($catalog);
    $settings = sc_settings();
    $brand = trim((string) $settings['merchant_name']);
    $rows = [];
    foreach ($data['categories'] as $category) {
        foreach ($category['items'] as $item) {
            if (empty($item['active'])) {
                continue;
            }
            $price = sc_export_amount($item['price_amount'], (string) $item['price_label']);
            $anchor = sc_export_amount($item['anchor_amount'], (string) $item['anchor_label']);
            $sale = $price !== '' && $anchor !== '' && (float) $price < (float) $anchor;
            $rows[] = [
                'naziv' => (string) $item['name'],
                'sifra' => trim((string) $item['code']) ?: (string) $item['id'],
                'marka' => $brand,
                'neto_kolicina' => '1',
                'jedinica_mjere' => (string) $item['unit'],
                'cijena' => $price,
                'posebna_prodaja' => $sale ? 'da' : 'ne',
                'naziv_posebne_prodaje' => $sale ? 'akcijska prodaja' : '',
                'cijena_po_jedinici' => $price,
                'valuta' => (string) $data['currency'],
                'barkod' => '',
                'kategorija' => (string) $category['title'],
                'sidrena_cijena' => $anchor,
                'sidrena_cijena_datum' => $anchor === '' ? '' : (string) $item['anchor_date'],
                'dostupnost' => 'dostupno',
            ];
        }
    }
    return $rows;
}

function sc_export_xml(string $catalog): string
{
    $data = sc_catalog_load($catalog);
    $settings = sc_settings();
    $escape = static function ($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };
    $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
    $lines[] = sprintf(
        '<cjenik trgovac="%s" oib="%s" vrijeme="%s" sidrena_cijena_datum="%s">',
        $escape($settings['merchant_name']),
        $escape(preg_replace('/\D+/', '', (string) $settings['oib'])),
        $escape(date('c')),
        $escape($data['anchor_date'])
    );
    foreach (sc_export_rows($catalog) as $row) {
        $lines[] = '  <proizvod>';
        foreach (sc_export_columns() as $column) {
            $lines[] = sprintf('    <%s>%s</%s>', $column, $escape($row[$column] ?? ''), $column);
        }
        $lines[] = '  </proizvod>';
    }
    $lines[] = '</cjenik>';
    return implode("\n", $lines) . "\n";
}

function sc_export_csv(string $catalog): string
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, sc_export_columns(), ';');
    foreach (sc_export_rows($catalog) as $row) {
        $line = [];
        foreach (sc_export_columns() as $column) {
            $line[] = (string) ($row[$column] ?? '');
        }
        fputcsv($stream, $line, ';');
    }
    rewind($stream);
    $content = (string) stream_get_contents($stream);
    fclose($stream);
    return $content;
}

function sc_export_send(string $catalog, string $format): void
{
    $format = strtolower($format);
    if (!in_array($format, ['xml', 'csv'], true)) {
        http_response_code(400);
        exit('Podržani formati su xml i csv.');
    }
    $content = $format === 'xml' ? sc_export_xml($catalog) : sc_export_csv($catalog);
    sc_send_download_headers(
        $format === 'xml' ? 'application/xml; charset=utf-8' : 'text/csv; charset=utf-8',
        'cjenik-' . sc_slug($catalog) . '-' . date('Y-m-d') . '.' . $format
    );
    echo $content;
    exit;
}

function sc_send_download_headers(string $contentType, string $filename): void
{
    $filename = basename($filename);
    $filename = (string) preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Robots-Tag: noindex');
}

function sc_archive_create(string $catalog): array
{
    $catalog = sc_slug($catalog);
    $dir = sc_data_path('archives/' . $catalog);
    sc_ensure_directory($dir);
    $day = date('Y-m-d');
    foreach ((array) glob($dir . '/cjenik-' . $catalog . '-' . $day . '-*.{xml,csv}', GLOB_BRACE) as $oldFile) {
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
    $stamp = $day . '-' . date('His');
    $base = 'cjenik-' . $catalog . '-' . $stamp;
    $xml = $dir . '/' . $base . '.xml';
    $csv = $dir . '/' . $base . '.csv';
    $ok = file_put_contents($xml, sc_export_xml($catalog), LOCK_EX) !== false
        && file_put_contents($csv, sc_export_csv($catalog), LOCK_EX) !== false;
    if (!$ok) {
        @unlink($xml);
        @unlink($csv);
        throw new RuntimeException('Arhiva nije spremljena.');
    }
    @chmod($xml, 0640);
    @chmod($csv, 0640);
    sc_archive_prune($catalog);
    sc_audit('archive.create', ['catalog' => $catalog, 'file' => $base]);
    return ['xml' => basename($xml), 'csv' => basename($csv)];
}

function sc_archive_prune(string $catalog): void
{
    $days = max(1, (int) sc_config('archive_days', 30));
    $cutoff = time() - ($days * 86400);
    foreach ((array) glob(sc_data_path('archives/' . sc_slug($catalog) . '/*.{xml,csv}'), GLOB_BRACE) as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            @unlink($file);
        }
    }
}

function sc_archive_list(string $catalog): array
{
    $files = [];
    foreach ((array) glob(sc_data_path('archives/' . sc_slug($catalog) . '/*.{xml,csv}'), GLOB_BRACE) as $file) {
        if (is_file($file)) {
            $files[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'time' => filemtime($file),
            ];
        }
    }
    usort($files, static function (array $a, array $b): int {
        return $b['time'] <=> $a['time'];
    });
    return $files;
}

function sc_archive_days(string $catalog): array
{
    $days = [];
    foreach (sc_archive_list($catalog) as $file) {
        if (!preg_match('/-(\d{4}-\d{2}-\d{2})-(\d{6})\.(xml|csv)$/', (string) $file['name'], $match)) {
            continue;
        }
        $date = $match[1];
        $format = strtolower($match[3]);
        if (!isset($days[$date])) {
            $days[$date] = [
                'date' => $date,
                'time' => substr($match[2], 0, 2) . ':' . substr($match[2], 2, 2),
                'xml' => null,
                'csv' => null,
            ];
        }
        if ($days[$date][$format] === null) {
            $days[$date][$format] = $file;
        }
    }
    krsort($days);
    return array_values($days);
}

function sc_format_file_size(int $bytes): string
{
    return max(1, (int) round($bytes / 1024)) . ' kB';
}

function sc_archive_file(string $catalog, string $name): ?string
{
    $name = basename($name);
    if (!preg_match('/^cjenik-[a-z0-9-]+-\d{4}-\d{2}-\d{2}-\d{6}\.(xml|csv)$/', $name)) {
        return null;
    }
    $path = sc_data_path('archives/' . sc_slug($catalog) . '/' . $name);
    return is_file($path) ? $path : null;
}
