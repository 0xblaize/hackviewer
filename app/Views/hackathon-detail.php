<?php
$registrationLink = null;
$rulesLink = null;
foreach ($links as $link) {
    if (($link['kind'] ?? '') === 'registration' && $registrationLink === null) {
        $registrationLink = $link;
    }
    if (($link['kind'] ?? '') === 'rules' && $rulesLink === null) {
        $rulesLink = $link;
    }
}
$applyUrl = $registrationLink['url'] ?? $hackathon['official_url'];
$formatDate = static fn (?string $value): string => $value ? gmdate('M j, Y · H:i', strtotime($value)) . ' UTC' : 'Not reported';
ob_start();
?>
<section class="detail-page">
    <a class="back-link" href="/">← Back to opportunities</a>
    <div class="detail-header"><div><span class="eyebrow"><?= e($hackathon['source_name'] ?: $hackathon['platform_name'] ?: 'Source pending') ?></span><h1><?= e($hackathon['title']) ?></h1><p class="detail-organizer"><?= e($hackathon['organizer_name'] ?: 'Organizer not reported') ?></p></div><span class="status-pill status-<?= e($countdown->status($hackathon['end_at_utc'])) ?>"><?= e(ucfirst($hackathon['status'])) ?></span></div>
    <div class="detail-layout">
        <div class="detail-main">
            <div class="countdown-panel"><span class="eyebrow">TIME REMAINING</span><strong data-deadline="<?= e($hackathon['end_at_utc']) ?>"><?= e($countdown->label($hackathon['end_at_utc'])) ?></strong><span><?= $hackathon['end_at_utc'] ? 'Event/submission end · ' . e($formatDate($hackathon['end_at_utc'])) : 'Deadline not reported' ?></span></div>
            <div class="detail-section application-panel"><span class="eyebrow">HOW TO APPLY</span><p class="section-intro">Use the official event page as the final authority. This checklist is general guidance; exact eligibility and submission requirements come from the event source.</p><ol class="application-steps"><li><strong>Open the official application page.</strong><span>Use the registration link when available, otherwise open the official event website.</span></li><li><strong>Read eligibility and rules.</strong><span>Check participation requirements, judging criteria, and prize terms before starting.</span></li><li><strong>Prepare your submission.</strong><span>Follow the official instructions for the project, repository, demo, or other required materials.</span></li><li><strong>Submit before the deadline.</strong><span>Registration deadline: <?= e($formatDate($hackathon['registration_deadline_utc'])) ?>. Confirm the timezone on the official page.</span></li><li><strong>Keep your confirmation.</strong><span>Save your registration or submission confirmation for future reference.</span></li></ol><div class="detail-actions"><a class="button button-primary" href="<?= e($applyUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $registrationLink ? 'Apply / register ↗' : 'Open official website ↗' ?></a><?php if ($rulesLink): ?><a class="button button-secondary" href="<?= e($rulesLink['url']) ?>" target="_blank" rel="noopener noreferrer">Read rules ↗</a><?php endif; ?></div></div>
            <div class="detail-section"><span class="eyebrow">EVENT TIMELINE</span><div class="event-timeline"><div><span>Starts</span><strong><?= e($formatDate($hackathon['start_at_utc'])) ?></strong></div><div><span>Register by</span><strong><?= e($formatDate($hackathon['registration_deadline_utc'])) ?></strong></div><div><span>Ends</span><strong><?= e($formatDate($hackathon['end_at_utc'])) ?></strong></div></div></div>
            <div class="detail-section"><span class="eyebrow">ABOUT THIS OPPORTUNITY</span><p><?= e($hackathon['description'] ?: 'The source has not provided a longer description yet.') ?></p></div>
            <div class="detail-section"><span class="eyebrow">BEFORE YOU SIGN UP</span><p><?= e($hackathon['what_to_know'] ?: 'Review the official rules, eligibility, judging criteria, prize terms, and submission requirements before signing up.') ?></p></div>
            <div class="detail-section"><span class="eyebrow">VERIFICATION NOTES</span><p><?= e($hackathon['legitimacy_notes'] ?: 'Use the official source link below as the authority.') ?></p></div>
        </div>
        <aside class="detail-aside"><div class="aside-panel"><span class="eyebrow">AT A GLANCE</span><dl><div><dt>Prize</dt><dd><?= e($hackathon['prize_text'] ?: 'Not reported') ?></dd></div><div><dt>Registration</dt><dd><?= e($formatDate($hackathon['registration_deadline_utc'])) ?></dd></div><div><dt>Participants</dt><dd><?= e($hackathon['participant_count'] !== null ? number_format((int) $hackathon['participant_count']) : 'Not reported') ?></dd></div><div><dt>Type</dt><dd><?= e($hackathon['hackathon_type'] ?: 'Not reported') ?></dd></div><div><dt>Format</dt><dd><?= e($hackathon['online_or_location'] ?: 'Not reported') ?></dd></div><div><dt>Location</dt><dd><?= e($hackathon['location_text'] ?: 'Not reported') ?></dd></div></dl></div><a class="button button-primary full-width" href="<?= e($applyUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $registrationLink ? 'Go to registration ↗' : 'Open official website ↗' ?></a><div class="aside-panel trust-panel"><span class="trust-icon">✓</span><div><strong><?= e(ucfirst($hackathon['verification_status'])) ?></strong><span>Source status for this listing</span></div></div></aside>
    </div>
    <?php if ($checks): ?><div class="detail-section"><span class="eyebrow">VERIFICATION EVIDENCE</span><div class="source-links"><?php foreach ($checks as $check): ?><div><strong><?= e(ucfirst($check['result'])) ?></strong> · <?= e($check['check_type']) ?><?php if (!empty($check['evidence_excerpt'])): ?> · <?= e($check['evidence_excerpt']) ?><?php endif; ?></div><?php endforeach; ?></div></div><?php endif; ?>
    <?php if ($links): ?><div class="detail-section"><span class="eyebrow">SOURCE LINKS</span><div class="source-links"><?php foreach ($links as $link): ?><a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['label'] ?: $link['kind']) ?> ↗</a><?php endforeach; ?></div></div><?php endif; ?>
</section>
<?php $content = ob_get_clean(); require appRoot() . '/app/Views/layout.php'; ?>
