<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
sc_send_no_cache_headers();
sc_require_admin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sc_require_csrf();
    try {
        $saved = sc_media_upload(isset($_FILES['file']) && is_array($_FILES['file']) ? $_FILES['file'] : []);
        if ((string) ($_POST['mode'] ?? '') === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['location' => $saved['url']], JSON_UNESCAPED_SLASHES);
            exit;
        }
        sc_flash('success', 'Slika je spremljena.');
        header('Location: ' . sc_base_url('filemanager.php') . '?picker=1');
        exit;
    } catch (Throwable $e) {
        if ((string) ($_POST['mode'] ?? '') === 'json') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $error = $e->getMessage();
    }
}

$files = sc_media_list();
$flash = sc_flash_take();
?><!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Tiny File Manager | <?= sc_e((string) sc_config('name', 'Cjenik')) ?></title>
    <link rel="stylesheet" href="<?= sc_e(sc_asset_url('admin.css')) ?>">
    <style>
        .sc-media{width:min(1100px,calc(100% - 24px));margin:20px auto}
        .sc-media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
        .sc-media-item{display:grid;gap:7px;padding:8px;border:1px solid #dde2ea;border-radius:8px;background:#fff;cursor:pointer;text-align:left}
        .sc-media-item img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:5px}
        .sc-media-item span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px}
    </style>
</head>
<body>
<main class="sc-media">
    <section class="sc-panel">
        <h1>Tiny File Manager</h1>
        <p class="sc-hint">Prenesite sliku ili odaberite postojeću za umetanje u tekst.</p>
        <?php if ($flash): ?><div class="sc-alert sc-alert--<?= sc_e($flash['type']) ?>"><?= sc_e($flash['message']) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="sc-alert sc-alert--error"><?= sc_e($error) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="sc-inline">
            <?= sc_csrf_field() ?>
            <input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp" required>
            <button class="sc-btn sc-btn--primary">Prenesi sliku</button>
        </form>
    </section>
    <div class="sc-media-grid">
        <?php foreach ($files as $file): ?>
            <button type="button" class="sc-media-item" data-url="<?= sc_e($file['url']) ?>">
                <img src="<?= sc_e($file['url']) ?>" alt="" loading="lazy">
                <span><?= sc_e($file['name']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</main>
<script>
document.addEventListener('click', function (event) {
  var item = event.target.closest('[data-url]');
  if (!item) return;
  if (window.opener) {
    window.opener.postMessage({type: 'sc-media-select', url: item.getAttribute('data-url')}, window.location.origin);
    window.close();
  }
});
</script>
</body>
</html>
