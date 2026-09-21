<?php
declare(strict_types=1);

function sc_config(string $key, $default = null)
{
    return array_key_exists($key, $GLOBALS['sc_config']) ? $GLOBALS['sc_config'][$key] : $default;
}

function sc_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sc_base_url(string $path = ''): string
{
    $base = rtrim((string) sc_config('base_url', '/sc'), '/');
    return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function sc_cli_archive_path(): string
{
    $root = str_replace('\\', '/', rtrim(SC_ROOT, '/\\'));
    if ($root !== '' && isset($root[0]) && $root[0] === '/') {
        return $root . '/cli/archive.php';
    }
    $base = trim((string) sc_config('base_url', '/sc'), '/');
    return '/var/www/html' . ($base !== '' ? '/' . $base : '') . '/cli/archive.php';
}

function sc_asset_url(string $path): string
{
    $file = SC_ROOT . '/assets/' . ltrim($path, '/');
    return sc_base_url('assets/' . ltrim($path, '/'))
        . '?v=' . (string) (is_file($file) ? filemtime($file) : time());
}

function sc_slug(string $value): string
{
    $value = strtr($value, [
        'č' => 'c', 'ć' => 'c', 'ž' => 'z', 'š' => 's', 'đ' => 'd',
        'Č' => 'c', 'Ć' => 'c', 'Ž' => 'z', 'Š' => 's', 'Đ' => 'd',
    ]);
    $value = strtolower($value);
    $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim($value, '-');
    return $value !== '' ? $value : 'stavka';
}

function sc_data_path(string $relative): string
{
    $relative = str_replace('\\', '/', $relative);
    if (strpos($relative, '..') !== false || strpos($relative, "\0") !== false) {
        throw new RuntimeException('Neispravna putanja podataka.');
    }
    return SC_ROOT . '/data/' . ltrim($relative, '/');
}

function sc_ensure_directory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
        throw new RuntimeException('Nije moguće napraviti mapu: ' . $path);
    }
}

function sc_json_read(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return $default;
    }
    flock($handle, LOCK_SH);
    $json = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    $decoded = json_decode((string) $json, true);
    return is_array($decoded) ? $decoded : $default;
}

function sc_json_write(string $path, array $data, bool $backup = true): bool
{
    sc_ensure_directory(dirname($path));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    if ($backup && is_file($path)) {
        $backupDir = sc_data_path('backups');
        sc_ensure_directory($backupDir);
        $name = pathinfo($path, PATHINFO_FILENAME);
        @copy($path, $backupDir . '/' . $name . '-' . date('Ymd-His') . '.json');
    }
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0640);
    if (PHP_OS_FAMILY === 'Windows' && is_file($path)) {
        @unlink($path);
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function sc_font_choices(): array
{
    return [
        'Arial, Helvetica, sans-serif' => 'Arial',
        'Georgia, "Times New Roman", serif' => 'Georgia',
        '"Times New Roman", Times, serif' => 'Times New Roman',
        'Verdana, Geneva, sans-serif' => 'Verdana',
        'Tahoma, Geneva, sans-serif' => 'Tahoma',
        '"Trebuchet MS", Helvetica, sans-serif' => 'Trebuchet MS',
        '"Courier New", Courier, monospace' => 'Courier New',
        'system-ui, -apple-system, "Segoe UI", sans-serif' => 'System UI',
    ];
}

function sc_theme_color_keys(): array
{
    return [
        'accent_color', 'text_color', 'background_color', 'muted_color', 'title_color',
        'category_bg', 'category_text', 'price_color', 'table_head_bg', 'table_head_color',
        'border_color', 'row_border_color',
    ];
}

function sc_theme_palettes(): array
{
    return [
        'encit' => [
            'label' => 'Enc IT',
            'accent_color' => '#18233d',
            'text_color' => '#1c1914',
            'background_color' => '#f4efe6',
            'muted_color' => '#6a6258',
            'title_color' => '#18233d',
            'category_bg' => '#18233d',
            'category_text' => '#f7f1e6',
            'price_color' => '#b08948',
            'table_head_bg' => '#faf6ef',
            'table_head_color' => '#6a6258',
            'border_color' => '#e6ddd0',
            'row_border_color' => '#eee6da',
        ],
        'klasicna' => [
            'label' => 'Klasična plava',
            'accent_color' => '#263f96',
            'text_color' => '#202536',
            'background_color' => '#ffffff',
            'muted_color' => '#667085',
            'title_color' => '#263f96',
            'category_bg' => '#263f96',
            'category_text' => '#ffffff',
            'price_color' => '#263f96',
            'table_head_bg' => '#f5f6f8',
            'table_head_color' => '#50586a',
            'border_color' => '#dfe3e9',
            'row_border_color' => '#e7e9ee',
        ],
        'grafit' => [
            'label' => 'Grafit',
            'accent_color' => '#2f343a',
            'text_color' => '#22262b',
            'background_color' => '#f7f7f8',
            'muted_color' => '#6b7178',
            'title_color' => '#2f343a',
            'category_bg' => '#2f343a',
            'category_text' => '#f4f5f6',
            'price_color' => '#2f343a',
            'table_head_bg' => '#eceeef',
            'table_head_color' => '#5c636b',
            'border_color' => '#d5d8dc',
            'row_border_color' => '#e6e8eb',
        ],
        'suma' => [
            'label' => 'Šuma',
            'accent_color' => '#2f4f3a',
            'text_color' => '#243028',
            'background_color' => '#f4f7f2',
            'muted_color' => '#66756a',
            'title_color' => '#2f4f3a',
            'category_bg' => '#2f4f3a',
            'category_text' => '#f3f7f1',
            'price_color' => '#3d6b49',
            'table_head_bg' => '#e8efe6',
            'table_head_color' => '#4d5f52',
            'border_color' => '#d4dfd4',
            'row_border_color' => '#e3ebe2',
        ],
        'bordo' => [
            'label' => 'Bordo',
            'accent_color' => '#6b2d3c',
            'text_color' => '#2b1c20',
            'background_color' => '#fbf6f6',
            'muted_color' => '#7a5d64',
            'title_color' => '#6b2d3c',
            'category_bg' => '#6b2d3c',
            'category_text' => '#f8eeee',
            'price_color' => '#8a3d4e',
            'table_head_bg' => '#f3e8ea',
            'table_head_color' => '#6d4b52',
            'border_color' => '#e5d2d6',
            'row_border_color' => '#eee0e3',
        ],
        'ocean' => [
            'label' => 'Ocean',
            'accent_color' => '#1f4e5f',
            'text_color' => '#1c2e35',
            'background_color' => '#f3f7f8',
            'muted_color' => '#5f7278',
            'title_color' => '#1f4e5f',
            'category_bg' => '#1f4e5f',
            'category_text' => '#eef6f8',
            'price_color' => '#2a6b7c',
            'table_head_bg' => '#e4eef1',
            'table_head_color' => '#4d646c',
            'border_color' => '#cfdce0',
            'row_border_color' => '#dfe8eb',
        ],
        'terakota' => [
            'label' => 'Terakota',
            'accent_color' => '#8b4b2f',
            'text_color' => '#2c211b',
            'background_color' => '#fbf6f1',
            'muted_color' => '#7a665b',
            'title_color' => '#8b4b2f',
            'category_bg' => '#8b4b2f',
            'category_text' => '#fbf3ec',
            'price_color' => '#a45a38',
            'table_head_bg' => '#f3e8df',
            'table_head_color' => '#6e5649',
            'border_color' => '#e5d5c8',
            'row_border_color' => '#eee3d8',
        ],
        'maslina' => [
            'label' => 'Maslina',
            'accent_color' => '#5c5a32',
            'text_color' => '#2a2818',
            'background_color' => '#f7f6ef',
            'muted_color' => '#6f6c55',
            'title_color' => '#5c5a32',
            'category_bg' => '#5c5a32',
            'category_text' => '#f4f3e8',
            'price_color' => '#6e6b3c',
            'table_head_bg' => '#ecead8',
            'table_head_color' => '#5b5843',
            'border_color' => '#dbd7c2',
            'row_border_color' => '#e8e5d4',
        ],
        'ugalj' => [
            'label' => 'Ugalj',
            'accent_color' => '#d8c39a',
            'text_color' => '#efe8dc',
            'background_color' => '#161513',
            'muted_color' => '#a39886',
            'title_color' => '#f3ead8',
            'category_bg' => '#22201c',
            'category_text' => '#f3ead8',
            'price_color' => '#d8c39a',
            'table_head_bg' => '#22201c',
            'table_head_color' => '#cbbfa8',
            'border_color' => '#3a372f',
            'row_border_color' => '#2c2a25',
        ],
        'sampanjac' => [
            'label' => 'Šampanjac',
            'accent_color' => '#9a7b3c',
            'text_color' => '#2a2418',
            'background_color' => '#f8f3e8',
            'muted_color' => '#7a6d55',
            'title_color' => '#7d6230',
            'category_bg' => '#9a7b3c',
            'category_text' => '#fff8ea',
            'price_color' => '#8b6c2f',
            'table_head_bg' => '#f0e6d0',
            'table_head_color' => '#6b5a38',
            'border_color' => '#e3d4b4',
            'row_border_color' => '#eee4cd',
        ],
    ];
}

function sc_theme_defaults(): array
{
    $encit = sc_theme_palettes()['encit'];
    unset($encit['label']);
    return array_replace([
        'palette' => 'encit',
        'font_family' => 'Arial, Helvetica, sans-serif',
        'font_size' => '15px',
        'title_size' => '32px',
        'category_size' => '16px',
        'table_size' => '12px',
        'link_size' => '13px',
        'border_style' => 'solid',
        'border_width' => '1px',
        'border_radius' => '8px',
        'page_width' => '1180px',
        'page_padding' => '28px 22px',
        'category_padding' => '10px 14px',
        'table_padding' => '10px 12px',
    ], $encit);
}

function sc_theme_palette_id(array $theme): string
{
    $id = (string) ($theme['palette'] ?? 'encit');
    $palettes = sc_theme_palettes();
    if ($id !== 'custom' && isset($palettes[$id])) {
        foreach (sc_theme_color_keys() as $key) {
            if (strtolower((string) ($theme[$key] ?? '')) !== strtolower((string) $palettes[$id][$key])) {
                return 'custom';
            }
        }
        return $id;
    }
    return $id === 'custom' ? 'custom' : (isset($palettes[$id]) ? $id : 'custom');
}

function sc_sanitize_hex(string $value, string $default): string
{
    $value = trim($value);
    if (preg_match('/^#[0-9a-f]{6}$/i', $value)) {
        return strtolower($value);
    }
    if (preg_match('/^[0-9a-f]{6}$/i', $value)) {
        return '#' . strtolower($value);
    }
    return $default;
}

function sc_sanitize_css_size(string $value, string $default): string
{
    $value = strtolower(trim($value));
    $value = (string) preg_replace('/(\d+)px(\d+)px/', '$1$2px', $value);
    if ($value === '') {
        return $default;
    }
    $parts = preg_split('/\s+/', $value);
    if ($parts === false || $parts === [] || count($parts) > 4) {
        return $default;
    }
    $clean = [];
    foreach ($parts as $part) {
        if (preg_match('/^[0-9.]+$/', $part)) {
            $clean[] = $part . 'px';
            continue;
        }
        if (preg_match('/^[0-9.]+(?:px|em|rem|%)$/', $part)) {
            $clean[] = $part;
            continue;
        }
        return $default;
    }
    return implode(' ', $clean);
}

function sc_sanitize_font(string $value): string
{
    $value = trim($value);
    foreach (array_keys(sc_font_choices()) as $font) {
        if (strcasecmp($font, $value) === 0) {
            return $font;
        }
    }
    if ($value !== '' && preg_match('/^[a-zA-Z0-9\s\'",.\-]+$/', $value)) {
        return $value;
    }
    return 'Arial, Helvetica, sans-serif';
}

function sc_sanitize_border_style(string $value): string
{
    return in_array($value, ['none', 'solid', 'dashed', 'dotted', 'double'], true) ? $value : 'solid';
}

function sc_theme_from_post(array $post): array
{
    $raw = isset($post['theme']) && is_array($post['theme']) ? $post['theme'] : [];
    $defaults = sc_theme_defaults();
    $fontFamily = trim((string) ($raw['font_family'] ?? ''));
    $fontCustom = trim((string) ($raw['font_custom'] ?? ''));
    if ($fontFamily !== '' && isset(sc_font_choices()[$fontFamily])) {
        $font = $fontFamily;
    } elseif ($fontCustom !== '') {
        $font = $fontCustom;
    } else {
        $font = $defaults['font_family'];
    }
    $theme = [
        'font_family' => sc_sanitize_font($font),
        'border_style' => sc_sanitize_border_style((string) ($raw['border_style'] ?? $defaults['border_style'])),
    ];
    $palette = (string) ($raw['palette'] ?? 'custom');
    $palettes = sc_theme_palettes();
    if ($palette !== 'custom' && !isset($palettes[$palette])) {
        $palette = 'custom';
    }
    $theme['palette'] = $palette;
    foreach ([
        'font_size', 'title_size', 'category_size', 'table_size', 'link_size',
        'border_width', 'border_radius', 'page_width', 'page_padding',
        'category_padding', 'table_padding',
    ] as $key) {
        $theme[$key] = sc_sanitize_css_size((string) ($raw[$key] ?? ''), $defaults[$key]);
    }
    foreach (sc_theme_color_keys() as $key) {
        if ($palette !== 'custom' && isset($palettes[$palette][$key])) {
            $theme[$key] = $palettes[$palette][$key];
        } else {
            $theme[$key] = sc_sanitize_hex((string) ($raw[$key] ?? ''), $defaults[$key]);
        }
    }
    return array_replace($defaults, $theme);
}

function sc_theme_css(array $settings): string
{
    $theme = isset($settings['theme']) && is_array($settings['theme'])
        ? array_replace(sc_theme_defaults(), $settings['theme'])
        : sc_theme_defaults();
    $map = [
        '--sc-font' => $theme['font_family'],
        '--sc-font-size' => $theme['font_size'],
        '--sc-title-size' => $theme['title_size'],
        '--sc-category-size' => $theme['category_size'],
        '--sc-table-size' => $theme['table_size'],
        '--sc-link-size' => $theme['link_size'],
        '--sc-accent' => $theme['accent_color'],
        '--sc-text' => $theme['text_color'],
        '--sc-bg' => $theme['background_color'],
        '--sc-muted' => $theme['muted_color'],
        '--sc-title-color' => $theme['title_color'],
        '--sc-category-bg' => $theme['category_bg'],
        '--sc-category-text' => $theme['category_text'],
        '--sc-price' => $theme['price_color'],
        '--sc-table-head-bg' => $theme['table_head_bg'],
        '--sc-table-head-color' => $theme['table_head_color'],
        '--sc-border-color' => $theme['border_color'],
        '--sc-row-border' => $theme['row_border_color'],
        '--sc-border-style' => $theme['border_style'],
        '--sc-border-width' => $theme['border_width'],
        '--sc-radius' => $theme['border_radius'],
        '--sc-page-width' => $theme['page_width'],
        '--sc-page-padding' => $theme['page_padding'],
        '--sc-category-padding' => $theme['category_padding'],
        '--sc-table-padding' => $theme['table_padding'],
    ];
    $css = ':root{';
    foreach ($map as $name => $value) {
        $css .= $name . ':' . $value . ';';
    }
    return $css . '}';
}

function sc_settings(): array
{
    $stored = sc_json_read(sc_data_path('settings.json'));
    $settings = array_replace([
        'merchant_name' => '',
        'oib' => '',
        'catalog_title' => 'Cjenik',
        'accent_color' => '#18233d',
        'custom_css' => '',
        'show_credit' => true,
        'cron_token' => '',
        'theme' => [],
    ], $stored);
    $hadTheme = isset($stored['theme']) && is_array($stored['theme']) && $stored['theme'] !== [];
    $theme = array_replace(sc_theme_defaults(), $hadTheme ? $stored['theme'] : []);
    if (!$hadTheme) {
        $theme = array_replace($theme, sc_theme_palettes()['encit']);
        unset($theme['label']);
        $theme['palette'] = 'encit';
    }
    $defaults = sc_theme_defaults();
    foreach ([
        'font_size', 'title_size', 'category_size', 'table_size', 'link_size',
        'border_width', 'border_radius', 'page_width', 'page_padding',
        'category_padding', 'table_padding',
    ] as $key) {
        $theme[$key] = sc_sanitize_css_size((string) ($theme[$key] ?? ''), $defaults[$key]);
    }
    $settings['theme'] = $theme;
    $settings['accent_color'] = (string) $theme['accent_color'];
    return $settings;
}

function sc_admin_tabs(): array
{
    return ['postavke', 'izvozi', 'ugradnja', 'program', 'racun', 'cjenik'];
}

function sc_embed_width(array $settings): string
{
    return trim((string) (($settings['theme']['page_width'] ?? '') ?: '1180px'));
}

function sc_embed_parent_css(array $settings): string
{
    $width = sc_embed_width($settings);
    return '.sc-cjenik-embed{display:block;width:100%;max-width:' . $width . ';margin:0 auto 24px;}'
        . '#sc-cjenik,.sc-cjenik-embed iframe{display:block;width:100%;max-width:100%;margin:0 auto;border:0;}';
}

function sc_embed_style(array $settings): string
{
    return 'width:100%;max-width:' . sc_embed_width($settings) . ';min-height:650px;border:0;display:block;margin:0 auto';
}

function sc_embed_code(string $url, array $settings): string
{
    return '<style>' . sc_embed_parent_css($settings) . '</style>' . "\n"
        . '<div class="sc-cjenik-embed">'
        . '<iframe id="sc-cjenik" src="' . $url . '" title="Cjenik" loading="lazy" style="'
        . sc_embed_style($settings) . '"></iframe>'
        . '</div>' . "\n"
        . '<script>window.addEventListener("message",function(e){var f=document.getElementById("sc-cjenik");'
        . 'if(f&&e.source===f.contentWindow&&e.data&&e.data.type==="sc-cjenik-height"){'
        . 'f.style.height=Math.max(300,e.data.height)+"px";}});</script>';
}

function sc_save_settings(array $settings): bool
{
    if (empty($settings['cron_token'])) {
        $settings['cron_token'] = bin2hex(random_bytes(16));
    }
    return sc_json_write(sc_data_path('settings.json'), $settings);
}

function sc_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name((string) sc_config('session_name', 'sc_admin_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => sc_base_url() . '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function sc_csrf_token(): string
{
    sc_start_session();
    if (empty($_SESSION['sc_csrf'])) {
        $_SESSION['sc_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['sc_csrf'];
}

function sc_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . sc_e(sc_csrf_token()) . '">';
}

function sc_require_csrf(): void
{
    sc_start_session();
    $given = (string) ($_POST['csrf_token'] ?? '');
    $known = (string) ($_SESSION['sc_csrf'] ?? '');
    if ($known === '' || $given === '' || !hash_equals($known, $given)) {
        http_response_code(403);
        exit('Nevaljan CSRF token.');
    }
}

function sc_admin_record(): array
{
    return sc_json_read(sc_data_path('admin.json'));
}

function sc_is_installed(): bool
{
    $admin = sc_admin_record();
    return !empty($admin['password_hash']);
}

function sc_is_admin(): bool
{
    sc_start_session();
    return !empty($_SESSION['sc_admin']);
}

function sc_require_admin(): void
{
    if (!sc_is_admin()) {
        header('Location: ' . sc_base_url('admin.php'));
        exit;
    }
}

function sc_login(string $username, string $password): bool
{
    sc_start_session();
    $now = time();
    $blockedUntil = (int) ($_SESSION['sc_login_blocked_until'] ?? 0);
    if ($blockedUntil > $now) {
        return false;
    }
    $admin = sc_admin_record();
    $ok = hash_equals((string) ($admin['username'] ?? 'admin'), $username)
        && password_verify($password, (string) ($admin['password_hash'] ?? ''));
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['sc_admin'] = true;
        $_SESSION['sc_login_attempts'] = 0;
        sc_audit('login', ['username' => $username]);
        return true;
    }
    $attempts = (int) ($_SESSION['sc_login_attempts'] ?? 0) + 1;
    $_SESSION['sc_login_attempts'] = $attempts;
    if ($attempts >= 5) {
        $_SESSION['sc_login_blocked_until'] = $now + 300;
        $_SESSION['sc_login_attempts'] = 0;
    }
    return false;
}

function sc_logout(): void
{
    sc_start_session();
    $_SESSION = [];
    session_destroy();
}

function sc_flash(string $type, string $message): void
{
    sc_start_session();
    $_SESSION['sc_flash'] = ['type' => $type, 'message' => $message];
}

function sc_flash_take(): ?array
{
    sc_start_session();
    $flash = isset($_SESSION['sc_flash']) && is_array($_SESSION['sc_flash'])
        ? $_SESSION['sc_flash']
        : null;
    unset($_SESSION['sc_flash']);
    return $flash;
}

function sc_clean_html(string $html): string
{
    $html = strip_tags(
        $html,
        '<p><br><strong><b><em><i><u><a><ul><ol><li><small><h2><h3><h4><blockquote><figure><figcaption><img><table><thead><tbody><tr><th><td>'
    );
    $html = (string) preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = (string) preg_replace('/href\s*=\s*("|\\\')\s*javascript:[^"\\\']*\\1/i', 'href="#"', $html);
    $html = (string) preg_replace('/src\s*=\s*("|\\\')\s*javascript:[^"\\\']*\\1/i', 'src=""', $html);
    return $html;
}

function sc_clean_css(string $css): string
{
    $css = str_ireplace(['</style', '<script', '</script'], '', $css);
    $css = (string) preg_replace('/@import\b[^;]*;?/i', '', $css);
    $css = (string) preg_replace('/expression\s*\(|javascript\s*:/i', '', $css);
    return trim($css);
}

function sc_change_credentials(string $currentPassword, string $username, string $newPassword): bool
{
    $admin = sc_admin_record();
    if (!password_verify($currentPassword, (string) ($admin['password_hash'] ?? ''))) {
        return false;
    }
    $username = trim($username);
    if ($username === '') {
        return false;
    }
    if ($newPassword !== '' && strlen($newPassword) < 10) {
        return false;
    }
    $admin['username'] = $username;
    if ($newPassword !== '') {
        $admin['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    $admin['updated_at'] = date('c');
    $ok = sc_json_write(sc_data_path('admin.json'), $admin);
    if ($ok) {
        sc_audit('credentials.change', ['username' => $username]);
    }
    return $ok;
}

function sc_audit(string $action, array $context = []): void
{
    $record = [
        'time' => date('c'),
        'action' => $action,
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'),
        'context' => $context,
    ];
    $path = sc_data_path('audit.jsonl');
    sc_ensure_directory(dirname($path));
    @file_put_contents(
        $path,
        json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

function sc_send_no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function sc_media_list(): array
{
    $root = SC_ROOT . '/uploads';
    if (!is_dir($root)) {
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $file->getFilename())) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
        $files[] = [
            'name' => $file->getFilename(),
            'url' => sc_base_url('uploads' . $relative),
            'size' => $file->getSize(),
            'time' => $file->getMTime(),
        ];
    }
    usort($files, static function (array $a, array $b): int {
        return $b['time'] <=> $a['time'];
    });
    return $files;
}

function sc_media_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload datoteke nije uspio.');
    }
    if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException('Slika smije imati najviše 8 MB.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    $info = @getimagesize($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Dozvoljene su JPG, PNG, GIF i WebP slike.');
    }
    $dirRelative = date('Y/m');
    $dir = SC_ROOT . '/uploads/' . $dirRelative;
    sc_ensure_directory($dir);
    $base = sc_slug(pathinfo((string) ($file['name'] ?? 'slika'), PATHINFO_FILENAME));
    $name = $base . '-' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
    $destination = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('Spremanje slike nije uspjelo.');
    }
    @chmod($destination, 0640);
    sc_audit('media.upload', ['file' => $dirRelative . '/' . $name]);
    return [
        'name' => $name,
        'url' => sc_base_url('uploads/' . $dirRelative . '/' . $name),
    ];
}
