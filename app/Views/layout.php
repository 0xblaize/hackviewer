<?php
$pageTitle = $pageTitle ?? 'Hackview';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A focused discovery dashboard for credible hackathons.">
    <title><?= e($pageTitle) ?> · Hackview</title>
    <link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <a class="brand" href="/" aria-label="Hackview home"><span class="hackview-logo"><svg class="hackview-logo-mark" viewBox="0 0 40 40" role="img" aria-hidden="true"><path d="M8 8h9v5h-4v14h4v5H8V8Zm24 0h-9v5h4v14h-4v5h9V8Z" fill="currentColor"></path><path d="M17 17h6v6h-6z" fill="var(--lime)"></path><path d="M20 3v6M20 31v6M3 20h6M31 20h6" stroke="var(--lime)" stroke-width="2" stroke-linecap="round"></path></svg><span class="hackview-logo-word">hackview<span>.</span></span></span></a>
            <div class="topbar-meta"><span class="live-dot"></span> Tracking verified opportunities <span class="meta-separator">·</span> <a href="/candidates">Review candidates</a> <span class="meta-separator">·</span> UTC</div>
        </header>
        <main><?= $content ?></main>
        <footer class="footer"><span><span class="hackview-logo hackview-logo-compact"><svg class="hackview-logo-mark" viewBox="0 0 40 40" aria-hidden="true"><path d="M8 8h9v5h-4v14h4v5H8V8Zm24 0h-9v5h4v14h-4v5h9V8Z" fill="currentColor"></path><path d="M17 17h6v6h-6z" fill="var(--lime)"></path><path d="M20 3v6M20 31v6M3 20h6M31 20h6" stroke="var(--lime)" stroke-width="2" stroke-linecap="round"></path></svg></span>Built for people who want to find the right room before it gets crowded.</span><span>Sources are always linked and freshness is visible.</span></footer>
    </div>
    <script src="/assets/app.js" defer></script>
</body>
</html>
