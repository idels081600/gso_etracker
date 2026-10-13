<?php

require_once __DIR__ . '/rice_release_batches.php';

function renderRiceBatchSwitcher(mysqli $conn, string $currentSource): void
{
    $items = [
        'first_wave' => [
            'ordinal' => '1ST',
            'title' => 'First Wave',
            'description' => 'Original recipient list',
            'href' => 'releasing_rice_first_wave.php',
            'tone' => 'first',
        ],
        'next_wave' => [
            'ordinal' => '2ND',
            'title' => 'Second Batch',
            'description' => 'Second distribution',
            'href' => 'releasing_rice_second_wave.php',
            'tone' => 'second',
        ],
        'third_wave' => [
            'ordinal' => '3RD',
            'title' => 'Third Batch',
            'description' => 'Third distribution',
            'href' => 'releasing_rice_third_wave.php',
            'tone' => 'third',
        ],
        'fourth_wave' => [
            'ordinal' => '4TH',
            'title' => 'Fourth Batch',
            'description' => 'Fourth distribution',
            'href' => 'releasing_rice_fourth_wave.php',
            'tone' => 'fourth',
        ],
    ];

    foreach ($items as $source => &$item) {
        $item['active'] = $source === 'first_wave' || riceReleaseBatchIsActive($conn, $source);
        $item['current'] = $source === $currentSource;
    }
    unset($item);

    $current = $items[$currentSource] ?? $items['third_wave'];
    ?>
    <section class="rice-batch-switcher-section" aria-labelledby="riceBatchSwitcherTitle">
        <div class="container">
            <div class="rice-batch-switcher" data-current-batch="<?php echo htmlspecialchars($current['tone']); ?>">
                <div class="rice-batch-switcher__heading">
                    <div>
                        <p class="rice-batch-switcher__eyebrow mb-1">Rice assistance releasing</p>
                        <h1 class="rice-batch-switcher__title mb-1" id="riceBatchSwitcherTitle">Select a distribution batch</h1>
                        <p class="rice-batch-switcher__help mb-0">Choose a batch below before searching or recording a claim.</p>
                    </div>
                    <div class="rice-current-batch rice-current-batch--<?php echo htmlspecialchars($current['tone']); ?>" aria-label="Current batch: <?php echo htmlspecialchars($current['title']); ?>">
                        <span>Currently in use</span>
                        <strong><?php echo htmlspecialchars($current['ordinal']); ?></strong>
                        <small><?php echo htmlspecialchars($current['title']); ?></small>
                    </div>
                </div>

                <nav class="rice-batch-options" aria-label="Rice distribution batch selection">
                    <?php foreach ($items as $source => $item): ?>
                        <a
                            class="rice-batch-option rice-batch-option--<?php echo htmlspecialchars($item['tone']); ?><?php echo $item['current'] ? ' is-current' : ''; ?><?php echo !$item['active'] ? ' is-locked' : ''; ?>"
                            href="<?php echo htmlspecialchars($item['href']); ?>"
                            <?php echo $item['current'] ? 'aria-current="page"' : ''; ?>
                        >
                            <span class="rice-batch-option__ordinal"><?php echo htmlspecialchars($item['ordinal']); ?></span>
                            <span class="rice-batch-option__copy">
                                <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                <small><?php echo htmlspecialchars($item['description']); ?></small>
                            </span>
                            <span class="rice-batch-option__state">
                                <?php if ($item['current']): ?>
                                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i> In use
                                <?php elseif (!$item['active']): ?>
                                    <i class="bi bi-lock-fill" aria-hidden="true"></i> Locked
                                <?php else: ?>
                                    Open
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </section>
    <?php
}
