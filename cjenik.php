<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$catalog = sc_slug((string) ($_GET['catalog'] ?? sc_config('catalog_default', 'main')));
$categorySelection = trim((string) ($_GET['categories'] ?? ''));
$format = strtolower((string) ($_GET['format'] ?? 'html'));
$action = (string) ($_GET['action'] ?? '');

if ($action === 'archive') {
    $settings = sc_settings();
    $token = (string) ($_GET['token'] ?? '');
    if ($token === '' || empty($settings['cron_token']) || !hash_equals((string) $settings['cron_token'], $token)) {
        http_response_code(403);
        exit('Neispravan token.');
    }
    header('Content-Type: text/plain; charset=utf-8');
    try {
        $files = sc_archive_create($catalog);
        echo "Arhiva je izrađena: " . $files['xml'] . ', ' . $files['csv'] . "\n";
    } catch (Throwable $e) {
        http_response_code(500);
        echo "Greška: " . $e->getMessage() . "\n";
    }
    exit;
}

if ($format === 'xml' || $format === 'csv') {
    sc_export_send($catalog, $format);
}

if ($format === 'json') {
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowed = (array) sc_config('embed_origins', ['*']);
    if (in_array('*', $allowed, true)) {
        header('Access-Control-Allow-Origin: *');
    } elseif ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(
        sc_catalog_filter_categories(sc_catalog_load($catalog), $categorySelection),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if (isset($_GET['file'])) {
    $path = sc_archive_file($catalog, (string) $_GET['file']);
    if ($path === null) {
        http_response_code(404);
        exit('Datoteka nije pronađena.');
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    sc_send_download_headers(
        $ext === 'xml' ? 'application/xml; charset=utf-8' : 'text/csv; charset=utf-8',
        basename($path)
    );
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}

$archiveView = (string) ($_GET['view'] ?? '') === 'archive';
$queryBase = 'catalog=' . rawurlencode($catalog);
if ($categorySelection !== '') {
    $queryBase .= '&categories=' . rawurlencode($categorySelection);
}
$data = sc_catalog_load($catalog);
if (!$archiveView) {
    $data = sc_catalog_filter_categories($data, $categorySelection);
}
$settings = sc_settings();
$localServer = PHP_SAPI === 'cli-server';
$xmlUrl = $localServer
    ? sc_base_url('cjenik.php') . '?' . $queryBase . '&format=xml'
    : sc_base_url('cjenik.xml') . '?' . $queryBase;
$csvUrl = $localServer
    ? sc_base_url('cjenik.php') . '?' . $queryBase . '&format=csv'
    : sc_base_url('cjenik.csv') . '?' . $queryBase;
$archiveUrl = $localServer
    ? sc_base_url('cjenik.php') . '?' . $queryBase . '&view=archive'
    : sc_base_url('arhiva-cjenika/') . '?' . $queryBase;
$catalogUrl = sc_base_url('cjenik.php') . '?' . $queryBase;
?><!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title><?= sc_e($archiveView ? 'Arhiva cjenika' : $data['title']) ?></title>
    <link rel="stylesheet" href="<?= sc_e(sc_asset_url('cjenik.css')) ?>">
    <style><?= sc_theme_css($settings) ?></style>
    <?php if (trim((string) $settings['custom_css']) !== ''): ?>
        <style><?= sc_clean_css((string) $settings['custom_css']) ?></style>
    <?php endif; ?>
</head>
<body class="sc-public">
<main class="sc-wrap" id="sc-cjenik">
    <header class="sc-public-head">
        <div>
            <p class="sc-eyebrow"><?= sc_e((string) $settings['merchant_name']) ?></p>
            <h1><?= sc_e($archiveView ? 'Arhiva cjenika' : $data['title']) ?></h1>
        </div>
        <?php if (!$archiveView): ?>
            <label class="sc-search">
                <span class="sc-sr-only">Pretraži cjenik</span>
                <input type="search" placeholder="Pretraži cjenik" data-sc-search>
            </label>
        <?php endif; ?>
    </header>

    <nav class="sc-links" aria-label="Formati cjenika">
        <?php if ($archiveView): ?>
            <a href="<?= sc_e($catalogUrl) ?>">Natrag na cjenik</a>
        <?php else: ?>
            <a href="<?= sc_e($xmlUrl) ?>">XML</a>
            <a href="<?= sc_e($csvUrl) ?>">CSV</a>
            <a href="?<?= sc_e($queryBase) ?>&format=json">JSON</a>
            <a href="<?= sc_e($archiveUrl) ?>">Arhiva</a>
        <?php endif; ?>
    </nav>

    <?php if ($archiveView): ?>
        <section class="sc-archive-view">
            <p><strong><?= sc_e($settings['merchant_name']) ?></strong> · objavljeni cjenici u strojno čitljivom obliku, dostupni <?= (int) sc_config('archive_days', 30) ?> dana od objave.</p>
            <p class="sc-archive-note">Prema Odluci o objavi cjenika proizvoda i usluga (Narodne novine 101/2026), na snazi od 1. listopada 2026. Za svaki dan objavljuje se jedan cjenik, u oba oblika.</p>
            <?php $archiveDays = sc_archive_days($catalog); ?>
            <?php if ($archiveDays === []): ?>
                <p>Arhiva još nema zapisa.</p>
            <?php else: ?>
                <div class="sc-table-wrap sc-archive-table-wrap">
                    <table class="sc-archive-table">
                        <thead><tr><th>Dan</th><th>XML</th><th>CSV</th><th>Zapisano</th></tr></thead>
                        <tbody>
                    <?php foreach ($archiveDays as $day): ?>
                        <tr>
                            <td data-label="Dan"><strong><?= sc_e(date('d.m.Y.', strtotime($day['date']))) ?></strong></td>
                            <?php foreach (['xml' => 'XML', 'csv' => 'CSV'] as $formatKey => $formatLabel): ?>
                                <td data-label="<?= sc_e($formatLabel) ?>">
                                    <?php if (is_array($day[$formatKey])): ?>
                                        <?php
                                        $file = $day[$formatKey];
                                        $fileUrl = $localServer
                                            ? sc_base_url('cjenik.php') . '?' . $queryBase . '&file=' . rawurlencode((string) $file['name'])
                                            : sc_base_url('arhiva-cjenika/' . (string) $file['name']) . '?' . $queryBase;
                                        ?>
                                        <a href="<?= sc_e($fileUrl) ?>" download="<?= sc_e((string) $file['name']) ?>"><?= sc_e($formatLabel) ?></a>
                                        <small><?= sc_e(sc_format_file_size((int) $file['size'])) ?></small>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td data-label="Zapisano"><?= sc_e($day['time']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="sc-archive-note">Stranica se obnavlja pri svakoj dnevnoj snimci.</p>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <?php if ($data['categories'] === []): ?>
            <p class="sc-card">Nema odabranih kategorija za prikaz.</p>
        <?php endif; ?>
        <?php foreach ($data['categories'] as $category): ?>
            <?php
            $activeItems = array_values(array_filter($category['items'], static function (array $item): bool {
                return !empty($item['active']);
            }));
            if ($activeItems === []) {
                continue;
            }
            ?>
            <section class="sc-category" data-sc-category>
                <h2><?= sc_e($category['title']) ?></h2>
                <div class="sc-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><?= sc_e($data['item_label']) ?></th>
                                <th>Cijena</th>
                                <th>Sidrena cijena</th>
                                <th>Datum sidrene</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeItems as $item): ?>
                                <tr data-sc-item data-search="<?= sc_e(mb_strtolower($item['name'] . ' ' . $item['description'])) ?>">
                                    <td data-label="<?= sc_e($data['item_label']) ?>">
                                        <strong><?= sc_e($item['name']) ?></strong>
                                        <?php if ($item['description'] !== ''): ?><small><?= sc_e($item['description']) ?></small><?php endif; ?>
                                    </td>
                                    <td class="sc-price" data-label="Cijena"><?= sc_e(sc_format_price($item, 'price', $data['currency'])) ?></td>
                                    <td data-label="Sidrena cijena"><?= sc_e(sc_format_price($item, 'anchor', $data['currency'])) ?></td>
                                    <td data-label="Datum sidrene"><?= sc_e(sc_format_date($item['anchor_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if ($data['footer_html'] !== ''): ?>
            <footer class="sc-footer-copy"><?= $data['footer_html'] ?></footer>
        <?php endif; ?>
        <?php if ($data['payment_icons'] !== []): ?>
            <div class="sc-payment-icons">
                <?php foreach ($data['payment_icons'] as $icon): ?>
                    <img src="<?= sc_e($icon['image']) ?>" alt="<?= sc_e($icon['alt']) ?>" loading="lazy">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($settings['show_credit'])): ?>
        <p class="sc-credit">sidrene cijene by <a href="<?= sc_e((string) sc_config('vendor_url', 'https://www.enc-it.hr/')) ?>" target="_blank" rel="noopener"><?= sc_e((string) sc_config('vendor', 'Enc IT d.o.o.')) ?></a></p>
    <?php endif; ?>
</main>
<script src="<?= sc_e(sc_asset_url('embed.js')) ?>"></script>
</body>
</html>
