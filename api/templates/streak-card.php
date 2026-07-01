<?php
/**
 * Template variables for the SVG Streak Stats card.
 *
 * @var array{
 *     totalContributionsText: string,
 *     currentStreakText: string,
 *     longestStreakText: string
 * } $cardLabels Associative array containing pre-translated and formatted column titles.
 *
 * @var array{
 *     totalContributionsRange: string,
 *     currentStreakRange: string,
 *     longestStreakRange: string
 * } $cardRanges Associative array containing pre-formatted and split date span strings for columns.
 *
 * @var array<string,string> $theme Colors and gradients mapping for the selected theme.
 * @var int $cardWidth Calculated responsive pixel width of the card.
 * @var int $cardHeight Calculated responsive pixel height of the card.
 * @var float|string $borderRadius Outer corner rounding radius.
 * @var int $rectWidth Width of the inner background rectangle (cardWidth - 1).
 * @var int $rectHeight Height of the inner background rectangle (cardHeight - 1).
 * @var string $direction Text layout directionality ("ltr" or "rtl").
 *
 * @var bool $disableAnimations Flag indicating whether animations are disabled.
 *
 * @var array<int> $barOffsets X coordinates for the column separator lines.
 * @var array<int> $barHeightOffsets Y start and end coordinates for the lines.
 *
 * @var int $totalContributionsOffset X position for the first column text.
 * @var string $totalContributions Formatted big number of total contributions.
 *
 * @var int $currentStreakOffset X position for the center column text.
 * @var array<int> $currentStreakHeightOffset Y positions for center column elements.
 * @var string $currentStreak Formatted big number of the current streak.
 *
 * @var int $longestStreakOffset X position for the last column text.
 * @var string $longestStreak Formatted big number of the longest streak.
 *
 * @var array<int> $sideColumnHeightOffsets Y positions for sile column elements.
 *
 * @var bool $hasExcludedDays Flag checking if any days are omitted from the streak.
 * @var int $excludedDaysOffset Calculated horizontal position for the note.
 * @var string $excludingDaysText Formatted footnote text listing all excluded days.
 */
?>
<svg
    xmlns='http://www.w3.org/2000/svg'
    xmlns:xlink='http://www.w3.org/1999/xlink'
    style='isolation: isolate'
    viewBox='0 0 <?= $cardWidth ?> <?= $cardHeight ?>'
    width='<?= $cardWidth ?>px'
    height='<?= $cardHeight ?>px'
    direction='<?= $direction ?>'
>
    <?php if (!$disableAnimations): ?>
        <style>
            @keyframes currstreak {
                0% { font-size: 3px; opacity: 0.2; }
                80% { font-size: 34px; opacity: 1; }
                100% { font-size: 28px; opacity: 1; }
            }

            @keyframes fadein {
                0% { opacity: 0; }
                100% { opacity: 1; }
            }
        </style>
    <?php endif; ?>

    <defs>
        <clipPath id='outer_rectangle'>
            <rect width='<?= $cardWidth ?>' height='<?= $cardHeight ?>' rx='<?= $borderRadius ?>'/>
        </clipPath>

        <mask id='mask_out_ring_behind_fire'>
            <rect width='<?= $cardWidth ?>' height='<?= $cardHeight ?>' fill='white'/>

            <ellipse
                id='mask-ellipse'
                cx='<?= $currentStreakOffset ?>'
                cy='32'
                rx='13'
                ry='18'
                fill='black'
            />
        </mask>

        <?= $theme["backgroundGradient"] ?>
    </defs>

    <g clip-path='url(#outer_rectangle)'>
        <g style='isolation: isolate'>
            <rect
                stroke='<?= $theme["border"] ?>'
                stroke-opacity='<?= $theme["borderOpacity"] ?>'
                fill='<?= $theme["background"] ?>'
                fill-opacity='<?= str_starts_with((string)$theme["background"], "url(") ? "1" : $theme["backgroundOpacity"] ?>'
                rx='<?= $borderRadius ?>'
                x='0.5'
                y='0.5'
                width='<?= $rectWidth ?>'
                height='<?= $rectHeight ?>'
            />
        </g>

        <g style='isolation: isolate'>
            <line
                x1='<?= $barOffsets[0] ?>'
                y1='<?= $barHeightOffsets[0] ?>'
                x2='<?= $barOffsets[0] ?>'
                y2='<?= $barHeightOffsets[1] ?>'
                vector-effect='non-scaling-stroke'
                stroke-width='1'
                stroke='<?= $theme["stroke"] ?>'
                stroke-opacity='<?= $theme["strokeOpacity"] ?>'
                stroke-linejoin='miter'
                stroke-linecap='square'
                stroke-miterlimit='3'
            />

            <line
                x1='<?= $barOffsets[1] ?>'
                y1='<?= $barHeightOffsets[0] ?>'
                x2='<?= $barOffsets[1] ?>'
                y2='<?= $barHeightOffsets[1] ?>'
                vector-effect='non-scaling-stroke'
                stroke-width='1'
                stroke='<?= $theme["stroke"] ?>'
                stroke-opacity='<?= $theme["strokeOpacity"] ?>'
                stroke-linejoin='miter'
                stroke-linecap='square'
                stroke-miterlimit='3'
            />
        </g>

        <g style='isolation: isolate'>
            <!-- Total Contributions big number -->
            <g transform='translate(<?= $totalContributionsOffset ?>, <?= $sideColumnHeightOffsets[0] ?>)'>
                <text
                    x='0'
                    y='32'
                    stroke-width='0'
                    text-anchor='middle'
                    fill='<?= $theme["sideNums"] ?>'
                    fill-opacity='<?= $theme["sideNumsOpacity"] ?>'
                    stroke='none'
                    font-family='"Segoe UI", Ubuntu, sans-serif'
                    font-weight='700'
                    font-size='28px'
                    font-style='normal'
                    <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 0.6s'<?php endif; ?>
                >
                    <?= $totalContributions ?>
                </text>
            </g>

            <!-- Total Contributions label -->
            <g transform='translate(<?= $totalContributionsOffset ?>, <?= $sideColumnHeightOffsets[1] ?>)'>
                <text
                    x='0'
                    y='32'
                    stroke-width='0'
                    text-anchor='middle'
                    fill='<?= $theme["sideLabels"] ?>'
                    fill-opacity='<?= $theme["sideLabelsOpacity"] ?>'
                    stroke='none'
                    font-family='"Segoe UI", Ubuntu, sans-serif'
                    font-weight='400'
                    font-size='14px'
                    font-style='normal'
                    <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 0.7s'<?php endif; ?>
                >
                    <?= $cardLabels["totalContributionsText"] ?>
                </text>
            </g>

            <!-- Total Contributions range -->
            <g transform='translate(<?= $totalContributionsOffset ?>, <?= $sideColumnHeightOffsets[2] ?>)'>
                <text
                    x='0'
                    y='32'
                    stroke-width='0'
                    text-anchor='middle'
                    fill='<?= $theme["dates"] ?>'
                    fill-opacity='<?= $theme["datesOpacity"] ?>'
                    stroke='none'
                    font-family='"Segoe UI", Ubuntu, sans-serif'
                    font-weight='400'
                    font-size='12px'
                    font-style='normal'
                    <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 0.8s'<?php endif; ?>
                >
                    <?= $cardRanges["totalContributionsRange"] ?>
                </text>
            </g>
        </g>

        <g style='isolation: isolate'>
            <!-- Current Streak label -->
            <g transform='translate(<?= $currentStreakOffset ?>, <?= $currentStreakHeightOffset[1] ?>)'>
                <text
                    x='0'
                    y='32'
                    stroke-width='0'
                    text-anchor='middle'
                    fill='<?= $theme["currStreakLabel"] ?>'
                    fill-opacity='<?= $theme["currStreakLabelOpacity"] ?>'
                    stroke='none'
                    font-family='"Segoe UI", Ubuntu, sans-serif'
                    font-weight='700'
                    font-size='14px'
                    font-style='normal'
                    <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'<?php endif; ?>
                >
                    <?= $cardLabels["currentStreakText"] ?>
                </text>
            </g>

            <!-- Current Streak range -->
            <g transform='translate(<?= $currentStreakOffset ?>, <?= $currentStreakHeightOffset[2] ?>)'>
                <text
                    x='0'
                    y='21'
                    stroke-width='0'
                    text-anchor='middle'
                    fill='<?= $theme["dates"] ?>'
                    fill-opacity='<?= $theme["datesOpacity"] ?>'
                    stroke='none'
                    font-family='"Segoe UI", Ubuntu, sans-serif'
                    font-weight='400'
                    font-size='12px'
                    font-style='normal'
                    <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'<?php endif; ?>
                >
                    <?= $cardRanges["currentStreakRange"] ?>
                </text>
            </g>

            <!-- Ring around number -->
            <g mask='url(#mask_out_ring_behind_fire)'>
                <circle
                    cx='<?= $currentStreakOffset ?>'
                    cy='<?= $currentStreakHeightOffset[3] ?>'
                    r='40'
                    fill='none'
                    stroke='<?= $theme["ring"] ?>'
                    stroke-opacity='<?= $theme["ringOpacity"] ?>'
                    stroke-width='5'
                    <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 0.4s'<?php endif; ?>
                >
                </circle>
            </g>

            <!-- Fire icon -->
            <g
                transform='translate(<?= $currentStreakOffset ?>, <?= $currentStreakHeightOffset[4] ?>)'
                stroke-opacity='0'
                <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 0.6s'<?php endif; ?>
            >
                <path d='M -12 -0.5 L 15 -0.5 L 15 23.5 L -12 23.5 L -12 -0.5 Z' fill='none'/>

                <path
                    d='
                        M 1.5 0.67
                        C 1.5 0.67 2.24 3.32 2.24 5.47
                        C 2.24 7.53 0.89 9.2 -1.17 9.2
                        C -3.23 9.2 -4.79 7.53 -4.79 5.47
                        L -4.76 5.11
                        C -6.78 7.51 -8 10.62 -8 13.99
                        C -8 18.41 -4.42 22 0 22
                        C 4.42 22 8 18.41 8 13.99
                        C 8 8.6 5.41 3.79 1.5 0.67
                        Z
                        M -0.29 19
                        C -2.07 19 -3.51 17.6 -3.51 15.86
                        C -3.51 14.24 -2.46 13.1 -0.7 12.74
                        C 1.07 12.38 2.9 11.53 3.92 10.16
                        C 4.31 11.45 4.51 12.81 4.51 14.2
                        C 4.51 16.85 2.36 19 -0.29 19
                        Z
                    '
                    fill='<?= $theme["fire"] ?>'
                    fill-opacity='<?= $theme["fireOpacity"] ?>'
                    stroke-opacity='0'
                />
            </g>

            <!-- Current Streak big number -->
            <g transform='translate(<?= $currentStreakOffset ?>, <?= $currentStreakHeightOffset[0] ?>)'>
                <text
                    x='0'
                    y='32'
                    stroke-width='0'
                    text-anchor='middle'
                    fill='<?= $theme["currStreakNum"] ?>'
                    fill-opacity='<?= $theme["currStreakNumOpacity"] ?>'
                    stroke='none'
                    font-family='"Segoe UI", Ubuntu, sans-serif'
                    font-weight='700'
                    font-size='28px'
                    font-style='normal'
                    <?php if (!$disableAnimations): ?>
                        style='animation: currstreak 0.6s linear forwards'
                    <?php else: ?>
                        font-size='28px'
                    <?php endif; ?>
                >
                    <?= $currentStreak ?>
                </text>
            </g>
        </g>

        <g style='isolation: isolate'>
            <!-- Longest Streak big number -->
            <g transform='translate(<?= $longestStreakOffset ?>, <?= $sideColumnHeightOffsets[0] ?>)'>
                <text
                    x='0'
                    y='32'
                    stroke-width='0'
                    text-anchor='middle'
                    fill='<?= $theme["sideNums"] ?>'
                    fill-opacity='<?= $theme["sideNumsOpacity"] ?>'
                    stroke='none'
                    font-family='"Segoe UI", Ubuntu, sans-serif'
                    font-weight='700'
                    font-size='28px'
                    font-style='normal'
                    <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 1.2s'<?php endif; ?>
                >
                    <?= $longestStreak ?>
                </text>
            </g>

            <!-- Longest Streak label -->
            <g transform='translate(<?= $longestStreakOffset ?>, <?= $sideColumnHeightOffsets[1] ?>)'>
                <text
                    x='0'
                    y='32'
                    stroke-width='0'
                    text-anchor='middle'
                    fill='<?= $theme["sideLabels"] ?>'
                    fill-opacity='<?= $theme["sideLabelsOpacity"] ?>'
                    stroke='none'
                    font-family='"Segoe UI", Ubuntu, sans-serif'
                    font-weight='400'
                    font-size='14px'
                    font-style='normal'
                    <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 1.3s'<?php endif; ?>
                >
                    <?= $cardLabels["longestStreakText"] ?>
                </text>
            </g>

            <!-- Longest Streak range -->
            <g transform='translate(<?= $longestStreakOffset ?>, <?= $sideColumnHeightOffsets[2] ?>)'>
                <text
                    x='0'
                    y='32'
                    stroke-width='0'
                    text-anchor='middle'
                    fill='<?= $theme["dates"] ?>'
                    fill-opacity='<?= $theme["datesOpacity"] ?>'
                    stroke='none'
                    font-family='"Segoe UI", Ubuntu, sans-serif'
                    font-weight='400'
                    font-size='12px'
                    font-style='normal'
                    <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 1.4s'<?php endif; ?>
                >
                    <?= $cardRanges["longestStreakRange"] ?>
                </text>
            </g>
        </g>

        <?php if ($hasExcludedDays): ?>
            <g style='isolation: isolate'>
                <!-- Excluded Days -->
                <g transform='translate(<?= $excludedDaysOffset ?>, 187)'>
                    <text
                        stroke-width='0'
                        text-anchor='right'
                        fill='<?= $theme["excludeDaysLabel"] ?>'
                        fill-opacity='<?= $theme["excludeDaysLabelOpacity"] ?>'
                        stroke='none'
                        font-family='"Segoe UI", Ubuntu, sans-serif'
                        font-weight='400'
                        font-size='10px'
                        font-style='normal'
                        <?php if (!$disableAnimations): ?>style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'<?php endif; ?>
                    >
                        * <?= htmlspecialchars($excludingDaysText) ?>
                    </text>
                </g>
            </g>
        <?php endif; ?>
    </g>
</svg>
