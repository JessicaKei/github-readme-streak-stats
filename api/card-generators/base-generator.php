<?php

declare(strict_types=1);

require_once __DIR__ . "/../utils.php";
require_once __DIR__ . "/../theme-manager.php";


/**
 * Calculate the dynamic responsive pixel width of the card based on column constraints.
 *
 * @param array<string,string> $params Request parameters from the URL query string.
 * @param int $numColumns Active visible metrics columns layout context.
 *
 * @return int Evaluated card width constraint.
 */
function getCardWidth(array $params, int $numColumns = 3): int
{
    return max(100 * $numColumns, (int)($params["card_width"] ?? 495));
}


/**
 * Calculate the dynamic responsive pixel height of the card.
 *
 * @param array<string,string> $params Request parameters from the URL query string.
 *
 * @return int Evaluated card height constraint.
 */
function getCardHeight(array $params): int
{
    return max(170, (int)($params["card_height"] ?? 195));
}


/**
 * Extract and calculate shared core parameters for all card generators.
 *
 * @param array<string,string>|null $params Raw request parameters dictionary.
 * @param int $numColumns Layout context columns count.
 *
 * @return array{
 *     params: array<string,string>,
 *     theme: array<string,string>,
 *     borderRadius: float|int,
 *     cardWidth: int,
 *     cardHeight: int,
 *     rectWidth: int,
 *     rectHeight: int,
 *     heightOffset: float|int
 * } Cleaned core layout metadata configuration vector.
 */
function getCommonCardData(?array $params, int $numColumns = 3): array
{
    $params = resolveParams($params);
    $theme = getRequestedTheme($params);

    $borderRadius = $params["border_radius"] ?? 4.5;

    $cardWidth = getCardWidth($params, $numColumns);
    $cardHeight = getCardHeight($params);

    return [
        "params" => $params,
        "theme" => $theme,
        "borderRadius" => $borderRadius,
        "cardWidth" => $cardWidth,
        "cardHeight" => $cardHeight,
        "rectWidth" => $cardWidth - 1,
        "rectHeight" => $cardHeight - 1,
        "heightOffset" => ($cardHeight - 195) / 2,
    ];
}
