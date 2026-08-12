<?php
$pageTitle = $pageTitle ?? 'Hackview';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A focused discovery dashboard for credible hackathons.">
    <title><?= e($pageTitle) ?> · Hackview</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <a class="brand" href="/" aria-label="Hackview home"><span class="brand-mark">H</span><span>hackview<span class="brand-dot">.</span></span></a>
            <div class="topbar-meta"><span class="live-dot"></span> Tracking verified opportunities <span class="meta-separator">·</span> UTC</div>
        </header>
        <main><?= $content ?></main>
        <footer class="footer"><span>Built for people who want to find the right room before it gets crowded.</span><span>Sources are always linked and freshness is visible.</span></footer>
    </div>
    <script src="/assets/app.js" defer></script>
</body>
</html>
