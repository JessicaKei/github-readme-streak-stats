<?php

declare(strict_types=1);

/**
 * Normalize a locale code string into a standard BCP 47 format (e.g., "en", "zh_Hans", "en_US").
 *
 * @param string $localeCode Raw locale code parameter string.
 *
 * @return string Strictly formatted language, script, and region tag.
 */
function normalizeLocaleCode(string $localeCode): string
{
    if (preg_match("/^([a-z]{2,3})(?:[_-]([a-z]{4}))?(?:[_-]([0-9]{3}|[a-z]{2}))?$/i", $localeCode, $matches) !== 1) {
        return "en";
    }

    $language = strtolower($matches[1]);

    $script = isset($matches[2])
        ? ucfirst(strtolower($matches[2]))
        : ""
    ;

    $region = isset($matches[3])
        ? strtoupper($matches[3])
        : ""
    ;

    // Combines elements into a strict modern standard string mapping (e.g. language_Script_REGION)
    return implode("_", array_filter([ $language, $script, $region ]));
}

/**
 * Fetch and compile the finalized dictionary translation array for a given locale.
 *
 * @param string $localeCode Raw user configuration or request locale code.
 *
 * @return array<string, string|bool> Complete translated token mappings containing default fallbacks.
 */
function getTranslations(string $localeCode): array
{
    /** @var array<string, mixed>|null $cachedTranslations */
    static $cachedTranslations = null;

    if ($cachedTranslations === null) {
        $cachedTranslations = require __DIR__ . "/translations.php";
    }

    $normalizedCode = normalizeLocaleCode($localeCode);

    // 1. Fallback Strategy Level 1: Check full matching key directly
    if (!isset($cachedTranslations[$normalizedCode])) {
        // Fallback Strategy Level 2: Strip script/region modifiers down to base language code (e.g., "en_US" -> "en")
        $normalizedCode = explode("_", $normalizedCode)[0];
    }

    $localeTranslations = $cachedTranslations[$normalizedCode] ?? [];

    // 2. Fallback Strategy Level 3: Resolve locale aliases (e.g., redirecting "zh" token to "zh_Hans" structure)
    if (is_string($localeTranslations)) {
        $localeTranslations = $cachedTranslations[$localeTranslations] ?? [];
    }

    // 3. Fallback Strategy Level 4: Array Union Operator merges English strings cleanly into missing target keys
    return $localeTranslations + $cachedTranslations["en"];
}
