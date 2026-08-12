<article class="hack-card">
    <div class="card-topline"><span class="source-label"><?= e($item['source_name'] ?: $item['platform_name'] ?: 'Source pending') ?></span><span class="status-pill status-<?= e($countdown->status($item['end_at_utc'])) ?>"><?= e(ucfirst($item['status'])) ?></span></div>
    <h3><a href="/hackathons/<?= (int) $item['id'] ?>"><?= e($item['title']) ?></a></h3>
    <p class="card-description"><?= e($item['what_to_know'] ?: 'Review the official rules, eligibility, and submission requirements before signing up.') ?></p>
    <div class="countdown" data-deadline="<?= e($item['end_at_utc']) ?>"><span class="countdown-label">Time remaining</span><strong><?= e($countdown->label($item['end_at_utc'])) ?></strong></div>
    <div class="card-stats"><div><span>Prize</span><strong><?= e($item['prize_text'] ?: ($item['prize_amount_minor'] ? number_format(((int) $item['prize_amount_minor']) / 100, 0) . ' ' . ($item['prize_currency'] ?: '') : 'Not reported')) ?></strong></div><div><span>People</span><strong><?= e($item['participant_count'] !== null ? number_format((int) $item['participant_count']) : 'Not reported') ?></strong></div><div><span>Type</span><strong><?= e($item['hackathon_type'] ?: 'Not reported') ?></strong></div></div>
    <div class="card-footer"><span class="trust-label trust-<?= e($item['verification_status']) ?>"><span class="trust-icon">✓</span><?= e(ucfirst($item['verification_status'])) ?></span><a class="arrow-link" href="/hackathons/<?= (int) $item['id'] ?>">View details <span>↗</span></a></div>
</article>
