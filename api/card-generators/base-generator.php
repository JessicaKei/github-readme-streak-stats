<?php

declare(strict_types=1);


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
