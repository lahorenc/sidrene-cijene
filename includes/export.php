<?php
declare(strict_types=1);

function sc_price_list_type(string $catalog = ''): string
{
    if ($catalog !== '') {
        $data = sc_catalog_load($catalog);
        $fromCatalog = strtolower(trim((string) ($data['list_type'] ?? '')));
        if (in_array($fromCatalog, ['roba', 'usluge'], true)) {
            return $fromCatalog;
        }
    }
    $settings = sc_settings();
    $fromSettings = strtolower(trim((string) ($settings['price_list_type'] ?? 'usluge')));
    return in_array($fromSettings, ['roba', 'usluge'], true) ? $fromSettings : 'usluge';
}

function sc_export_columns(string $catalog = ''): array
{
    if (sc_price_list_type($catalog) === 'roba') {
        return [
            'naziv',
            'sifra',
            'marka',
            'neto_kolicina',
            'jedinica_mjere',
            'cijena',
            'posebna_prodaja',
            'naziv_posebne_prodaje',
            'cijena_po_jedinici',
            'barkod',
            'ean_barkod',
            'bazna_cijena',
        ];
    }

    return [
        'naziv',
        'cijena',
        'posebna_prodaja',
        'naziv_posebne_prodaje',
        'bazna_cijena',
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
    $isGoods = sc_price_list_type($catalog) === 'roba';
    $rows = [];
    foreach ($data['categories'] as $category) {
        foreach ($category['items'] as $item) {
            if (empty($item['active'])) {
                continue;
            }
            $price = sc_export_amount($item['price_amount'], (string) $item['price_label']);
            $anchor = sc_export_amount($item['anchor_amount'], (string) $item['anchor_label']);
            $sale = $price !== '' && $anchor !== '' && (float) $price < (float) $anchor;
            $row = [
                'naziv' => (string) $item['name'],
                'cijena' => $price,
                'posebna_prodaja' => $sale ? 'da' : 'ne',
                'naziv_posebne_prodaje' => $sale ? 'akcijska prodaja' : '',
                'bazna_cijena' => $anchor,
            ];
            if ($isGoods) {
                $neto = trim((string) ($item['neto_kolicina'] ?? ''));
                $row['sifra'] = trim((string) $item['code']) ?: (string) $item['id'];
                $row['marka'] = trim((string) ($item['marka'] ?? '')) ?: $brand;
                $row['neto_kolicina'] = $neto !== '' ? $neto : '1';
                $row['jedinica_mjere'] = (string) $item['unit'];
                $row['cijena_po_jedinici'] = $price;
                $row['barkod'] = trim((string) ($item['barkod'] ?? ''));
                $row['ean_barkod'] = trim((string) ($item['ean_barkod'] ?? ''));
            }
            $rows[] = $row;
        }
    }
    return $rows;
}

function sc_export_xml(string $catalog): string
{
    $settings = sc_settings();
    $escape = static function ($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };
    $listType = sc_price_list_type($catalog);
    $itemTag = $listType === 'roba' ? 'proizvod' : 'usluga';
    $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
    $lines[] = sprintf(
        '<cjenik trgovac="%s" oib="%s" tip="%s" vrijeme="%s">',
        $escape($settings['merchant_name']),
        $escape(preg_replace('/\D+/', '', (string) $settings['oib'])),
        $escape($listType),
        $escape(date('c'))
    );
    foreach (sc_export_rows($catalog) as $row) {
        $lines[] = '  <' . $itemTag . '>';
        foreach (sc_export_columns($catalog) as $column) {
            $lines[] = sprintf('    <%s>%s</%s>', $column, $escape($row[$column] ?? ''), $column);
        }
        $lines[] = '  </' . $itemTag . '>';
    }
    $lines[] = '</cjenik>';
    return implode("\n", $lines) . "\n";
}

function sc_export_csv(string $catalog): string
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, sc_export_columns($catalog), ';');
    foreach (sc_export_rows($catalog) as $row) {
        $line = [];
        foreach (sc_export_columns($catalog) as $column) {
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
        sc_archive_filename_stem($catalog, false) . '.' . $format
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

function sc_archive_part(string $value): string
{
    $value = strtr($value, [
        'č' => 'c', 'ć' => 'c', 'đ' => 'd', 'š' => 's', 'ž' => 'z',
        'Č' => 'C', 'Ć' => 'C', 'Đ' => 'D', 'Š' => 'S', 'Ž' => 'Z',
    ]);
    $value = str_replace(['.', ',', '_'], ' ', $value);
    $value = preg_replace('/\s+/', '-', trim($value)) ?? '';
    $value = preg_replace('/[^A-Za-z0-9-]/', '', $value) ?? '';
    return trim(preg_replace('/-+/', '-', $value) ?? '', '-');
}

function sc_archive_merchant_id(): string
{
    $settings = sc_settings();
    $id = preg_replace('/\D+/', '', (string) ($settings['oib'] ?? '')) ?? '';
    if ($id === '') {
        $id = '00000000000';
    }
    return $id;
}

function sc_archive_filename_stem(string $catalog = '', bool $incrementSeq = true): string
{
    $settings = sc_settings();
    $listType = strtoupper(sc_price_list_type($catalog));
    $seq = (int) ($settings['archive_seq'] ?? 0);
    if ($incrementSeq) {
        $seq++;
        $settings['archive_seq'] = $seq;
        sc_save_settings($settings);
    } elseif ($seq < 1) {
        $seq = 1;
    }

    $stamp = date('Ymd_Hi');
    $merchant = sc_archive_merchant_id();

    if (!empty($settings['all_outlets'])) {
        return $merchant
            . '_ZA_SVA_PRODAJNA_MJESTA'
            . '_' . $listType
            . '_' . sprintf('%04d', $seq)
            . '_' . $stamp;
    }

    $form = trim((string) ($settings['outlet_form'] ?? ''));
    if ($form === '' || $form === 'internetska-trgovina') {
        $form = 'online-webshop';
    }
    $address = trim((string) ($settings['outlet_address'] ?? ''));
    if ($address === '') {
        $address = 'nepoznata-adresa';
    }
    $mark = trim((string) ($settings['outlet_mark'] ?? ''));
    if ($mark === '') {
        $mark = 'web-1';
    }

    return $merchant
        . '_' . sc_archive_part($form)
        . '_' . sc_archive_part($address)
        . '_' . sc_archive_part($mark)
        . '_' . $listType
        . '_' . sprintf('%04d', $seq)
        . '_' . $stamp;
}

function sc_archive_filename_pattern(): string
{
    return '/^(?:'
        . '\d{8,13}_ZA_SVA_PRODAJNA_MJESTA_(?:ROBA|USLUGE)_\d{4,}_\d{8}_\d{4}'
        . '|\d{8,13}_[A-Za-z0-9-]+_[A-Za-z0-9-]+_[A-Za-z0-9-]+_(?:ROBA|USLUGE)_\d{4,}_\d{8}_\d{4}'
        . '|[A-Za-z0-9-]+_[A-Za-z0-9-]+_[A-Za-z0-9-]+_\d{4}_\d{2}-\d{2}-\d{4}T\d{2}-\d{2}'
        . ')\.(xml|csv)$/';
}

function sc_archive_drop_same_day(string $dir): void
{
    $dayOld = date('d-m-Y');
    $dayNew = date('Ymd');
    foreach ((array) glob($dir . '/*.{xml,csv}', GLOB_BRACE) as $oldFile) {
        $name = basename((string) $oldFile);
        if (!is_file($oldFile) || !preg_match(sc_archive_filename_pattern(), $name)) {
            continue;
        }
        if (strpos($name, '_' . $dayOld . 'T') !== false || preg_match('/_' . $dayNew . '_\d{4}\.(xml|csv)$/', $name)) {
            @unlink($oldFile);
        }
    }
}

function sc_archive_create(string $catalog): array
{
    $catalog = sc_slug($catalog);
    $dir = sc_data_path('archives/' . $catalog);
    sc_ensure_directory($dir);
    sc_archive_drop_same_day($dir);
    $base = sc_archive_filename_stem($catalog, true);
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
    $days = max(1, (int) sc_config('archive_days', 15));
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

function sc_archive_parse_name(string $name): ?array
{
    if (preg_match('/_(\d{8})_(\d{4})\.(xml|csv)$/', $name, $match)) {
        $date = $match[1];
        return [
            'date' => substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2),
            'time' => substr($match[2], 0, 2) . ':' . substr($match[2], 2, 2),
            'format' => strtolower($match[3]),
        ];
    }
    if (preg_match('/_(\d{2})-(\d{2})-(\d{4})T(\d{2})-(\d{2})\.(xml|csv)$/', $name, $match)) {
        return [
            'date' => $match[3] . '-' . $match[2] . '-' . $match[1],
            'time' => $match[4] . ':' . $match[5],
            'format' => strtolower($match[6]),
        ];
    }
    if (preg_match('/^cjenik-[a-z0-9-]+-(\d{4}-\d{2}-\d{2})-(\d{6})\.(xml|csv)$/', $name, $match)) {
        return [
            'date' => $match[1],
            'time' => substr($match[2], 0, 2) . ':' . substr($match[2], 2, 2),
            'format' => strtolower($match[3]),
        ];
    }
    return null;
}

function sc_archive_days(string $catalog): array
{
    $days = [];
    foreach (sc_archive_list($catalog) as $file) {
        $parsed = sc_archive_parse_name((string) $file['name']);
        if ($parsed === null) {
            continue;
        }
        $date = $parsed['date'];
        $format = $parsed['format'];
        if (!isset($days[$date])) {
            $days[$date] = [
                'date' => $date,
                'time' => $parsed['time'],
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
    if (sc_archive_parse_name($name) === null) {
        return null;
    }
    $path = sc_data_path('archives/' . sc_slug($catalog) . '/' . $name);
    return is_file($path) ? $path : null;
}
