<?php
declare(strict_types=1);

function sc_catalog_path(string $catalog): string
{
    return sc_data_path('catalogs/' . sc_slug($catalog) . '.json');
}

function sc_catalog_default(string $catalog = 'main'): array
{
    return [
        'id' => sc_slug($catalog),
        'title' => (string) sc_settings()['catalog_title'],
        'item_label' => 'Naziv',
        'anchor_date' => date('Y-m-d'),
        'currency' => (string) sc_config('currency', 'EUR'),
        'footer_html' => '',
        'payment_icons' => [],
        'categories' => [],
        'updated_at' => date('c'),
    ];
}

function sc_catalog_load(string $catalog = 'main'): array
{
    $catalog = sc_slug($catalog);
    return sc_catalog_normalize(sc_json_read(sc_catalog_path($catalog), sc_catalog_default($catalog)), $catalog);
}

function sc_catalog_list(): array
{
    $dir = sc_data_path('catalogs');
    sc_ensure_directory($dir);
    $result = [];
    foreach ((array) glob($dir . '/*.json') as $path) {
        $id = pathinfo($path, PATHINFO_FILENAME);
        $data = sc_catalog_load($id);
        $result[] = ['id' => $id, 'title' => $data['title']];
    }
    if ($result === []) {
        $id = (string) sc_config('catalog_default', 'main');
        $result[] = ['id' => $id, 'title' => sc_catalog_default($id)['title']];
    }
    return $result;
}

function sc_parse_date(string $value, string $fallback = ''): string
{
    $value = trim($value);
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\.?$/', $value, $m)) {
        $value = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if ($date && $date->format('Y-m-d') === $value) {
        return $value;
    }
    return $fallback !== '' ? $fallback : date('Y-m-d');
}

function sc_format_date(string $value): string
{
    $value = sc_parse_date($value);
    return date('d.m.Y', strtotime($value));
}

function sc_parse_price(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return ['amount' => null, 'from' => false, 'label' => ''];
    }
    if (strpos(mb_strtolower($text), 'besplat') !== false) {
        return ['amount' => null, 'from' => false, 'label' => 'Besplatan'];
    }
    $from = (bool) preg_match('/^\s*od\b/iu', $text);
    $number = (string) preg_replace('/[^\d,.\-]/', '', $text);
    if ($number === '') {
        return ['amount' => null, 'from' => $from, 'label' => $text];
    }
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $number)) {
        $number = str_replace('.', '', $number);
        $number = str_replace(',', '.', $number);
    } else {
        $number = str_replace(',', '.', $number);
    }
    if (!is_numeric($number)) {
        return ['amount' => null, 'from' => $from, 'label' => $text];
    }
    return ['amount' => (float) $number, 'from' => $from, 'label' => ''];
}

function sc_price_text(array $item, string $prefix): string
{
    $label = (string) ($item[$prefix . '_label'] ?? '');
    if ($label !== '') {
        return $label;
    }
    $amount = $item[$prefix . '_amount'] ?? null;
    if ($amount === null || $amount === '') {
        return '';
    }
    return (!empty($item[$prefix . '_from']) ? 'od ' : '') . sc_format_number((float) $amount);
}

function sc_format_number(float $amount): string
{
    return abs($amount - round($amount)) < 0.001
        ? number_format($amount, 0, ',', '.')
        : number_format($amount, 2, ',', '.');
}

function sc_format_price(array $item, string $prefix, string $currency): string
{
    $text = sc_price_text($item, $prefix);
    if ($text === '') {
        return '—';
    }
    if (strpos(mb_strtolower($text), 'besplat') !== false) {
        return $text;
    }
    return $text . ' ' . $currency;
}

function sc_catalog_normalize(array $data, string $catalog = 'main'): array
{
    $anchorDate = sc_parse_date((string) ($data['anchor_date'] ?? ''));
    $categories = [];
    foreach ((array) ($data['categories'] ?? []) as $category) {
        if (!is_array($category)) {
            continue;
        }
        $title = trim((string) ($category['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $items = [];
        foreach ((array) ($category['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = sc_catalog_normalize_item($item, $anchorDate);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }
        $categories[] = [
            'id' => sc_slug((string) ($category['id'] ?? $title)),
            'title' => $title,
            'items' => $items,
        ];
    }
    $icons = [];
    foreach ((array) ($data['payment_icons'] ?? []) as $icon) {
        if (!is_array($icon) || trim((string) ($icon['image'] ?? '')) === '') {
            continue;
        }
        $icons[] = [
            'image' => trim((string) $icon['image']),
            'alt' => trim((string) ($icon['alt'] ?? '')),
        ];
    }
    return [
        'id' => sc_slug((string) ($data['id'] ?? $catalog)),
        'title' => trim((string) ($data['title'] ?? '')) ?: 'Cjenik',
        'item_label' => trim((string) ($data['item_label'] ?? '')) ?: 'Naziv',
        'anchor_date' => $anchorDate,
        'currency' => trim((string) ($data['currency'] ?? 'EUR')) ?: 'EUR',
        'footer_html' => sc_clean_html((string) ($data['footer_html'] ?? '')),
        'payment_icons' => $icons,
        'categories' => $categories,
        'updated_at' => (string) ($data['updated_at'] ?? date('c')),
    ];
}

function sc_catalog_normalize_item(array $item, string $fallbackDate): ?array
{
    $name = trim((string) ($item['name'] ?? ''));
    if ($name === '') {
        return null;
    }
    $price = sc_parse_price((string) ($item['price_text'] ?? sc_price_text($item, 'price')));
    $anchor = sc_parse_price((string) ($item['anchor_text'] ?? sc_price_text($item, 'anchor')));
    if ($anchor['amount'] === null && $anchor['label'] === '') {
        $anchor = $price;
    }
    return [
        'id' => sc_slug((string) ($item['id'] ?? $name)),
        'name' => $name,
        'code' => trim((string) ($item['code'] ?? '')),
        'description' => trim((string) ($item['description'] ?? '')),
        'unit' => trim((string) ($item['unit'] ?? 'kom')) ?: 'kom',
        'price_amount' => $price['amount'],
        'price_from' => $price['from'],
        'price_label' => $price['label'],
        'anchor_amount' => $anchor['amount'],
        'anchor_from' => $anchor['from'],
        'anchor_label' => $anchor['label'],
        'anchor_date' => sc_parse_date((string) ($item['anchor_date'] ?? ''), $fallbackDate),
        'active' => !isset($item['active']) || !empty($item['active']),
        'featured' => !empty($item['featured']),
    ];
}

function sc_catalog_from_post(array $post, string $catalog): array
{
    $data = [
        'id' => sc_slug($catalog),
        'title' => trim((string) ($post['title'] ?? 'Cjenik')),
        'item_label' => trim((string) ($post['item_label'] ?? 'Naziv')),
        'anchor_date' => sc_parse_date((string) ($post['anchor_date'] ?? '')),
        'currency' => strtoupper(trim((string) ($post['currency'] ?? 'EUR'))),
        'footer_html' => sc_clean_html((string) ($post['footer_html'] ?? '')),
        'payment_icons' => [],
        'categories' => [],
        'updated_at' => date('c'),
    ];
    foreach ((array) ($post['payment_icons'] ?? []) as $icon) {
        if (is_array($icon)) {
            $data['payment_icons'][] = $icon;
        }
    }
    foreach ((array) ($post['categories'] ?? []) as $category) {
        if (!is_array($category)) {
            continue;
        }
        $items = [];
        foreach ((array) ($category['items'] ?? []) as $item) {
            if (is_array($item)) {
                $item['active'] = isset($item['active']);
                $item['featured'] = isset($item['featured']);
                $items[] = $item;
            }
        }
        $category['items'] = $items;
        $data['categories'][] = $category;
    }
    return sc_catalog_normalize($data, $catalog);
}

function sc_catalog_item_index(array $catalog): array
{
    $index = [];
    foreach ($catalog['categories'] as $category) {
        foreach ($category['items'] as $item) {
            $index[(string) $item['id']] = $item;
        }
    }
    return $index;
}

function sc_catalog_filter_categories(array $catalog, string $selection): array
{
    $selection = trim($selection);
    if ($selection === '') {
        return $catalog;
    }
    $wanted = [];
    foreach (explode(',', $selection) as $id) {
        $id = sc_slug(trim($id));
        if ($id !== '') {
            $wanted[$id] = true;
        }
    }
    if ($wanted === []) {
        return $catalog;
    }
    $catalog['categories'] = array_values(array_filter(
        $catalog['categories'],
        static function (array $category) use ($wanted): bool {
            return isset($wanted[(string) $category['id']]);
        }
    ));
    return $catalog;
}

function sc_catalog_save(string $catalog, array $data): bool
{
    $catalog = sc_slug($catalog);
    $before = sc_catalog_load($catalog);
    $data = sc_catalog_normalize($data, $catalog);
    $old = sc_catalog_item_index($before);
    foreach (sc_catalog_item_index($data) as $id => $item) {
        $previous = $old[$id] ?? null;
        if ($previous === null
            || sc_price_text($previous, 'price') !== sc_price_text($item, 'price')
            || sc_price_text($previous, 'anchor') !== sc_price_text($item, 'anchor')
            || $previous['anchor_date'] !== $item['anchor_date']) {
            sc_catalog_history_append($catalog, $id, $previous, $item);
        }
    }
    $ok = sc_json_write(sc_catalog_path($catalog), $data);
    if ($ok) {
        sc_audit('catalog.save', ['catalog' => $catalog]);
    }
    return $ok;
}

function sc_catalog_history_append(string $catalog, string $itemId, ?array $before, array $after): void
{
    $path = sc_data_path('history/' . sc_slug($catalog) . '.jsonl');
    sc_ensure_directory(dirname($path));
    $record = [
        'time' => date('c'),
        'item_id' => $itemId,
        'before' => $before,
        'after' => $after,
    ];
    @file_put_contents(
        $path,
        json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

function sc_csv_header_key(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = strtr($value, [
        'č' => 'c', 'ć' => 'c', 'ž' => 'z', 'š' => 's', 'đ' => 'd',
    ]);
    $value = (string) preg_replace('/[^a-z0-9]+/', '_', $value);
    $value = trim($value, '_');
    $aliases = [
        'name' => 'naziv',
        'usluga' => 'naziv',
        'stavka' => 'naziv',
        'code' => 'sifra',
        'sku' => 'sifra',
        'price' => 'cijena',
        'anchor' => 'sidrena_cijena',
        'sidrena' => 'sidrena_cijena',
        'anchor_date' => 'sidrena_cijena_datum',
        'datum' => 'sidrena_cijena_datum',
        'datum_sidrene' => 'sidrena_cijena_datum',
        'category' => 'kategorija',
        'kat' => 'kategorija',
        'unit' => 'jedinica_mjere',
        'jedinica' => 'jedinica_mjere',
        'currency' => 'valuta',
        'opis' => 'description',
        'description' => 'description',
        'dostupno' => 'dostupnost',
    ];
    return $aliases[$value] ?? $value;
}

function sc_csv_detect_delimiter(string $line): string
{
    $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
    arsort($counts);
    $delimiter = (string) array_key_first($counts);
    return ($counts[$delimiter] ?? 0) > 0 ? $delimiter : ';';
}

function sc_csv_parse_rows(string $csv): array
{
    $csv = (string) preg_replace('/^\xEF\xBB\xBF/', '', $csv);
    $csv = str_replace(["\r\n", "\r"], "\n", $csv);
    $csv = trim($csv);
    if ($csv === '') {
        throw new RuntimeException('CSV je prazan.');
    }
    $first = '';
    foreach (explode("\n", $csv) as $line) {
        if (trim($line) !== '') {
            $first = $line;
            break;
        }
    }
    $delimiter = sc_csv_detect_delimiter($first);
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        throw new RuntimeException('CSV se ne može pročitati.');
    }
    fwrite($handle, $csv);
    rewind($handle);
    $rows = [];
    while (($row = fgetcsv($handle, 20000, $delimiter)) !== false) {
        if ($row === [null] || $row === []) {
            continue;
        }
        $empty = true;
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                $empty = false;
                break;
            }
        }
        if (!$empty) {
            $rows[] = $row;
        }
    }
    fclose($handle);
    if ($rows === []) {
        throw new RuntimeException('CSV nema podataka.');
    }
    return $rows;
}

function sc_catalog_from_csv_rows(array $rows, array $current, string $mode): array
{
    $header = array_map('sc_csv_header_key', $rows[0]);
    $hasHeader = in_array('naziv', $header, true) || in_array('cijena', $header, true) || in_array('kategorija', $header, true);
    if ($hasHeader) {
        array_shift($rows);
    } else {
        $header = ['naziv', 'cijena', 'sidrena_cijena', 'sidrena_cijena_datum', 'kategorija'];
    }
    $grouped = [];
    $currency = (string) $current['currency'];
    foreach ($rows as $row) {
        $assoc = [];
        foreach ($header as $index => $key) {
            $assoc[$key] = trim((string) ($row[$index] ?? ''));
        }
        $name = (string) ($assoc['naziv'] ?? '');
        if ($name === '') {
            continue;
        }
        $categoryTitle = trim((string) ($assoc['kategorija'] ?? '')) ?: 'Opće';
        $categoryKey = sc_slug($categoryTitle);
        $price = (string) ($assoc['cijena'] ?? '');
        $anchor = (string) ($assoc['sidrena_cijena'] ?? '');
        $availability = mb_strtolower((string) ($assoc['dostupnost'] ?? 'dostupno'));
        if ($currency === '' || $currency === 'EUR') {
            $fromCsv = strtoupper((string) ($assoc['valuta'] ?? ''));
            if ($fromCsv !== '') {
                $currency = $fromCsv;
            }
        }
        $item = [
            'id' => sc_slug((string) (($assoc['sifra'] ?? '') !== '' ? $assoc['sifra'] : $name)),
            'name' => $name,
            'code' => (string) ($assoc['sifra'] ?? ''),
            'description' => (string) ($assoc['description'] ?? ''),
            'unit' => (string) (($assoc['jedinica_mjere'] ?? '') !== '' ? $assoc['jedinica_mjere'] : 'kom'),
            'price_text' => $price,
            'anchor_text' => $anchor !== '' ? $anchor : $price,
            'anchor_date' => (string) ($assoc['sidrena_cijena_datum'] ?? $current['anchor_date']),
            'active' => $availability === '' || strpos($availability, 'nedostup') === false,
        ];
        if (!isset($grouped[$categoryKey])) {
            $grouped[$categoryKey] = [
                'id' => $categoryKey,
                'title' => $categoryTitle,
                'items' => [],
            ];
        }
        $grouped[$categoryKey]['items'][] = $item;
    }
    if ($grouped === []) {
        throw new RuntimeException('U CSV-u nema redova s nazivom stavke.');
    }
    $data = $current;
    $data['currency'] = $currency !== '' ? $currency : $current['currency'];
    if ($mode === 'append') {
        $existing = [];
        foreach ($data['categories'] as $category) {
            $existing[(string) $category['id']] = $category;
        }
        foreach ($grouped as $id => $category) {
            if (!isset($existing[$id])) {
                $existing[$id] = $category;
                continue;
            }
            $index = [];
            foreach ($existing[$id]['items'] as $itemIndex => $item) {
                $index[(string) $item['id']] = $itemIndex;
                $index[sc_slug((string) $item['name'])] = $itemIndex;
            }
            foreach ($category['items'] as $item) {
                $key = (string) $item['id'];
                if (isset($index[$key])) {
                    $existing[$id]['items'][$index[$key]] = $item;
                } else {
                    $existing[$id]['items'][] = $item;
                }
            }
        }
        $data['categories'] = array_values($existing);
    } else {
        $data['categories'] = array_values($grouped);
    }
    $data['updated_at'] = date('c');
    return sc_catalog_normalize($data, (string) $current['id']);
}

function sc_catalog_import_csv(string $catalog, string $csv, string $mode = 'replace'): int
{
    $mode = $mode === 'append' ? 'append' : 'replace';
    $current = sc_catalog_load($catalog);
    $imported = sc_catalog_from_csv_rows(sc_csv_parse_rows($csv), $current, $mode);
    if (!sc_catalog_save($catalog, $imported)) {
        throw new RuntimeException('Uvoz nije spremljen.');
    }
    $count = 0;
    foreach ($imported['categories'] as $category) {
        $count += count($category['items']);
    }
    sc_audit('catalog.import', ['catalog' => sc_slug($catalog), 'mode' => $mode, 'items' => $count]);
    return $count;
}

function sc_catalog_delete(string $catalog): string
{
    $catalog = sc_slug($catalog);
    $remaining = [];
    foreach (sc_catalog_list() as $option) {
        if ($option['id'] !== $catalog) {
            $remaining[] = $option['id'];
        }
    }
    if ($remaining === []) {
        throw new RuntimeException('Ne može se obrisati posljednji cjenik.');
    }
    $path = sc_catalog_path($catalog);
    if (is_file($path) && !@unlink($path)) {
        throw new RuntimeException('Cjenik nije obrisan.');
    }
    $history = sc_data_path('history/' . $catalog . '.jsonl');
    if (is_file($history)) {
        @unlink($history);
    }
    foreach ((array) glob(sc_data_path('archives/' . $catalog . '/*')) as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    $archiveDir = sc_data_path('archives/' . $catalog);
    if (is_dir($archiveDir)) {
        @rmdir($archiveDir);
    }
    sc_audit('catalog.delete', ['catalog' => $catalog]);
    return $remaining[0];
}
