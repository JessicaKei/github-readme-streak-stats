<?php

declare(strict_types=1);


/**
 * Parse a request parameter string value into a clean boolean state.
 *
 * @param string $paramKey The request parameter key to evaluate.
 * @param array<string,string> $params Request parameters mapping.
 *
 * @return bool True if the value is explicitly set to "true", false otherwise.
 */
function parseBool(string $paramKey, array $params): bool
{
    return isset($params[$paramKey]) && strtolower(trim($params[$paramKey])) === "true";
}


/**
 * Convert a generic configuration or request value safely into a boolean state.
 *
 * @param string|int|float $paramKey The index name or key to look up.
 * @param array<string|int, mixed> $source The source container array (e.g., $params, $_SERVER).
 *
 * @return bool True if the value represents a true state, false otherwise.
 */
function convertToBool(string|int|float $paramKey, array $source): bool
{
    if (!isset($source[$paramKey])) {
        return false;
    }

    $rawVal = $source[$paramKey];

    if (is_bool($rawVal)) {
        return $rawVal;
    }

    if (is_numeric($rawVal)) {
        return (float)$rawVal !== 0.0;
    }

    $cleanStr = strtolower(trim((string)$rawVal));

    return $cleanStr === "true" || $cleanStr === "on" || $cleanStr === "yes";
}


/**
 * Wraps a string to a given number of characters using multibyte string length.
 *
 * @param string $text The input string to wrap.
 * @param int $width The number of characters at which the string will be wrapped.
 * @param string $break The line break character sequence.
 *
 * @return string The wrapped string with line breaks inserted.
 */
function mbWordWrap(string $text, int $width, string $break = "\n"): string
{
    if ($width <= 0) {
        return $text;
    }

    $words = explode(" ", $text);
    $wrappedLines = [];
    $currentLine = "";

    foreach ($words as $word) {
        $testLine = $currentLine === ""
            ? $word
            : $currentLine . " " . $word
        ;

        if (mb_strlen($testLine, "UTF-8") <= $width) {
            $currentLine = $testLine;
        } else {
            if ($currentLine !== "") {
                $wrappedLines[] = $currentLine;
            }

            $currentLine = $word;
        }
    }

    if ($currentLine !== "") {
        $wrappedLines[] = $currentLine;
    }

    return implode($break, $wrappedLines);
}


/**
 * Split a string into multiple SVG tspan lines if it contains a newline or exceeds character limits.
 *
 * @param string $text The raw text or translated string to process.
 * @param int $maxChars The maximum allowed characters before forcing a word wrap.
 * @param int $line1Offset Vertical Y-axis offset adjustment for the very first line.
 * @param string $separator Custom text separator pattern to check for primary splitting.
 *
 * @return string Formatted SVG text block safely wrapped inside <tspan> elements.
 */
function splitLines(string $text, int $maxChars, int $line1Offset, string $separator = " - "): string
{
    if ($maxChars === 0 || (mb_strlen($text, "UTF-8") <= $maxChars && !str_contains($text, "\n"))) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    $text = str_contains($text, $separator)
        ? str_replace($separator, "\n- ", $text)
        : mbWordWrap($text, $maxChars, "\n")
    ;

    $lines = explode("\n", $text);

    $firstLineEscaped = htmlspecialchars($lines[0], ENT_QUOTES, 'UTF-8');
    $svgText = "<tspan x='0' dy='{$line1Offset}'>{$firstLineEscaped}</tspan>";

    $totalLines = count($lines);

    for ($i = 1; $i < $totalLines; $i++) {
        $escapedLine = htmlspecialchars($lines[$i], ENT_QUOTES, 'UTF-8');
        $svgText .= "<tspan x='0' dy='16'>{$escapedLine}</tspan>";
    }

    return $svgText;
}


/**
 * Format a date object using a custom format string with template bracket logic.
 *
 * @param DateTime $date Pre-initialized DateTime object.
 * @param string $format Custom date format string containing bracket instructions.
 * @param bool $isCurrentYear Flag indicating if the date belongs to the current calendar year.
 *
 * @return string Safely escaped formatted date string.
 */
function formatCustomDate(DateTime $date, string $format, bool $isCurrentYear): string
{
    $cleanedFormat = $isCurrentYear
        ? preg_replace("/\[.*?\]/", "", $format)
        : str_replace(["[", "]"], "", $format)
    ;

    return htmlspecialchars($date->format($cleanedFormat), ENT_QUOTES, 'UTF-8');
}


/**
 * Format a date object using localized internationalization features (ICU).
 *
 * @param DateTime $date Pre-initialized DateTime object.
 * @param string $locale Locale code identifier (e.g., "en" or "fr").
 * @param bool $isCurrentYear Flag indicating if the date belongs to the current calendar year.
 * @param string $fallbackOriginal Default string fallback value if formatting fails.
 *
 * @return string Safely escaped localized formatted date string.
 */
function formatLocaleDate(DateTime $date, string $locale, bool $isCurrentYear, string $fallbackOriginal): string
{
    $skeleton = $isCurrentYear
        ? "MMM d"
        : "yyyy MMM d"
    ;

    $patternGenerator = new IntlDatePatternGenerator($locale);
    $bestPattern = $patternGenerator->getBestPattern($skeleton);

    $dateFormatter = new IntlDateFormatter(
        $locale,
        IntlDateFormatter::MEDIUM,
        IntlDateFormatter::NONE,
        pattern: $bestPattern
    );

    $formattedDate = $dateFormatter->format($date);

    return htmlspecialchars(
        $formattedDate !== false
            ? $formattedDate
            : $fallbackOriginal
        ,
        ENT_QUOTES,
        'UTF-8'
    );
}


/**
 * Convert a Y-M-D date string into a localized, human-readable format.
 *
 * @param string $dateString Date string in standard Y-M-D format.
 * @param string|null $format Custom date format string containing bracket instructions, or null.
 * @param string $locale Locale code identifier (e.g., "en" or "fr").
 *
 * @return string Localized and escaped date string.
 */
function formatDate(string $dateString, ?string $format, string $locale): string
{
    $date = new DateTime($dateString);
    $isCurrentYear = ($date->format("Y") === date("Y"));

    if ($format !== null && $format !== "") {
        return formatCustomDate($date, $format, $isCurrentYear);
    }

    return formatLocaleDate($date, $locale, $isCurrentYear, $dateString);
}


/**
 * Format a numeric value into a localized string with optional shorthand notation (K, M, B).
 *
 * @param float $num The raw numeric value to format.
 * @param string $localeCode Locale identifier token string (e.g., "en" or "de").
 * @param bool $useShortNumbers True to activate modern short suffixes (e.g., 1.5K), false for flat strings.
 *
 * @return string Strictly formatted and localized number string.
 */
function formatNumber(float $num, string $localeCode, bool $useShortNumbers): string
{
    /** @var array<string, NumberFormatter> $cachedFormatters */
    static $cachedFormatters = [];

    if (!isset($cachedFormatters[$localeCode])) {
        $cachedFormatters[$localeCode] = new NumberFormatter($localeCode, NumberFormatter::DECIMAL);
    }

    $numFormatter = $cachedFormatters[$localeCode];
    $suffix = "";

    if ($useShortNumbers && $num >= 1000.0 && is_finite($num)) {
        /** @var array<int, string> $units */
        static $units = ["", "K", "M", "B", "T"];

        /** @var int $maxIndex */
        static $maxIndex = 4;

        $index = (int)floor(log10($num) / 3);
        $index = min($index, $maxIndex);

        $num /= 1000 ** $index;
        $num = round($num, 1);
        $suffix = $units[$index];
    }

    $formattedValue = $numFormatter->format($num);

    $prefix = $formattedValue !== false
        ? $formattedValue
        : (string)$num
    ;

    return $prefix . $suffix;
}


/**
 * Safely resolve parameter inputs, falling back to global request mappings.
 *
 * @param array<string,string>|null $params Provided request parameters dictionary.
 *
 * @return array<string,string> Valid non-null configuration array.
 */
function resolveParams(?array $params): array
{
    return $params ?? $_REQUEST;
}
