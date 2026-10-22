<?php /** @var string $pageTitle */ ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= esc($pageTitle) ?></title>
<?= view('App\Modules\Core\Views\partials\theme_boot') ?>
<link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
