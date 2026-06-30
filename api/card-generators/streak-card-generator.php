<?php

declare(strict_types=1);

// Mount core independent infrastructural tools
require_once __DIR__ . "/../utils.php";
require_once __DIR__ . "/../translation-manager.php";


require_once __DIR__ . "/base-generator.php";


/**
 * Calculate the horizontal center X coordinate for a specific card column.
 *
 * @param float|int $columnWidth The responsive pixel width of a single column.
 * @param int $columnIndex The 0-based index of the column.
 *
 * @return float|int The exact center X coordinate for rendering text elements.
 */
function getColumnCenterOffset(float|int $columnWidth, int $columnIndex): float|int
{
    return $columnWidth / 2 + $columnWidth * $columnIndex;
}


/**
 * Generate split SVG text labels for card columns based on mode and constraints.
 *
 * @param array<string,string> $localeTranslations Translations for the locale.
 * @param string $mode Calculation mode identifier ("daily" or "weekly").
 * @param int $maxCharsPerLine Maximum number of characters per line.
 *
 * @return array{
 *     totalContributionsText: string,
 *     currentStreakText: string,
 *     longestStreakText: string
 * } Split SVG-ready labels.
 */
function getCardLabels(array $localeTranslations, string $mode, int $maxCharsPerLine): array
{
    $totalContributionsText = splitLines($localeTranslations["Total Contributions"], $maxCharsPerLine, -9);

    if ($mode === "weekly") {
        $currentStreakText = splitLines($localeTranslations["Week Streak"], $maxCharsPerLine, -9);
        $longestStreakText = splitLines($localeTranslations["Longest Week Streak"], $maxCharsPerLine, -9);
    }
    else {
        $currentStreakText = splitLines($localeTranslations["Current Streak"], $maxCharsPerLine, -9);
        $longestStreakText = splitLines($localeTranslations["Longest Streak"], $maxCharsPerLine, -9);
    }

    return [
        "totalContributionsText" => $totalContributionsText,
        "currentStreakText" => $currentStreakText,
        "longestStreakText" => $longestStreakText,
    ];
}


/**
 * Generate formatted and split SVG date ranges for card columns.
 *
 * @param array<string,mixed> $stats Streak stats array.
 * @param string $localeCode Locale code.
 * @param array<string,string> $params Request parameters from the URL query string.
 * @param array<string,string> $localeTranslations Translations for the locale.
 * @param int $maxCharsPerLine Maximum number of characters per line.
 *
 * @return array{
 *     totalContributionsRange: string,
 *     currentStreakRange: string,
 *     longestStreakRange: string
 * } Split SVG-ready date ranges.
 */
function getCardRanges(array $stats, string $localeCode, array $params, array $localeTranslations, int $maxCharsPerLine): array
{
    $dateFormat = $params["date_format"] ?? $localeTranslations["date_format"];
    $firstContribution = formatDate($stats["firstContribution"], $dateFormat, $localeCode);
    $totalContributionsRange = $firstContribution . " - " . $localeTranslations["Present"];

    $currentStreakStart = formatDate($stats["currentStreak"]["start"], $dateFormat, $localeCode);
    $currentStreakEnd = formatDate($stats["currentStreak"]["end"], $dateFormat, $localeCode);

    $currentStreakRange = $currentStreakStart === $currentStreakEnd
        ? $currentStreakStart
        : $currentStreakStart . " - " . $currentStreakEnd
    ;

    $longestStreakStart = formatDate($stats["longestStreak"]["start"], $dateFormat, $localeCode);
    $longestStreakEnd = formatDate($stats["longestStreak"]["end"], $dateFormat, $localeCode);

    $longestStreakRange = $longestStreakStart === $longestStreakEnd
        ? $longestStreakStart
        : $longestStreakStart . " - " . $longestStreakEnd
    ;

    return [
        "totalContributionsRange" => splitLines($totalContributionsRange, $maxCharsPerLine, 0),
        "currentStreakRange" => splitLines($currentStreakRange, $maxCharsPerLine, 0),
        "longestStreakRange" => splitLines($longestStreakRange, $maxCharsPerLine, 0),
    ];
}


/**
 * Translate names or abbreviations of weekdays into the selected locale.
 *
 * @param array<string> $days List of raw English days abbreviations (e.g., ["Sun", "Mon", "Sat"]).
 * @param string $locale Locale code identifier (e.g., "fr" or "de").
 * @return array<string> Localized short abbreviations of the given weekdays.
 */
function translateDays(array $days, string $locale): array
{
    if ($locale === "en") {
        return $days;
    }

    /** @var array<string, IntlDateFormatter> $cachedFormatters */
    static $cachedFormatters = [];

    if (!isset($cachedFormatters[$locale])) {
        $patternGenerator = new IntlDatePatternGenerator($locale);
        $bestPattern = $patternGenerator->getBestPattern("EEE");

        $cachedFormatters[$locale] = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            pattern: $bestPattern
        );
    }

    $dateFormatter = $cachedFormatters[$locale];
    $translatedDays = [];

    foreach ($days as $day) {
        $dateObject = new DateTime($day);
        $formattedDay = $dateFormatter->format($dateObject);

        $translatedDays[] = $formattedDay !== false
            ? $formattedDay
            : $day
        ;
    }

    return $translatedDays;
}


/**
 * Compile a localized footnote text block listing all explicitly omitted weekdays.
 *
 * @param array<string> $excludedDays List of raw day codes omitted from the current streak.
 * @param array<string,string> $localeTranslations Dictionary containing translations mapping.
 * @param string $localeCode Target language configuration token string.
 * @return string Formatted human-readable message block.
 */
function getExcludingDaysText(array $excludedDays, array $localeTranslations, string $localeCode): string
{
    $separator = $localeTranslations["comma_separator"] ?? ", ";

    $translatedList = translateDays($excludedDays, $localeCode);
    $daysCommaSeparated = implode($separator, $translatedList);

    $baseTemplate = $localeTranslations["Excluding {days}"] ?? "Excluding {days}";

    return str_replace("{days}", $daysCommaSeparated, $baseTemplate);
}


/**
 * Generate SVG output for a stats array.
 *
 * @param array<string,mixed> $stats Streak stats.
 * @param array<string,string>|NULL $params Request parameters.
 *
 * @return string The generated SVG Streak Stats card.
 *
 * @throws InvalidArgumentException If a locale does not exist.
 */
function generateCard(array $stats, ?array $params = null): string
{
    $params = resolveParams($params);

    $showTotalContributions = !parseBool("hide_total_contributions", $params);
    $showCurrentStreak = !parseBool("hide_current_streak", $params);
    $showLongestStreak = !parseBool("hide_longest_streak", $params);

    $numColumns = (int)$showTotalContributions + (int)$showCurrentStreak + (int)$showLongestStreak;

    if ($numColumns === 0) {
        throw new InvalidArgumentException("All card columns are hidden. Please enable at least one column.", 400);
    }

    $commonData = getCommonCardData($params, $numColumns);

    extract($commonData);

    $localeCode = $params["locale"] ?? "en";
    $localeTranslations = getTranslations($localeCode);

    $columnWidth = $cardWidth / $numColumns;
    $barOffsets = [];
    $lastIndex = $numColumns - 1;

    for ($i = 0; $i < $lastIndex; $i++) {
        $barOffsets[$i] = $columnWidth * ($i + 1);
    }

    $maxCharsPerLineLabels = (int)floor($cardWidth / $numColumns / 7.5);
    $maxCharsPerLineDates = (int)floor($cardWidth / $numColumns / 6);

    $columnOffsets = [];

    if ($localeTranslations["rtl"] ?? false) {
        $direction = "rtl";

        for ($i = 0, $index = $lastIndex; $i < $numColumns; $i++, $index--) {
            $columnOffsets[$i] = getColumnCenterOffset($columnWidth, $index);
        }

        $excludedDaysOffset = $cardWidth - 5;
    }
    else {
        $direction = "ltr";

        for ($i = 0; $i < $numColumns; $i++) {
            $columnOffsets[$i] = getColumnCenterOffset($columnWidth, $i);
        }

        $excludedDaysOffset = 5;
    }

    if (empty($stats["excludedDays"])) {
        $hasExcludedDays = false;
        $excludingDaysText = "";
    }
    else {
        $hasExcludedDays = true;
        $excludingDaysText = getExcludingDaysText($stats["excludedDays"], $localeTranslations, $localeCode);
    }

    $nextColumnIndex = 0;
    $defaultColumnOffset = -999;

    $totalContributionsOffset = $showTotalContributions
        ? $columnOffsets[$nextColumnIndex++]
        : $defaultColumnOffset
    ;

    $currentStreakOffset = $showCurrentStreak
        ? $columnOffsets[$nextColumnIndex++]
        : $defaultColumnOffset
    ;

    $longestStreakOffset = $showLongestStreak
        ? $columnOffsets[$nextColumnIndex++]
        : $defaultColumnOffset
    ;

    $heightOffset = ($cardHeight - 195) / 2;

    $barHeightOffsets = [ 28 + $heightOffset / 2, 170 + $heightOffset ];

    $sideColumnHeightOffsets = [
        48 + $heightOffset,
        84 + $heightOffset,
        114 + $heightOffset,
    ];

    $currentStreakHeightOffset = [
        48 + $heightOffset,
        108 + $heightOffset,
        145 + $heightOffset,
        71 + $heightOffset,
        19.5 + $heightOffset,
    ];

    $useShortNumbers = parseBool("short_numbers", $params);

    $totalContributions = formatNumber((float)$stats["totalContributions"], $localeCode, $useShortNumbers);
    $currentStreak = formatNumber((float)$stats["currentStreak"]["length"], $localeCode, $useShortNumbers);
    $longestStreak = formatNumber((float)$stats["longestStreak"]["length"], $localeCode, $useShortNumbers);
    $cardLabels = getCardLabels($localeTranslations, $stats["mode"], $maxCharsPerLineLabels);
    $cardRanges = getCardRanges($stats, $localeCode, $params, $localeTranslations, $maxCharsPerLineDates);

    ob_start();

    include __DIR__ . "/../templates/streak-card.php";

    return ob_get_clean();
}
