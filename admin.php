<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
sc_send_no_cache_headers();
sc_start_session();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sc_require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'install' && !sc_is_installed()) {
        $username = trim((string) ($_POST['username'] ?? 'admin'));
        $password = (string) ($_POST['password'] ?? '');
        $merchant = trim((string) ($_POST['merchant_name'] ?? ''));
        if (strlen($password) < 10 || $username === '' || $merchant === '') {
            $error = 'Upišite naziv, korisničko ime i lozinku od najmanje 10 znakova.';
        } else {
            sc_json_write(sc_data_path('admin.json'), [
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date('c'),
            ], false);
            $settings = sc_settings();
            $settings['merchant_name'] = $merchant;
            $settings['oib'] = preg_replace('/\D+/', '', (string) ($_POST['oib'] ?? ''));
            sc_save_settings($settings);
            sc_catalog_save((string) sc_config('catalog_default', 'main'), sc_catalog_default());
            sc_login($username, $password);
            header('Location: ' . sc_base_url('admin.php'));
            exit;
        }
    } elseif ($action === 'login' && sc_is_installed()) {
        if (sc_login(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''))) {
            header('Location: ' . sc_base_url('admin.php'));
            exit;
        }
        $error = 'Prijava nije uspjela. Nakon pet pokušaja slijedi pauza od pet minuta.';
    } elseif ($action === 'logout') {
        sc_logout();
        header('Location: ' . sc_base_url('admin.php'));
        exit;
    } else {
        sc_require_admin();
        $catalog = sc_slug((string) ($_POST['catalog'] ?? sc_config('catalog_default', 'main')));
        $returnTab = (string) ($_POST['return_tab'] ?? 'postavke');
        if (!in_array($returnTab, sc_admin_tabs(), true)) {
            $returnTab = 'postavke';
        }
        if ($action === 'save') {
            $settings = sc_settings();
            $settings['merchant_name'] = trim((string) ($_POST['merchant_name'] ?? ''));
            $settings['oib'] = preg_replace('/\D+/', '', (string) ($_POST['oib'] ?? ''));
            $settings['catalog_title'] = trim((string) ($_POST['title'] ?? 'Cjenik'));
            $settings['custom_css'] = sc_clean_css((string) ($_POST['custom_css'] ?? ''));
            $settings['show_credit'] = !empty($_POST['show_credit']);
            $layout = (string) ($_POST['embed_host_layout'] ?? 'default');
            $settings['embed_host_layout'] = in_array($layout, ['default', 'shape5'], true) ? $layout : 'default';
            $settings['theme'] = sc_theme_from_post($_POST);
            $settings['accent_color'] = (string) $settings['theme']['accent_color'];
            $ok = sc_save_settings($settings)
                && sc_catalog_save($catalog, sc_catalog_from_post($_POST, $catalog));
            sc_flash($ok ? 'success' : 'error', $ok ? 'Cjenik je spremljen.' : 'Spremanje nije uspjelo.');
        } elseif ($action === 'create_catalog') {
            $newName = trim((string) ($_POST['new_catalog'] ?? ''));
            $newId = sc_slug($newName);
            if ($newName === '' || is_file(sc_catalog_path($newId))) {
                sc_flash('error', $newName === '' ? 'Upišite naziv novog cjenika.' : 'Cjenik s tim nazivom već postoji.');
            } else {
                $newData = sc_catalog_default($newId);
                $newData['title'] = $newName;
                sc_catalog_save($newId, $newData);
                sc_flash('success', 'Novi cjenik je napravljen.');
                $catalog = $newId;
            }
        } elseif ($action === 'delete_catalog') {
            try {
                $next = sc_catalog_delete($catalog);
                sc_flash('success', 'Cjenik je obrisan.');
                $catalog = $next;
            } catch (Throwable $e) {
                sc_flash('error', $e->getMessage());
            }
        } elseif ($action === 'import_csv') {
            try {
                $csv = trim((string) ($_POST['csv_text'] ?? ''));
                $file = isset($_FILES['csv_file']) && is_array($_FILES['csv_file']) ? $_FILES['csv_file'] : [];
                if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
                    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
                        throw new RuntimeException('CSV datoteka je veća od 2 MB.');
                    }
                    $csv = (string) file_get_contents((string) $file['tmp_name']);
                }
                $count = sc_catalog_import_csv($catalog, $csv, (string) ($_POST['import_mode'] ?? 'replace'));
                sc_flash('success', 'Uvezeno je ' . $count . ' stavki.');
                $returnTab = 'cjenik';
            } catch (Throwable $e) {
                sc_flash('error', $e->getMessage());
                $returnTab = 'izvozi';
            }
        } elseif ($action === 'archive') {
            try {
                sc_archive_create($catalog);
                sc_flash('success', 'Arhiva je izrađena.');
            } catch (Throwable $e) {
                sc_flash('error', $e->getMessage());
            }
        } elseif ($action === 'change_credentials') {
            $ok = sc_change_credentials(
                (string) ($_POST['current_password'] ?? ''),
                (string) ($_POST['new_username'] ?? ''),
                (string) ($_POST['new_password'] ?? '')
            );
            sc_flash(
                $ok ? 'success' : 'error',
                $ok
                    ? 'Pristupni podaci su promijenjeni.'
                    : 'Promjena nije uspjela. Provjerite trenutačnu lozinku i novu lozinku od najmanje 10 znakova.'
            );
        }
        header(
            'Location: ' . sc_base_url('admin.php')
            . '?catalog=' . rawurlencode($catalog)
            . '&tab=' . rawurlencode($returnTab)
        );
        exit;
    }
}

function sc_admin_head(string $title): void
{
    ?><!doctype html>
    <html lang="hr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <meta name="csrf-token" content="<?= sc_e(sc_csrf_token()) ?>">
        <title><?= sc_e($title) ?> | <?= sc_e((string) sc_config('name', 'Cjenik')) ?></title>
        <link rel="stylesheet" href="<?= sc_e(sc_asset_url('admin.css')) ?>">
    </head>
    <body class="sc-admin-body" data-sc-base="<?= sc_e(sc_base_url()) ?>"><?php
}

function sc_admin_item_row(int $categoryIndex, int $itemIndex, array $item): void
{
    ?>
    <tr data-item>
        <td>
            <input name="categories[<?= $categoryIndex ?>][items][<?= $itemIndex ?>][name]" value="<?= sc_e($item['name'] ?? '') ?>" placeholder="Naziv" required>
            <input type="hidden" name="categories[<?= $categoryIndex ?>][items][<?= $itemIndex ?>][id]" value="<?= sc_e($item['id'] ?? '') ?>">
            <input name="categories[<?= $categoryIndex ?>][items][<?= $itemIndex ?>][description]" value="<?= sc_e($item['description'] ?? '') ?>" placeholder="Kratki opis (opcionalno)">
        </td>
        <td><input name="categories[<?= $categoryIndex ?>][items][<?= $itemIndex ?>][code]" value="<?= sc_e($item['code'] ?? '') ?>" placeholder="Šifra"></td>
        <td><input name="categories[<?= $categoryIndex ?>][items][<?= $itemIndex ?>][price_text]" value="<?= sc_e(sc_price_text($item, 'price')) ?>" placeholder="40 / od 250 / Besplatno"></td>
        <td><input name="categories[<?= $categoryIndex ?>][items][<?= $itemIndex ?>][anchor_text]" value="<?= sc_e(sc_price_text($item, 'anchor')) ?>" placeholder="Sidrena cijena"></td>
        <td><input name="categories[<?= $categoryIndex ?>][items][<?= $itemIndex ?>][anchor_date]" value="<?= sc_e(sc_format_date((string) ($item['anchor_date'] ?? date('Y-m-d')))) ?>" placeholder="dd.mm.gggg"></td>
        <td>
            <label class="sc-check"><input type="checkbox" name="categories[<?= $categoryIndex ?>][items][<?= $itemIndex ?>][active]" value="1"<?= !isset($item['active']) || !empty($item['active']) ? ' checked' : '' ?>> Aktivna</label>
            <button type="button" class="sc-btn sc-btn--danger sc-btn--small" data-remove-item>Ukloni</button>
        </td>
    </tr>
    <?php
}

function sc_admin_category(int $index, array $category): void
{
    $items = isset($category['items']) && is_array($category['items']) ? $category['items'] : [];
    ?>
    <section class="sc-panel sc-category-admin" data-category>
        <div class="sc-panel-head">
            <div class="sc-field sc-grow">
                <label>Naziv kategorije</label>
                <input name="categories[<?= $index ?>][title]" value="<?= sc_e($category['title'] ?? '') ?>" placeholder="Npr. Opća stomatologija" required>
                <input type="hidden" name="categories[<?= $index ?>][id]" value="<?= sc_e($category['id'] ?? '') ?>">
            </div>
            <button type="button" class="sc-btn sc-btn--danger sc-btn--small" data-remove-category>Ukloni kategoriju</button>
        </div>
        <div class="sc-table-scroll">
            <table class="sc-admin-table">
                <thead><tr><th>Naziv</th><th>Šifra</th><th>Cijena</th><th>Sidrena</th><th>Datum</th><th></th></tr></thead>
                <tbody data-items>
                    <?php foreach ($items as $itemIndex => $item): ?>
                        <?php sc_admin_item_row($index, (int) $itemIndex, is_array($item) ? $item : []); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="sc-btn sc-btn--secondary sc-btn--small" data-add-item>+ Dodaj novi</button>
    </section>
    <?php
}

if (!sc_is_installed()) {
    sc_admin_head('Instalacija');
    ?>
    <main class="sc-login">
        <form method="post" class="sc-panel">
            <h1>Pokrenite <?= sc_e((string) sc_config('name', 'Cjenik')) ?></h1>
            <p>Podaci se spremaju u lokalne JSON datoteke. Baza nije potrebna.</p>
            <?= sc_csrf_field() ?>
            <input type="hidden" name="action" value="install">
            <div class="sc-field"><label>Naziv firme</label><input name="merchant_name" required></div>
            <div class="sc-field"><label>OIB</label><input name="oib" inputmode="numeric"></div>
            <div class="sc-field"><label>Admin korisničko ime</label><input name="username" value="admin" required></div>
            <div class="sc-field"><label>Admin lozinka (najmanje 10 znakova)</label><input type="password" name="password" minlength="10" required></div>
            <?php if ($error !== ''): ?><div class="sc-alert sc-alert--error"><?= sc_e($error) ?></div><?php endif; ?>
            <button class="sc-btn sc-btn--primary">Instaliraj</button>
        </form>
    </main></body></html>
    <?php exit;
}

if (!sc_is_admin()) {
    sc_admin_head('Prijava');
    ?>
    <main class="sc-login">
        <form method="post" class="sc-panel">
            <h1><?= sc_e((string) sc_config('name', 'Cjenik')) ?></h1>
            <?= sc_csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div class="sc-field"><label>Korisničko ime</label><input name="username" autocomplete="username" required></div>
            <div class="sc-field"><label>Lozinka</label><input type="password" name="password" autocomplete="current-password" required></div>
            <?php if ($error !== ''): ?><div class="sc-alert sc-alert--error"><?= sc_e($error) ?></div><?php endif; ?>
            <button class="sc-btn sc-btn--primary">Prijava</button>
        </form>
    </main></body></html>
    <?php exit;
}

$catalogId = sc_slug((string) ($_GET['catalog'] ?? sc_config('catalog_default', 'main')));
$activeTab = (string) ($_GET['tab'] ?? 'postavke');
if (!in_array($activeTab, sc_admin_tabs(), true)) {
    $activeTab = 'postavke';
}
$data = sc_catalog_load($catalogId);
$settings = sc_settings();
$adminRecord = sc_admin_record();
$flash = sc_flash_take();
$catalogs = sc_catalog_list();
$localServer = PHP_SAPI === 'cli-server';
$catalogQuery = 'catalog=' . rawurlencode($catalogId);
$xmlUrl = $localServer
    ? sc_base_url('cjenik.php') . '?' . $catalogQuery . '&format=xml'
    : sc_base_url('cjenik.xml') . '?' . $catalogQuery;
$csvUrl = $localServer
    ? sc_base_url('cjenik.php') . '?' . $catalogQuery . '&format=csv'
    : sc_base_url('cjenik.csv') . '?' . $catalogQuery;
$jsonUrl = sc_base_url('cjenik.php') . '?' . $catalogQuery . '&format=json';
$archiveUrl = $localServer
    ? sc_base_url('cjenik.php') . '?' . $catalogQuery . '&view=archive'
    : sc_base_url('arhiva-cjenika/') . '?' . $catalogQuery;
$cronUrl = $xmlUrl . '&action=archive&token=' . rawurlencode((string) $settings['cron_token']);
$secureRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$embedUrl = ($secureRequest ? 'https' : 'http') . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'example.com')
    . sc_base_url('cjenik.php') . '?catalog=' . rawurlencode($catalogId);
$embedCode = sc_embed_code($embedUrl, $settings);
sc_admin_head('Administracija');
?>
<header class="sc-topbar">
    <div><strong><?= sc_e((string) sc_config('name', 'Cjenik')) ?></strong><span><?= sc_e($settings['merchant_name']) ?></span></div>
    <form method="post"><?= sc_csrf_field() ?><input type="hidden" name="action" value="logout"><button class="sc-btn sc-btn--ghost">Odjava</button></form>
</header>
<main class="sc-admin">
    <?php if ($flash): ?><div class="sc-alert sc-alert--<?= sc_e($flash['type']) ?>"><?= sc_e($flash['message']) ?></div><?php endif; ?>

    <div class="sc-toolbar">
        <form method="get" class="sc-inline">
            <input type="hidden" name="tab" value="<?= sc_e($activeTab) ?>">
            <label for="catalog">Odabrani cjenik</label>
            <select id="catalog" name="catalog" onchange="this.form.submit()">
                <?php foreach ($catalogs as $option): ?>
                    <option value="<?= sc_e($option['id']) ?>"<?= $option['id'] === $catalogId ? ' selected' : '' ?>><?= sc_e($option['title']) ?> (<?= sc_e($option['id']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button class="sc-btn sc-btn--secondary">Otvori</button>
        </form>
        <form method="post" class="sc-inline">
            <?= sc_csrf_field() ?>
            <input type="hidden" name="action" value="create_catalog">
            <input type="hidden" name="catalog" value="<?= sc_e($catalogId) ?>">
            <input type="hidden" name="return_tab" value="cjenik">
            <input name="new_catalog" placeholder="Naziv novog cjenika" required>
            <button class="sc-btn sc-btn--secondary">Novi cjenik</button>
        </form>
        <a class="sc-btn sc-btn--secondary" href="<?= sc_e(sc_base_url('cjenik.php')) ?>?catalog=<?= rawurlencode($catalogId) ?>" target="_blank" rel="noopener">Javni prikaz</a>
        <?php if (count($catalogs) > 1): ?>
            <form method="post" class="sc-inline" onsubmit="return confirm('Obrisati cjenik <?= sc_e($data['title']) ?>? Stavke i arhiva ovog cjenika bit će uklonjeni.');">
                <?= sc_csrf_field() ?>
                <input type="hidden" name="action" value="delete_catalog">
                <input type="hidden" name="catalog" value="<?= sc_e($catalogId) ?>">
                <input type="hidden" name="return_tab" value="postavke">
                <button class="sc-btn sc-btn--danger">Obriši cjenik</button>
            </form>
        <?php endif; ?>
    </div>
    <p class="sc-current-catalog">
        Uređujete: <strong><?= sc_e($data['title']) ?></strong>
        <code>catalog=<?= sc_e($catalogId) ?></code>
    </p>

    <nav class="sc-tabs" data-tabs data-active-tab="<?= sc_e($activeTab) ?>" aria-label="Administracija">
        <button type="button" data-tab="postavke">Postavke</button>
        <button type="button" data-tab="izvozi">Izvozi i arhiva</button>
        <button type="button" data-tab="ugradnja">Ugradnja</button>
        <button type="button" data-tab="program">Upute</button>
        <button type="button" data-tab="racun">Korisnički račun</button>
        <button type="button" data-tab="cjenik">Cjenik</button>
    </nav>

    <form method="post" id="sc-catalog-form" data-tab-form>
        <?= sc_csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="catalog" value="<?= sc_e($catalogId) ?>">
        <input type="hidden" name="return_tab" value="<?= sc_e($activeTab) ?>" data-return-tab>

        <div data-tab-panel="postavke">
            <section class="sc-panel">
                <h2>Osnovne postavke</h2>
                <div class="sc-grid">
                    <div class="sc-field"><label>Naziv firme</label><input name="merchant_name" value="<?= sc_e($settings['merchant_name']) ?>" required></div>
                    <div class="sc-field"><label>OIB</label><input name="oib" value="<?= sc_e($settings['oib']) ?>"></div>
                    <div class="sc-field">
                        <label>Naziv odabranog cjenika</label>
                        <input name="title" value="<?= sc_e($data['title']) ?>" required>
                        <small class="sc-hint">Naziv se prikazuje u dropdownu, javnom prikazu i embedu. Slug <code><?= sc_e($catalogId) ?></code> ostaje nepromijenjen.</small>
                    </div>
                    <div class="sc-field"><label>Naziv prvog stupca</label><input name="item_label" value="<?= sc_e($data['item_label']) ?>" placeholder="Naziv" required></div>
                    <div class="sc-field"><label>Valuta</label><input name="currency" value="<?= sc_e($data['currency']) ?>" maxlength="3"></div>
                    <div class="sc-field"><label>Zadani datum sidrene cijene</label><input name="anchor_date" value="<?= sc_e(sc_format_date($data['anchor_date'])) ?>"></div>
                </div>
            </section>
            <section class="sc-panel">
                <h2>Izgled javnog cjenika</h2>
                <?php $theme = $settings['theme']; ?>
                <h3>Tipografija</h3>
                <div class="sc-grid">
                    <div class="sc-field">
                        <label>Font</label>
                        <?php $fontSelected = isset(sc_font_choices()[$theme['font_family']]); ?>
                        <select name="theme[font_family]">
                            <?php foreach (sc_font_choices() as $value => $label): ?>
                                <option value="<?= sc_e($value) ?>"<?= $theme['font_family'] === $value ? ' selected' : '' ?>><?= sc_e($label) ?></option>
                            <?php endforeach; ?>
                            <option value=""<?= $fontSelected ? '' : ' selected' ?>>Vlastiti font</option>
                        </select>
                    </div>
                    <div class="sc-field">
                        <label>Vlastiti font</label>
                        <input name="theme[font_custom]" value="<?= $fontSelected ? '' : sc_e($theme['font_family']) ?>" placeholder="npr. Calibri, sans-serif">
                    </div>
                    <div class="sc-field"><label>Veličina teksta</label><input name="theme[font_size]" value="<?= sc_e($theme['font_size']) ?>" placeholder="15px"><small class="sc-hint">Nazivi i cijene u tablici.</small></div>
                    <div class="sc-field"><label>Veličina naslova</label><input name="theme[title_size]" value="<?= sc_e($theme['title_size']) ?>" placeholder="32px"></div>
                    <div class="sc-field"><label>Veličina kategorije</label><input name="theme[category_size]" value="<?= sc_e($theme['category_size']) ?>" placeholder="16px"></div>
                    <div class="sc-field"><label>Veličina zaglavlja tablice</label><input name="theme[table_size]" value="<?= sc_e($theme['table_size']) ?>" placeholder="12px"><small class="sc-hint">Natpisi stupaca: Naziv, Cijena…</small></div>
                    <div class="sc-field"><label>Veličina poveznica</label><input name="theme[link_size]" value="<?= sc_e($theme['link_size']) ?>" placeholder="13px"></div>
                </div>
                <h3>Boje</h3>
                <?php
                $paletteId = sc_theme_palette_id($theme);
                $palettes = sc_theme_palettes();
                $paletteJson = [];
                foreach ($palettes as $id => $palette) {
                    $paletteJson[$id] = array_intersect_key($palette, array_flip(sc_theme_color_keys()));
                }
                ?>
                <div class="sc-field">
                    <label>Predložak boja</label>
                    <select name="theme[palette]" data-palette-select data-palettes="<?= sc_e(json_encode($paletteJson, JSON_UNESCAPED_UNICODE)) ?>">
                        <?php foreach ($palettes as $id => $palette): ?>
                            <option value="<?= sc_e($id) ?>"<?= $paletteId === $id ? ' selected' : '' ?>><?= sc_e($palette['label']) ?></option>
                        <?php endforeach; ?>
                        <option value="custom"<?= $paletteId === 'custom' ? ' selected' : '' ?>>Prilagođeno</option>
                    </select>
                    <small class="sc-hint">Deset predložaka. <strong>Prilagođeno</strong> otvara ručno mijenjanje svake boje. Enc IT je zadani izgled, isti kao admin.</small>
                </div>
                <div class="sc-palette-swatches" data-palette-swatches>
                    <?php foreach ($palettes as $id => $palette): ?>
                        <button type="button" class="sc-palette-swatch<?= $paletteId === $id ? ' is-active' : '' ?>" data-apply-palette="<?= sc_e($id) ?>" title="<?= sc_e($palette['label']) ?>">
                            <span class="sc-palette-swatch-colors">
                                <span style="background:<?= sc_e($palette['background_color']) ?>"></span>
                                <span style="background:<?= sc_e($palette['category_bg']) ?>"></span>
                                <span style="background:<?= sc_e($palette['price_color']) ?>"></span>
                            </span>
                            <em><?= sc_e($palette['label']) ?></em>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="sc-grid" data-color-fields>
                    <div class="sc-field"><label>Glavna boja</label><input type="color" name="theme[accent_color]" value="<?= sc_e($theme['accent_color']) ?>" data-accent-master></div>
                    <div class="sc-field"><label>Boja teksta</label><input type="color" name="theme[text_color]" value="<?= sc_e($theme['text_color']) ?>"></div>
                    <div class="sc-field"><label>Boja pozadine</label><input type="color" name="theme[background_color]" value="<?= sc_e($theme['background_color']) ?>"></div>
                    <div class="sc-field"><label>Boja naslova</label><input type="color" name="theme[title_color]" value="<?= sc_e($theme['title_color']) ?>"></div>
                    <div class="sc-field"><label>Pozadina kategorije</label><input type="color" name="theme[category_bg]" value="<?= sc_e($theme['category_bg']) ?>"></div>
                    <div class="sc-field"><label>Tekst kategorije</label><input type="color" name="theme[category_text]" value="<?= sc_e($theme['category_text']) ?>"></div>
                    <div class="sc-field"><label>Boja cijene</label><input type="color" name="theme[price_color]" value="<?= sc_e($theme['price_color']) ?>"></div>
                    <div class="sc-field"><label>Boja sporednog teksta</label><input type="color" name="theme[muted_color]" value="<?= sc_e($theme['muted_color']) ?>"></div>
                    <div class="sc-field"><label>Pozadina zaglavlja tablice</label><input type="color" name="theme[table_head_bg]" value="<?= sc_e($theme['table_head_bg']) ?>"></div>
                    <div class="sc-field"><label>Tekst zaglavlja tablice</label><input type="color" name="theme[table_head_color]" value="<?= sc_e($theme['table_head_color']) ?>"></div>
                    <div class="sc-field"><label>Boja okvira</label><input type="color" name="theme[border_color]" value="<?= sc_e($theme['border_color']) ?>"></div>
                    <div class="sc-field"><label>Boja linija u tablici</label><input type="color" name="theme[row_border_color]" value="<?= sc_e($theme['row_border_color']) ?>"></div>
                </div>
                <h3>Okviri i razmaci</h3>
                <div class="sc-grid">
                    <div class="sc-field">
                        <label>Tip okvira</label>
                        <select name="theme[border_style]">
                            <?php foreach (['solid' => 'Puna crta', 'dashed' => 'Isprekidana', 'dotted' => 'Točkasta', 'double' => 'Dvostruka', 'none' => 'Bez okvira'] as $value => $label): ?>
                                <option value="<?= sc_e($value) ?>"<?= $theme['border_style'] === $value ? ' selected' : '' ?>><?= sc_e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sc-field"><label>Debljina okvira</label><input name="theme[border_width]" value="<?= sc_e($theme['border_width']) ?>" placeholder="1px"></div>
                    <div class="sc-field"><label>Zaobljenje</label><input name="theme[border_radius]" value="<?= sc_e($theme['border_radius']) ?>" placeholder="8px"></div>
                    <div class="sc-field"><label>Širina cjenika</label><input name="theme[page_width]" value="<?= sc_e($theme['page_width']) ?>" placeholder="1180px"></div>
                    <div class="sc-field"><label>Padding stranice</label><input name="theme[page_padding]" value="<?= sc_e($theme['page_padding']) ?>" placeholder="28px 22px"></div>
                    <div class="sc-field"><label>Padding kategorije</label><input name="theme[category_padding]" value="<?= sc_e($theme['category_padding']) ?>" placeholder="10px 14px"></div>
                    <div class="sc-field"><label>Padding ćelija</label><input name="theme[table_padding]" value="<?= sc_e($theme['table_padding']) ?>" placeholder="10px 12px"></div>
                </div>
                <div class="sc-field">
                    <label>Vlastiti CSS javnog cjenika</label>
                    <textarea name="custom_css" rows="8" placeholder=".sc-public-head h1 { letter-spacing: 0.02em; }"><?= sc_e($settings['custom_css']) ?></textarea>
                    <small class="sc-hint">Opcionalno. Za sve što nije pokriveno poljima iznad, npr. <code>.sc-search input { border-radius: 20px; }</code>. Prazno ostavlja izgled iz postavki.</small>
                </div>
                <h3>Potpis izdavača</h3>
                <label class="sc-check">
                    <input type="checkbox" name="show_credit" value="1"<?= !empty($settings['show_credit']) ? ' checked' : '' ?>>
                    Prikaži u podnožju javnog cjenika: <em>sidrene cijene by Enc IT d.o.o.</em>
                </label>
                <p class="sc-hint">Ako isključite, potpis se ne prikazuje ni na javnoj stranici ni u ugradnji (iframe).</p>
                <h3>Ugradnja u host stranicu</h3>
                <div class="sc-field">
                    <label for="embed_host_layout">Layout host CMS-a</label>
                    <select id="embed_host_layout" name="embed_host_layout">
                        <option value="default"<?= sc_embed_host_layout($settings) === 'default' ? ' selected' : '' ?>>Standard (ProcessWire, WordPress, običan HTML…)</option>
                        <option value="shape5"<?= sc_embed_host_layout($settings) === 'shape5' ? ' selected' : '' ?>>Joomla Shape5 (skrij desni stupac)</option>
                    </select>
                    <small class="sc-hint">
                        Shape5 CSS ide u embed kod samo ako je ovo uključeno (npr. bikefix53).
                        Na ostalim siteovima ostavite Standard — inače se u kod kopiraju suvišni <code>#s5_*</code> selektori.
                        Nakon promjene ponovno kopirajte embed iz kartice Ugradnja.
                    </small>
                </div>
            </section>
        </div>

        <div data-tab-panel="cjenik">
            <div id="sc-categories">
                <?php foreach ($data['categories'] as $index => $category): ?>
                    <?php sc_admin_category((int) $index, $category); ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="sc-btn sc-btn--secondary" data-add-category>+ Dodaj kategoriju</button>

            <section class="sc-panel">
                <h2>Dodatni sadržaj</h2>
                <div class="sc-field">
                    <label>Tekst i slike ispod cjenika</label>
                    <textarea id="sc-footer-editor" name="footer_html" rows="8"><?= sc_e($data['footer_html']) ?></textarea>
                    <p><a class="sc-btn sc-btn--secondary sc-btn--small" href="<?= sc_e(sc_base_url('filemanager.php')) ?>?picker=1" target="scTinyFileManager">Otvori Tiny File Manager</a></p>
                </div>
                <h3>Ikone plaćanja</h3>
                <div data-payment-icons>
                    <?php foreach ($data['payment_icons'] as $index => $icon): ?>
                        <div class="sc-icon-row" data-payment-icon>
                            <input name="payment_icons[<?= $index ?>][image]" value="<?= sc_e($icon['image']) ?>" placeholder="URL slike">
                            <input name="payment_icons[<?= $index ?>][alt]" value="<?= sc_e($icon['alt']) ?>" placeholder="Naziv">
                            <button type="button" class="sc-btn sc-btn--danger sc-btn--small" data-remove-icon>Ukloni</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="sc-btn sc-btn--secondary sc-btn--small" data-add-icon>+ Dodaj ikonu</button>
            </section>
        </div>

        <div class="sc-savebar" data-savebar><button class="sc-btn sc-btn--primary">Spremi promjene</button></div>
    </form>

    <section class="sc-panel" data-tab-panel="izvozi">
        <h2>Izvozi i arhiva</h2>
        <p>
            <a href="<?= sc_e($xmlUrl) ?>">XML</a> ·
            <a href="<?= sc_e($csvUrl) ?>">CSV</a> ·
            <a href="<?= sc_e($jsonUrl) ?>">JSON</a> ·
            <a href="<?= sc_e($archiveUrl) ?>">Arhiva</a>
        </p>
        <div class="sc-grid">
            <div class="sc-field">
                <label>Cron token</label>
                <input readonly value="<?= sc_e((string) $settings['cron_token']) ?>" onclick="this.select()">
                <small class="sc-hint">Tajni ključ za HTTP cron. Nije za javnu stranicu. Kliknite polje da ga označite i kopirate.</small>
            </div>
        </div>
        <div class="sc-field">
            <label>HTTP cron URL</label>
            <input readonly value="<?= sc_e($cronUrl) ?>" onclick="this.select()">
            <small class="sc-hint">Koristi se samo ako hosting nema CLI cron. Token je već u ovom URL-u.</small>
        </div>
        <form method="post"><?= sc_csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="catalog" value="<?= sc_e($catalogId) ?>"><input type="hidden" name="return_tab" value="izvozi"><button class="sc-btn sc-btn--secondary">Izradi arhivu sada</button></form>

        <hr class="sc-split">
        <h3>Uvoz CSV</h3>
        <p class="sc-hint">
            Prvi red nije proizvod — to su imena stupaca, da program zna koji je naziv, cijena, datum i kategorija.
            Redove ispod pišete proizvode. Odjeljivač može biti točka-zarez, zarez ili tab.
        </p>
        <pre class="sc-help-code">naziv;sifra;cijena;sidrena_cijena;sidrena_cijena_datum;kategorija
USB-C kabel 1 m;kab-001;6.90;6.90;10.09.2026;Računalna oprema
Bežični miš;mis-014;19.90;22.50;10.09.2026;Računalna oprema
A4 papir 500 listova;pap-500;4.50;4.50;10.09.2026;Uredski materijal</pre>
        <p class="sc-hint">Prihvaćaju se i stupci iz službenog izvoza: <code>marka</code>, <code>jedinica_mjere</code>, <code>valuta</code>, <code>dostupnost</code>, <code>opis</code>.</p>
        <form method="post" enctype="multipart/form-data">
            <?= sc_csrf_field() ?>
            <input type="hidden" name="action" value="import_csv">
            <input type="hidden" name="catalog" value="<?= sc_e($catalogId) ?>">
            <input type="hidden" name="return_tab" value="izvozi">
            <div class="sc-field">
                <label>Zalijepite CSV</label>
                <textarea name="csv_text" rows="10" placeholder="naziv;sifra;cijena;sidrena_cijena;sidrena_cijena_datum;kategorija"></textarea>
            </div>
            <div class="sc-grid">
                <div class="sc-field">
                    <label>Ili odaberite CSV datoteku</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv,text/plain">
                </div>
                <div class="sc-field">
                    <label>Način uvoza</label>
                    <select name="import_mode">
                        <option value="replace">Zamijeni stavke odabranog cjenika</option>
                        <option value="append">Dodaj / ažuriraj postojeće stavke</option>
                    </select>
                </div>
            </div>
            <button class="sc-btn sc-btn--primary">Uvezi u cjenik</button>
        </form>
    </section>

    <section class="sc-panel" data-tab-panel="ugradnja" data-embed-builder data-base-url="<?= sc_e($embedUrl) ?>" data-embed-style="<?= sc_e(sc_embed_style($settings)) ?>" data-embed-parent-css="<?= sc_e(sc_embed_parent_css($settings)) ?>">
        <h2>Ugradnja u web-stranicu</h2>
        <p>Odabrani cjenik: <strong><?= sc_e($data['title']) ?></strong> · parametar <code>catalog=<?= sc_e($catalogId) ?></code></p>
        <p class="sc-hint">
            Svaki cjenik poziva se svojim slugom. Primjer:
            <code><?= sc_e(sc_base_url('cjenik.php')) ?>?catalog=<?= sc_e($catalogId) ?></code>.
            Isti parametar koristi se za XML, CSV, cron i arhivu; arhive različitih cjenika potpuno su odvojene.
        </p>
        <div class="sc-grid">
            <div class="sc-field"><label>Slug cjenika</label><input readonly value="<?= sc_e($catalogId) ?>" onclick="this.select()"></div>
            <div class="sc-field"><label>Javni URL</label><input readonly value="<?= sc_e($embedUrl) ?>" onclick="this.select()" data-embed-url></div>
            <div class="sc-field"><label>Arhiva ovog cjenika</label><input readonly value="<?= sc_e($archiveUrl) ?>" onclick="this.select()"></div>
        </div>
        <fieldset class="sc-embed-categories">
            <legend>Kategorije za prikaz</legend>
            <p class="sc-hint">
                Bez odabira prikazuje se kompletan cjenik. Označite jednu ili više kategorija za djelomični prikaz.
                Ručni format parametra: <code>&amp;categories=slug-kategorije-1,slug-kategorije-2</code>.
            </p>
            <div class="sc-check-grid">
                <?php foreach ($data['categories'] as $category): ?>
                    <label class="sc-check">
                        <input type="checkbox" value="<?= sc_e($category['id']) ?>" data-embed-category>
                        <?= sc_e($category['title']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <p class="sc-hint">
            Kopirajte kod ispod u Joomla Custom HTML modul, WordPress Custom HTML blok ili običnu HTML/PHP stranicu.
            Font, boje, okviri i vlastiti CSS uzimaju se iz Postavki unutar cjenika — nije ih potrebno ponovno pisati ovdje.
            Trenutačni host layout: <strong><?= sc_embed_host_layout($settings) === 'shape5' ? 'Joomla Shape5' : 'Standard' ?></strong>
            (mijenja se u Postavkama → Ugradnja u host stranicu).
            Širina iframea prati polje <strong>Širina cjenika</strong> (sada <?= sc_e((string) $settings['theme']['page_width']) ?>); na užem ekranu se i dalje smanjuje na 100%.
        </p>
        <div class="sc-field">
            <label>Responsivni embed kod</label>
            <textarea rows="7" readonly onclick="this.select()" data-embed-code><?= sc_e($embedCode) ?></textarea>
        </div>
    </section>

    <section class="sc-panel sc-help" data-tab-panel="program">
        <header class="sc-help-hero">
            <p class="sc-help-kicker">Upute</p>
            <h2><?= sc_e((string) sc_config('name')) ?></h2>
            <p class="sc-help-lead">
                Samostalni modul za objavu cjenika i sidrenih cijena. Radi bez baze i bez CMS-a.
                Podaci se spremaju u JSON datoteke na poslužitelju.
            </p>
            <p class="sc-help-meta">
                <span>Verzija <?= sc_e((string) sc_config('version', '1.0.0')) ?></span>
                <span>Izrada: <a href="<?= sc_e((string) sc_config('vendor_url', 'https://www.enc-it.hr/')) ?>" target="_blank" rel="noopener"><?= sc_e((string) sc_config('vendor', 'Enc IT d.o.o.')) ?></a></span>
            </p>
            <aside class="sc-help-reqs">
                <h3>System requirements</h3>
                <ul>
                    <li>PHP 7.4 ili noviji (preporuka 8.2 / 8.3)</li>
                    <li>ekstenzije <code>json</code>, <code>mbstring</code>, <code>session</code></li>
                    <li>zapisive mape <code>data/</code> (cjenici, arhive, backupi)</li>
                    <li>Apache <code>.htaccess</code> ili Nginx zabrana pristupa <code>data/</code>, <code>config/</code> i <code>includes/</code></li>
                    <li>nije potrebna baza niti CMS</li>
                </ul>
            </aside>
        </header>

        <article class="sc-help-block">
            <h3>Kako početi</h3>
            <ol class="sc-help-steps">
                <li>
                    <strong>Postavke</strong>
                    <span>Upišite naziv firme, OIB i naziv cjenika. Po želji podesite font, boje, širinu i okvire.</span>
                </li>
                <li>
                    <strong>Cjenik</strong>
                    <span>Dodajte kategorije i stavke: naziv, cijenu, sidrenu cijenu i datum sidrene. Spremi promjene.</span>
                </li>
                <li>
                    <strong>Objava</strong>
                    <span>Otvorite javni prikaz ili zalijepite kod iz kartice Ugradnja na svoju web-stranicu.</span>
                </li>
                <li>
                    <strong>Izvoz i arhiva</strong>
                    <span>Preuzmite XML ili CSV te postavite dnevni cron da arhiva ostane uredna.</span>
                </li>
            </ol>
        </article>

        <article class="sc-help-block">
            <h3>Kartice administracije</h3>
            <dl class="sc-help-dl">
                <div>
                    <dt>Postavke</dt>
                    <dd>Podaci o firmi, naziv cjenika i izgled javnog prikaza. Zadane boje su Enc IT (iste kao admin). Deset predložaka ili Prilagođeno za ručno mijenjanje svake boje. Ovdje se uključuje ili isključuje potpis Enc IT u podnožju.</dd>
                </div>
                <div>
                    <dt>Cjenik</dt>
                    <dd>Kategorije, stavke, tekst ispod cjenika i ikone plaćanja. Šifra je opcionalna i ide samo u XML/CSV — posjetitelji je ne vide.</dd>
                </div>
                <div>
                    <dt>Izvozi i arhiva</dt>
                    <dd>XML, CSV, JSON, 30-dnevna arhiva, cron token te uvoz CSV-a (datoteka ili copy-paste). Svaki cjenik ima vlastitu arhivu.</dd>
                </div>
                <div>
                    <dt>Ugradnja</dt>
                    <dd>Gotov iframe za cijeli cjenik ili samo odabrane kategorije.</dd>
                </div>
                <div>
                    <dt>Korisnički račun</dt>
                    <dd>Promjena korisničkog imena i lozinke.</dd>
                </div>
            </dl>
        </article>

        <article class="sc-help-block">
            <h3>Više cjenika</h3>
            <p>
                U gornjoj traci odaberite postojeći cjenik ili upišite naziv i kliknite <strong>Novi cjenik</strong>.
                Naziv se kasnije mijenja u Postavkama. Slug ostaje isti da se linkovi i ugradnja ne pokvare.
                Cjenik se briše gumbom <strong>Obriši cjenik</strong> — posljednji cjenik se ne može obrisati.
            </p>
            <p class="sc-help-note">Trenutačni slug: <code><?= sc_e($catalogId) ?></code> · URL parametar: <code>catalog=<?= sc_e($catalogId) ?></code></p>
        </article>

        <article class="sc-help-block">
            <h3>Uvoz CSV</h3>
            <p>
                U kartici <strong>Izvozi i arhiva</strong> zalijepite CSV ili učitajte datoteku.
                Prvi red nije proizvod — to su imena stupaca, da program zna što je naziv, cijena, datum i kategorija.
                Svi sljedeći redovi su stavke.
            </p>
            <p>
                Ako postoji zaglavlje, <strong>redoslijed stupaca nije bitan</strong>. Program čita imena, ne pozicije.
                Stupce koje ne trebate možete izostaviti. Obavezan je samo <code>naziv</code>.
                Bez zaglavlja pretpostavlja se fiksni redoslijed: naziv, cijena, sidrena cijena, datum, kategorija — taj redoslijed tada nemojte mijenjati.
            </p>
            <p>Odjeljivač može biti točka-zarez <code>;</code>, zarez ili tab. Preporuka je točka-zarez.</p>
            <dl class="sc-help-dl">
                <div>
                    <dt>naziv</dt>
                    <dd>Ime stavke. Obavezno.</dd>
                </div>
                <div>
                    <dt>sifra</dt>
                    <dd>Interna oznaka. Na javnom cjeniku se ne vidi. Može ostati prazna.</dd>
                </div>
                <div>
                    <dt>cijena</dt>
                    <dd>Važeća cijena, npr. <code>19.90</code> ili <code>Besplatno</code>.</dd>
                </div>
                <div>
                    <dt>sidrena_cijena</dt>
                    <dd>Sidrena (dodatna) cijena. Ako je prazna, koristi se važeća cijena.</dd>
                </div>
                <div>
                    <dt>sidrena_cijena_datum</dt>
                    <dd>Datum sidrene, npr. <code>10.09.2026</code> ili <code>2026-09-10</code>.</dd>
                </div>
                <div>
                    <dt>kategorija</dt>
                    <dd>Grupa u cjeniku. Ako nedostaje, stavka ide u <em>Opće</em>.</dd>
                </div>
            </dl>
            <p>Prihvaćaju se i stupci iz službenog izvoza: <code>marka</code>, <code>jedinica_mjere</code>, <code>valuta</code>, <code>dostupnost</code>, <code>opis</code>. Engleski nazivi također rade, npr. <code>name</code>, <code>price</code>, <code>category</code>.</p>
            <p>Način uvoza:</p>
            <ul class="sc-help-plain">
                <li><strong>Zamijeni stavke</strong> — obriše postojeće kategorije odabranog cjenika i uveze CSV.</li>
                <li><strong>Dodaj / ažuriraj</strong> — zadrži postojeće; iste šifre ili nazivi u istoj kategoriji se ažuriraju, nove se dodaju.</li>
            </ul>
            <pre class="sc-help-code">naziv;sifra;cijena;sidrena_cijena;sidrena_cijena_datum;kategorija
USB-C kabel 1 m;kab-001;6.90;6.90;10.09.2026;Računalna oprema
Bežični miš;mis-014;19.90;22.50;10.09.2026;Računalna oprema
A4 papir 500 listova;pap-500;4.50;4.50;10.09.2026;Uredski materijal</pre>
            <p class="sc-help-note">Isti podaci s drugim redoslijedom stupaca također prolaze, npr. <code>kategorija;naziv;cijena;sidrena_cijena;sidrena_cijena_datum;sifra</code>.</p>
        </article>

        <article class="sc-help-block">
            <h3>Linkovi ovog cjenika</h3>
            <ul class="sc-help-urls">
                <li><span>Admin</span><a href="<?= sc_e(sc_base_url('admin.php')) ?>?catalog=<?= rawurlencode($catalogId) ?>"><?= sc_e(sc_base_url('admin.php')) ?></a></li>
                <li><span>Javni prikaz</span><a href="<?= sc_e(sc_base_url('cjenik.php')) ?>?catalog=<?= rawurlencode($catalogId) ?>" target="_blank" rel="noopener"><?= sc_e(sc_base_url('cjenik.php')) ?>?catalog=<?= sc_e($catalogId) ?></a></li>
                <li><span>XML</span><a href="<?= sc_e($xmlUrl) ?>" target="_blank" rel="noopener"><?= sc_e($xmlUrl) ?></a></li>
                <li><span>CSV</span><a href="<?= sc_e($csvUrl) ?>" target="_blank" rel="noopener"><?= sc_e($csvUrl) ?></a></li>
                <li><span>JSON</span><a href="<?= sc_e($jsonUrl) ?>" target="_blank" rel="noopener"><?= sc_e($jsonUrl) ?></a></li>
                <li><span>Arhiva</span><a href="<?= sc_e($archiveUrl) ?>" target="_blank" rel="noopener"><?= sc_e($archiveUrl) ?></a></li>
            </ul>
            <p class="sc-help-note">Samo određene kategorije: dodajte <code>&amp;categories=slug-1,slug-2</code> na javni URL. Kod se sam gradi u kartici Ugradnja.</p>
        </article>

        <article class="sc-help-block">
            <h3>Ugradnja na web-stranicu</h3>
            <p>
                Kopirajte iframe iz kartice <strong>Ugradnja</strong> u WordPress Custom HTML blok, Joomla Custom HTML modul ili običnu HTML stranicu.
                Visina se prilagođava sama, bez unutarnjeg scrollbara.
            </p>
            <ul class="sc-help-plain">
                <li>Font, boje, okviri i vlastiti CSS dolaze iz Postavki. Ne pišu se u embed kod.</li>
                <li>Embed kod centrira cjenik na stranici predloška; nije potrebna izmjena template CSS-a.</li>
                <li>Širina iframea prati polje <strong>Širina cjenika</strong>. Ako je promijenite, kopirajte kod ponovno.</li>
                <li>Ostale izmjene izgleda vide se odmah, bez nove ugradnje.</li>
            </ul>
        </article>

        <article class="sc-help-block">
            <h3>Dnevna arhiva</h3>
            <p>Najbolje je pokretati arhivu preko CLI crona, bez izlaganja tokena na webu:</p>
            <pre class="sc-help-code">45 6 * * * /usr/bin/php <?= sc_e(sc_cli_archive_path()) ?> <?= sc_e($catalogId) ?> >/dev/null 2>&amp;1</pre>
            <p>Ako hosting nema CLI, koristite HTTP URL i token iz kartice <strong>Izvozi i arhiva</strong>.</p>
            <p class="sc-help-note">Token: <code><?= sc_e((string) $settings['cron_token']) ?></code></p>
            <pre class="sc-help-code"><?= sc_e($cronUrl) ?></pre>
            <p class="sc-help-note">Čuva se XML i CSV, jedan zapis po danu, <?= (int) sc_config('archive_days', 30) ?> dana.</p>
        </article>

        <article class="sc-help-block">
            <h3>Sidrene cijene i propisi</h3>
            <p>
                Od 1. listopada 2026. trgovci i pružatelji usluga sa web-stranicama objavljuju cjenik u strojno čitljivom XML ili CSV obliku
                te uz važeću cijenu ističu sidrenu (dodatnu) cijenu. Ovaj program priprema javni prikaz, izvoz i arhivu.
            </p>
            <p class="sc-help-note">Ovo nije pravni savjet. Uvijek provjerite važeći tekst odluka.</p>
            <ul class="sc-help-docs">
                <li><a href="https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html" target="_blank" rel="noopener">NN 101/2026 — Odluka o objavi cjenika</a></li>
                <li><a href="https://www.zakon.hr/c/podzakonski-propis/1420946/nn-101-2026-%2811.9.2026.%29%2C-odluka-o-objavi-cjenika-proizvoda-i-usluga-kao-mjera-izravne-kontrole-cijena" target="_blank" rel="noopener">Isti tekst na Zakon.hr</a></li>
                <li><a href="https://www.iusinfo.hr/aktualno/u-sredistu/sidrena-cijena-od-1-listopada-2026-nova-pravila-za-sve-trgovce-i-pruzatelje-usluga-70717" target="_blank" rel="noopener">IUS-INFO: sidrena cijena od 1. listopada 2026.</a></li>
                <li><a href="https://www.rif.hr/obveza-isticanja-dodatne-sidrene-cijene-zatecene-na-dan-10-rujna-2026-te-obveza-objave-cjenika-proizvoda-i-usluga-u-trgovini-na-malo-i-pri-pruzanju-usluga/" target="_blank" rel="noopener">RIF / HZRFD: obveza sidrene cijene i objave cjenika</a></li>
                <li><a href="https://narodne-novine.nn.hr/" target="_blank" rel="noopener">Narodne novine</a></li>
            </ul>
        </article>

        <article class="sc-help-block">
            <h3>Podaci i sigurnost</h3>
            <ul class="sc-help-plain">
                <li>Sve datoteke su u mapi <code>data/</code>: cjenici, postavke, arhive, backupi i povijest cijena.</li>
                <li>Web ne smije moći otvoriti <code>data/</code>, <code>config/</code> i <code>includes/</code> (.htaccess ili Nginx).</li>
                <li>Lozinka se sprema kao hash u <code>data/admin.json</code>. Mijenja se u kartici Korisnički račun.</li>
            </ul>
        </article>

        <article class="sc-help-block sc-help-block--last">
            <h3>Podrška</h3>
            <div class="sc-help-contact">
                <p>
                    <strong><?= sc_e((string) sc_config('vendor', 'Enc IT d.o.o.')) ?></strong><br>
                    <a href="<?= sc_e((string) sc_config('vendor_url', 'https://www.enc-it.hr/')) ?>" target="_blank" rel="noopener"><?= sc_e((string) sc_config('vendor_url', 'https://www.enc-it.hr/')) ?></a>
                </p>
                <p>
                    <a href="mailto:<?= sc_e((string) sc_config('vendor_email', 'info@enc-it.hr')) ?>"><?= sc_e((string) sc_config('vendor_email', 'info@enc-it.hr')) ?></a><br>
                    <a href="tel:+385912018385"><?= sc_e((string) sc_config('vendor_phone', '+385 91 201 8385')) ?></a>
                </p>
            </div>
        </article>
    </section>

    <section class="sc-panel" data-tab-panel="racun">
        <h2>Pristupni podaci</h2>
        <form method="post">
            <?= sc_csrf_field() ?>
            <input type="hidden" name="action" value="change_credentials">
            <input type="hidden" name="catalog" value="<?= sc_e($catalogId) ?>">
            <input type="hidden" name="return_tab" value="racun">
            <div class="sc-grid">
                <div class="sc-field"><label>Korisničko ime</label><input name="new_username" value="<?= sc_e($adminRecord['username'] ?? 'admin') ?>" required></div>
                <div class="sc-field"><label>Trenutačna lozinka</label><input type="password" name="current_password" required></div>
                <div class="sc-field"><label>Nova lozinka</label><input type="password" name="new_password" minlength="10" placeholder="Prazno zadržava postojeću"></div>
            </div>
            <button class="sc-btn sc-btn--secondary">Promijeni pristupne podatke</button>
        </form>
    </section>
</main>

<template id="sc-category-template"><?php sc_admin_category(0, ['title' => '', 'id' => '', 'items' => []]); ?></template>
<template id="sc-item-template"><?php sc_admin_item_row(0, 0, ['name' => '', 'id' => '', 'active' => true, 'anchor_date' => date('Y-m-d')]); ?></template>
<template id="sc-icon-template"><div class="sc-icon-row" data-payment-icon><input name="payment_icons[0][image]" placeholder="URL slike"><input name="payment_icons[0][alt]" placeholder="Naziv"><button type="button" class="sc-btn sc-btn--danger sc-btn--small" data-remove-icon>Ukloni</button></div></template>
<script src="<?= sc_e(sc_asset_url('admin.js')) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.0/tinymce.min.js"></script>
<script src="<?= sc_e(sc_asset_url('editor.js')) ?>"></script>
</body>
</html>
