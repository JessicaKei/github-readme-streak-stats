<?php

declare(strict_types=1);

require_once __DIR__ . "/base-generator.php";


/**
 * Generate an SVG card displaying a customized validation or runtime error message.
 *
 * @param string $message The error message text string to display on the card.
 * @param array<string,string>|null $params Request parameters from the URL query string.
 *
 * @return array{svg: string, width: int, height: int} Packaged descriptor array containing raw SVG code and precise pixel dimensions.
 */
function generateErrorCard(string $message, ?array $params = null): array
{
    $commonData = getCommonCardData($params);

    extract($commonData);

    $centerOffset = $cardWidth / 2;
    $errorLabelOffset = $cardHeight / 2 + 10.5;

    ob_start();

    include __DIR__ . "/../templates/error-card.php";

    $svg = (string)ob_get_clean();

    return [
        "svg"    => $svg,
        "width"  => $cardWidth,
        "height" => $cardHeight
    ];
}
