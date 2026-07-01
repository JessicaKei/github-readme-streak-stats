<?php

declare(strict_types=1);

require_once __DIR__ . "/utils.php";


/**
 * Normalize a theme name.
 *
 * @param string $theme Theme name.
 *
 * @return string Normalized theme name.
 */
function normalizeThemeName(string $theme): string
{
    return str_replace("_", "-", $theme);
}


/**
 * Get a theme with normalization and backward compatibility in mind.
 *
 * @param string|null $themeName Theme name from URL parameters.
 * @param array<string, array<string, string>> $themes List of all available themes from `themes.php`.
 * @return array<string, string> Selected or default theme colors.
 */
function getTheme(?string $themeName, array $themes): array
{
    if (array_key_exists($themeName, $themes)) {
        return $themes[$themeName];
    }

    $lowerThemeName = strtolower($themeName);

    if (array_key_exists($lowerThemeName, $themes)) {
        return $themes[$lowerThemeName];
    }

    $normalizedThemeName = normalizeThemeName($themeName);

    if (array_key_exists($normalizedThemeName, $themes)) {
        return $themes[$normalizedThemeName];
    }

    $lowerNormalizedThemeName = strtolower($normalizedThemeName);

    if (array_key_exists($lowerNormalizedThemeName, $themes)) {
        return $themes[$lowerNormalizedThemeName];
    }

    return $themes["default"];
}


/**
 * Generate a valid SVG linear gradient definition from a theme background property configuration.
 *
 * Deconstructs a comma-separated gradient layout rule vector into explicit angle metrics
 * and child color tokens, validating and extracting Inkscape-compatible color/alpha pairs
 * dynamically for each discrete gradient stop.
 *
 * @param string $backgroundConfig Raw background string configuration (e.g., "angle,color1,color2,...").
 * @param array<string> $validCssColors White list of valid named CSS standard colors.
 *
 * @return string The generated standalone SVG <linearGradient> tag or an empty string if rules constraints mismatch.
 *
 * @throws InvalidArgumentException If any parsed color token inside the gradient layout fails hex/CSS syntax vetting.
 */
function buildBackgroundGradient(string $backgroundConfig, array $validCssColors): string
{
    $parts = explode(",", $backgroundConfig);

    if (count($parts) < 3) {
        return "";
    }

    $angle = $parts[0];
    $colors = array_slice($parts, 1);
    $colorCount = count($colors);
    $colorLastIndex = $colorCount - 1;

    $gradientHtml = "<linearGradient id='gradient' gradientTransform='rotate({$angle})' gradientUnits='userSpaceOnUse'>";

    for ($i = 0; $i < $colorCount; $i++) {
        $offset = ($i * 100) / $colorLastIndex;

        $cleanColorToken = strtolower(trim($colors[$i]));

        [
            "color" => $c,
            "opacity" => $o
        ] = resolveColorAndOpacity($cleanColorToken, $validCssColors);

        $gradientHtml .= "<stop offset='{$offset}%' stop-color='{$c}' stop-opacity='{$o}' />";
    }

    $gradientHtml .= "</linearGradient>";

    return $gradientHtml;
}


/**
 * Expand a 3-digit shorthand hex color layout into a standard 6-digit hex string.
 *
 * This function extracts three individual hex characters using explicit index positions
 * and duplicates each component to form a standard `#RRGGBB` format.
 *
 * @param string $hex The raw string containing the hex code coordinates.
 * @param int $firstIndex The absolute string character offset index for the Red component.
 * @param int $secondIndex The absolute string character offset index for the Green component.
 * @param int $thirdIndex The absolute string character offset index for the Blue component.
 *
 * @return array{hex: string, opacityHex: string} Standardized hex color map vector containing an empty opacity segment.
 */
function normalize3DigitHexLength(string $hex, int $firstIndex, int $secondIndex, int $thirdIndex): array
{
    $c1 = $hex[$firstIndex];
    $c2 = $hex[$secondIndex];
    $c3 = $hex[$thirdIndex];

    return [
        "hex" => "#{$c1}{$c1}{$c2}{$c2}{$c3}{$c3}",
        "opacityHex" => ""
    ];
}


/**
 * Expand a 4-digit shorthand alpha hex layout into a standard 6-digit hex string and an isolated alpha channel.
 *
 * This function extracts four individual hex characters using explicit index positions.
 * The first three components are duplicated to form a standard `#RRGGBB` format, while the
 * fourth component is duplicated to form a clean 2-digit alpha channel block.
 *
 * @param string $hex The raw string containing the short alpha hex code coordinates.
 * @param int $firstIndex The absolute string character offset index for the Red component.
 * @param int $secondIndex The absolute string character offset index for the Green component.
 * @param int $thirdIndex The absolute string character offset index for the Blue component.
 * @param int $fourthIndex The absolute string character offset index for the Alpha channel component.
 *
 * @return array{hex: string, opacityHex: string} Standardized color descriptor packet splitting color data from alpha payload metadata.
 */
function normalize4DigitHexLength(string $hex, int $firstIndex, int $secondIndex, int $thirdIndex, int $fourthIndex): array
{
    $c1 = $hex[$firstIndex];
    $c2 = $hex[$secondIndex];
    $c3 = $hex[$thirdIndex];
    $c4 = $hex[$fourthIndex];

    return [
        "hex" => "#{$c1}{$c1}{$c2}{$c2}{$c3}{$c3}",
        "opacityHex" => "{$c4}{$c4}"
    ];
}


/**
 * Deconstruct and standardize any variant of shorthand or expanded hex color strings directly into explicit layout components.
 *
 * Evaluates the input structure to detect leading hash identifiers (`#`) natively, calculating boundary limits
 * and routing token extractions down to optimized memory-index processors without generating temporary upper-level allocations.
 *
 * @param string $hex The raw unvetted input color string token (e.g., "#fff", "f0a3", "#ff0000").
 *
 * @return array{hex: string, opacityHex: string} Standardized target layout map structure containing clean color tokens and raw alpha bits.
 */
function normalizeHexLength(string $hex): array
{
    $len = strlen($hex);

    if ($hex[0] === '#') {
        if ($len === 4) {
            return normalize3DigitHexLength($hex, 1, 2, 3);
        }

        if ($len === 5) {
            return normalize4DigitHexLength($hex, 1, 2, 3, 4);
        }

        if ($len === 7) {
            return [
                "hex" => $hex,
                "opacityHex" => ""
            ];
        }

        return [
            "hex" => substr($hex, 0, 7),
            "opacityHex" => substr($hex, 7, 2)
        ];
    }

    if ($len === 3) {
        return normalize3DigitHexLength($hex, 0, 1, 2);
    }

    if ($len === 4) {
        return normalize4DigitHexLength($hex, 0, 1, 2, 3);
    }

    if ($len === 6) {
        return [
            "hex" => "#{$hex}",
            "opacityHex" => ""
        ];
    }

    return [
        "hex" => "#" . substr($hex, 0, 6),
        "opacityHex" => substr($hex, 6, 2)
    ];
}


/**
 * Convert a 2-digit hex alpha channel string into an invariant web-safe opacity float string (0-1).
 *
 * @param string $opacityHex Clean 2-digit hex string representing the alpha channel (e.g., "ff", "33").
 *
 * @return string Localized-safe string decimal percentage value rounded dynamically.
 */
function parseHexOpacity(string $opacityHex): string
{
    $alphaFloat = hexdec($opacityHex) / 255;

    $opacityStr = sprintf("%.4F", $alphaFloat);
    $opacityStr = rtrim(rtrim($opacityStr, "0"), ".");

    return $opacityStr === "" || $opacityStr === "0."
        ? "0"
        : $opacityStr
    ;
}


/**
 * Resolve any raw valid color token (hex with/without #, transparent or named CSS) into an Inkscape-compatible color and opacity.
 *
 * Validates the input against allowed syntax rules, expands short configurations natively via layout boundary
 * index offsets, and decouples alpha hex descriptors directly into dedicated web layout tokens.
 *
 * @param string $color The raw unvetted input color string (e.g., "#fff", "transparent", "red", "ff0000ff").
 * @param array<string> $validCssColors White list of valid named CSS standard colors.
 *
 * @return array{color: string, opacity: string} Standardized target configuration vector mapping clear color to alpha channel strength.
 *
 * @throws InvalidArgumentException If the input pattern breaks color validation constraints or is an unknown CSS name.
 */
function resolveColorAndOpacity(string $color, array $validCssColors): array
{
    $color = trim($color);
    $lowerColor = strtolower($color);

    if ($lowerColor === "transparent") {
        return [
            "color" => "#000000",
            "opacity" => "0"
        ];
    }

    if (in_array($lowerColor, $validCssColors, true)) {
        return [
            "color" => $lowerColor,
            "opacity" => "1"
        ];
    }

    if (!preg_match("/^#?([a-f0-9]{3,4}|[a-f0-9]{6}|[a-f0-9]{8})$/i", $color)) {
        throw new InvalidArgumentException("Invalid color format or unknown CSS name: '{$color}'.", 400);
    }

    [
        "hex" => $cleanColor,
        "opacityHex" => $opacityHex
    ] = normalizeHexLength($color);

    $opacity = $opacityHex === ""
        ? "1"
        : parseHexOpacity($opacityHex)
    ;

    return [
        "color" => $cleanColor,
        "opacity" => $opacity
    ];
}


/**
 * Check theme and color customization parameters to generate a theme mapping.
 *
 * @param array<string,string> $params Request parameters from the URL query string.
 *
 * @return array<string,string> The fully evaluated and customized theme property dictionary.
 *
 * @throws InvalidArgumentException If any parsed color token inside the gradient layout fails hex/CSS syntax vetting.
 */
function getRequestedTheme(array $params): array
{
    /** @var array<string,array<string,string>>|null $cachedThemes */
    static $cachedThemes = null;

    /** @var array<string>|null $cachedColors */
    static $cachedColors = null;

    if ($cachedThemes === null) {
        $cachedThemes = require __DIR__ . "/themes.php";
    }

    if ($cachedColors === null) {
        $cachedColors = require __DIR__ . "/colors.php";
    }

    $theme = getTheme($params["theme"] ?? "default", $cachedThemes);

    if (parseBool("hide_border", $params)) {
        $params["border"] = "transparent";
    }

    $finalTheme = [];

    // Override theme properties using customized parameters
    foreach ($theme as $prop => $defaultValue) {
        $rawValue = $params[$prop] ?? $defaultValue;
        $cleanValue = strtolower(trim((string)$rawValue));

        // Handle specific layout customization rules
        if ($prop === "background" && preg_match("/^-?[0-9]+,[a-f0-9]{3,8}(,[a-f0-9]{3,8})+$/", $cleanValue)) {
            $finalTheme[$prop] = $cleanValue;

            continue;
        }

        [
            "color" => $cleanColor,
            "opacity" => $opacityValue
        ] = resolveColorAndOpacity($cleanValue, $cachedColors);

        $finalTheme[$prop] = $cleanColor;
        $finalTheme[$prop . "Opacity"] = $opacityValue;
    }

    // Resolve gradient values and compile final SVG background layout bindings
    $finalTheme["backgroundGradient"] = buildBackgroundGradient($finalTheme["background"] ?? "", $cachedColors);

    if ($finalTheme["backgroundGradient"] !== "") {
        $finalTheme["background"] = "url(#gradient)";
        $finalTheme["backgroundOpacity"] = "1";
    }

    return $finalTheme;
}
