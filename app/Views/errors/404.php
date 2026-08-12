<?php ob_start(); ?>
<section class="empty-page"><span class="eyebrow">404</span><h1><?= e($pageTitle) ?></h1><p>That page is not available. Return to the discovery board to keep looking.</p><a class="button button-primary" href="/">Back to dashboard</a></section>
<?php $content = ob_get_clean(); require appRoot() . '/app/Views/layout.php'; ?>
