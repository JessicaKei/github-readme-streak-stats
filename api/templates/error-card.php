<?php
/**
 * Template layout for the SVG Error card.
 *
 * @var array<string,string> $theme Colors and gradients mapping for the selected theme.
 * @var int $cardWidth Calculated pixel width of the error card.
 * @var int $cardHeight Calculated pixel height of the error card.
 * @var float|string $borderRadius Outer corner rounding radius.
 * @var int $rectWidth Width of the inner background rectangle (cardWidth - 1).
 * @var int $rectHeight Height of the inner background rectangle (cardHeight - 1).
 * @var float|int $centerOffset Horizontal center X coordinate for text and graphics.
 * @var float|int $heightOffset Vertical positioning offset based on dynamic height.
 * @var float|int $errorLabelOffset Vertical Y coordinate for the error text label.
 * @var string $message The escaped raw error message to display.
 * @var bool $disableAnimations Flag indicating whether animations are disabled.
 */
?>
<svg
    xmlns='http://www.w3.org/2000/svg'
    xmlns:xlink='http://www.w3.org/1999/xlink'
    style='isolation: isolate'
    viewBox='0 0 <?= $cardWidth ?> <?= $cardHeight ?>'
    width='<?= $cardWidth ?>px'
    height='<?= $cardHeight ?>px'
>
    <?php if (!$disableAnimations): ?>
        <style>
            a {
                fill: <?= $theme["dates"] ?>;
                fill-opacity: <?= $theme["datesOpacity"] ?>;
            }
        </style>
    <?php endif; ?>

    <defs>
        <clipPath id='outer_rectangle'>
            <rect width='<?= $cardWidth ?>' height='<?= $cardHeight ?>' rx='<?= $borderRadius ?>'/>
        </clipPath>

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
            <!-- Error label -->
            <g transform='translate(<?= $centerOffset ?>, <?= $errorLabelOffset ?>)'>
                <text
                    x='0'
                    y='50'
                    dy='0.25em'
                    stroke-width='0'
                    text-anchor='middle'
                    fill='<?= $theme["sideLabels"] ?>'
                    fill-opacity='<?= $theme["sideLabelsOpacity"] ?>'
                    stroke='none'
                    font-family='"Segoe UI", Ubuntu, sans-serif'
                    font-weight='400'
                    font-size='14px'
                    font-style='normal'
                >
                    <?php if ($disableAnimations): ?>
                        <?= $message ?>
                    <?php else: ?>
                        <a href="#"><?= $message ?></a>
                    <?php endif; ?>
                </text>
            </g>

            <!-- Mask for background behind face -->
            <defs>
                <mask id='cut-off-area'>
                    <rect x='0' y='0' width='500' height='500' fill='white' />
                    <ellipse cx='<?= $centerOffset ?>' cy='31' rx='13' ry='18'/>
                </mask>
            </defs>

            <!-- Sad face -->
            <g transform='translate(<?= $centerOffset ?>, <?= $heightOffset ?>)'>
                <path
                    fill='<?= $theme["fire"] ?>'
                    fill-opacity='<?= $theme["fireOpacity"] ?>'
                    d='
                        M0,35.8
                        c-25.2,0-45.7,20.5-45.7,45.7
                        s20.5,45.8,45.7,45.8
                        s45.7-20.5,45.7-45.7
                        S25.2,35.8,0,35.8z
                        M0,122.3
                        c-11.2,0-21.4-4.5-28.8-11.9
                        c-2.9-2.9-5.4-6.3-7.4-10
                        c-3-5.7-4.6-12.1-4.6-18.9
                        c0-22.5,18.3-40.8,40.8-40.8
                        c10.7,0,20.4,4.1,27.7,10.9
                        c3.8,3.5,6.9,7.7,9.1,12.4
                        c2.6,5.3,4,11.3,4,17.6
                        C40.8,104.1,22.5,122.3,0,122.3z
                    '
                />
                <path
                    fill='<?= $theme["fire"] ?>'
                    fill-opacity='<?= $theme["fireOpacity"] ?>'
                    d='
                        M4.8,93.8
                        c5.4,1.1,10.3,4.2,13.7,8.6
                        l3.9-3
                        c-4.1-5.3-10-9-16.6-10.4
                        c-10.6-2.2-21.7,1.9-28.3,10.4
                        l3.9,3
                        C-13.1,95.3-3.9,91.9,4.8,93.8z
                    '
                />
                <circle fill='<?= $theme["fire"] ?>' fill-opacity='<?= $theme["fireOpacity"] ?>' cx='-15' cy='71' r='4.9'/>
                <circle fill='<?= $theme["fire"] ?>' fill-opacity='<?= $theme["fireOpacity"] ?>' cx='15' cy='71' r='4.9'/>
            </g>
        </g>
    </g>
</svg>
