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
 * Validate and format a raw color parameter string into a safe CSS or hex value.
 *
 * @param string $colorVal The raw, lowercase color value to check.
 * @param array<string> $validCssColors Dictionary of supported standard CSS color names.
 *
 * @return string|null The sanitized color with '#' prefix if hex, valid CSS name, or null if invalid.
 */
function sanitizeColorValue(string $colorVal, array $validCssColors): ?string
{
    // Check if color is a valid hex code (3, 4, 6, or 8 hex digits)
    if (preg_match("/^([a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/", $colorVal)) {
        return "#" . $colorVal;
    }

    // Check if color is a valid named CSS color
    if (in_array($colorVal, $validCssColors, true)) {
        return $colorVal;
    }

    return null;
}


/**
 * Generate a valid SVG linear gradient definition from a theme background property.
 *
 * @param string $backgroundConfig Raw background string configuration (e.g., "angle,color1,color2,...").
 *
 * @return string The generated standalone SVG <linearGradient> tag or an empty string.
 */
function buildBackgroundGradient(string $backgroundConfig): string
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
        $gradientHtml .= "<stop offset='{$offset}%' stop-color='#{$colors[$i]}' />";
    }

    $gradientHtml .= "</linearGradient>";

    return $gradientHtml;
}


/**
 * Check theme and color customization parameters to generate a theme mapping.
 *
 * @param array<string,string> $params Request parameters from the URL query string.
 *
 * @return array<string,string> The fully evaluated and customized theme property dictionary.
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

    // Override theme properties using customized parameters
    foreach ($theme as $prop => $defaultVal) {
        if (!isset($params[$prop])) {
            continue;
        }

        $paramValue = strtolower(trim($params[$prop]));

        // Handle specific layout customization rules
        if ($prop === "background" && preg_match("/^-?[0-9]+,[a-f0-9]{3,8}(,[a-f0-9]{3,8})+$/", $paramValue)) {
            $theme[$prop] = $paramValue;

            continue;
        }

        $sanitizedColor = sanitizeColorValue($paramValue, $cachedColors);

        if ($sanitizedColor !== null) {
            $theme[$prop] = $sanitizedColor;
        }
    }

    // Force transparent borders if explicitly requested by configuration parameter flags
    if (parseBool("hide_border", $params)) {
        $theme["border"] = "#0000";
    }

    // Resolve gradient values and compile final SVG background layout bindings
    $theme["backgroundGradient"] = buildBackgroundGradient($theme["background"] ?? "");

    if ($theme["backgroundGradient"] !== "") {
        $theme["background"] = "url(#gradient)";
    }

    return $theme;
}
