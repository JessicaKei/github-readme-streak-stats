<?php

declare(strict_types=1);

/**
 * Generate SVG displaying an error message
 *
 * @param string $message The error message to display
 * @param array<string,string>|NULL $params Request parameters
 * @return string The generated SVG error card
 */
function generateErrorCard(string $message, array $params = null): string
{
    $params = $params ?? $_REQUEST;

    // get requested theme, use $_REQUEST if no params array specified
    $theme = getRequestedTheme($params);

    // read border_radius parameter, default to 4.5 if not set
    $borderRadius = $params["border_radius"] ?? 4.5;

    // read card_width parameter
    $cardWidth = getCardWidth($params);
    $rectWidth = $cardWidth - 1;
    $centerOffset = $cardWidth / 2;

    // read card_height parameter
    $cardHeight = getCardHeight($params);
    $rectHeight = $cardHeight - 1;
    $heightOffset = ($cardHeight - 195) / 2;
    $errorLabelOffset = $cardHeight / 2 + 10.5;

    return "<svg xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' style='isolation: isolate' viewBox='0 0 {$cardWidth} {$cardHeight}' width='{$cardWidth}px' height='{$cardHeight}px'>
        <style>
            a {
                fill: {$theme["dates"]};
            }
        </style>
        <defs>
            <clipPath id='outer_rectangle'>
                <rect width='{$cardWidth}' height='{$cardHeight}' rx='{$borderRadius}'/>
            </clipPath>
            {$theme["backgroundGradient"]}
        </defs>
        <g clip-path='url(#outer_rectangle)'>
            <g style='isolation: isolate'>
                <rect stroke='{$theme["border"]}' fill='{$theme["background"]}' rx='{$borderRadius}' x='0.5' y='0.5' width='{$rectWidth}' height='{$rectHeight}'/>
            </g>
            <g style='isolation: isolate'>
                <!-- Error lable -->
                <g transform='translate({$centerOffset}, {$errorLabelOffset})'>
                    <text x='0' y='50' dy='0.25em' stroke-width='0' text-anchor='middle' fill='{$theme["sideLabels"]}' stroke='none' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-weight='400' font-size='14px' font-style='normal'>
                        {$message}
                    </text>
                </g>

                <!-- Mask for background behind face -->
                <defs>
                    <mask id='cut-off-area'>
                        <rect x='0' y='0' width='500' height='500' fill='white' />
                        <ellipse cx='{$centerOffset}' cy='31' rx='13' ry='18'/>
                    </mask>
                </defs>
                <!-- Sad face -->
                <g transform='translate({$centerOffset}, {$heightOffset})'>
                    <path fill='{$theme["fire"]}' d='M0,35.8c-25.2,0-45.7,20.5-45.7,45.7s20.5,45.8,45.7,45.8s45.7-20.5,45.7-45.7S25.2,35.8,0,35.8z M0,122.3c-11.2,0-21.4-4.5-28.8-11.9c-2.9-2.9-5.4-6.3-7.4-10c-3-5.7-4.6-12.1-4.6-18.9c0-22.5,18.3-40.8,40.8-40.8 c10.7,0,20.4,4.1,27.7,10.9c3.8,3.5,6.9,7.7,9.1,12.4c2.6,5.3,4,11.3,4,17.6C40.8,104.1,22.5,122.3,0,122.3z'/>
                    <path fill='{$theme["fire"]}' d='M4.8,93.8c5.4,1.1,10.3,4.2,13.7,8.6l3.9-3c-4.1-5.3-10-9-16.6-10.4c-10.6-2.2-21.7,1.9-28.3,10.4l3.9,3 C-13.1,95.3-3.9,91.9,4.8,93.8z'/>
                    <circle fill='{$theme["fire"]}' cx='-15' cy='71' r='4.9'/>
                    <circle fill='{$theme["fire"]}' cx='15' cy='71' r='4.9'/>
                </g>
            </g>
        </g>
    </svg>
";
}

/**
 * Remove animations from SVG
 *
 * @param string $svg The SVG for the card as a string
 * @return string The SVG without animations
 */
function removeAnimations(string $svg): string
{
    $svg = preg_replace("/(<style>\X*?<\/style>)/m", "", $svg);
    $svg = preg_replace("/(opacity: 0;)/m", "opacity: 1;", $svg);
    $svg = preg_replace("/(animation: fadein[^;'\"]+)/m", "opacity: 1;", $svg);
    $svg = preg_replace("/(animation: currstreak[^;'\"]+)/m", "font-size: 28px;", $svg);
    $svg = preg_replace("/<a \X*?>(\X*?)<\/a>/m", '\1', $svg);
    return $svg;
}

/**
 * Convert a color from hex 3/4/6/8 digits to hex 6 digits and opacity (0-1)
 *
 * @param string $color The color to convert
 * @return array<string, string> The converted color
 */
function convertHexColor(string $color): array
{
    $color = preg_replace("/[^0-9a-fA-F]/", "", $color);

    // double each character if the color is in 3/4 digit format
    if (strlen($color) === 3) {
        $chars = str_split($color);
        $color = "{$chars[0]}{$chars[0]}{$chars[1]}{$chars[1]}{$chars[2]}{$chars[2]}";
    } elseif (strlen($color) === 4) {
        $chars = str_split($color);
        $color = "{$chars[0]}{$chars[0]}{$chars[1]}{$chars[1]}{$chars[2]}{$chars[2]}{$chars[3]}{$chars[3]}";
    }

    // convert to 6 digit hex and opacity
    if (strlen($color) === 6) {
        return [
            "color" => "#{$color}",
            "opacity" => 1,
        ];
    } elseif (strlen($color) === 8) {
        return [
            "color" => "#" . substr($color, 0, 6),
            "opacity" => hexdec(substr($color, 6, 2)) / 255,
        ];
    }
    throw new AssertionError("Invalid color: " . $color);
}

/**
 * Convert transparent hex colors (4/8 digits) in an SVG to hex 6 digits and corresponding opacity attribute (0-1)
 *
 * @param string $svg The SVG for the card as a string
 * @return string The SVG with converted colors
 */
function convertHexColors(string $svg): string
{
    // convert "transparent" to "#0000"
    $svg = preg_replace("/(fill|stroke)=['\"]transparent['\"]/m", '\1="#0000"', $svg);

    // convert hex colors to 6 digits and corresponding opacity attribute
    $svg = preg_replace_callback(
        "/(fill|stroke|stop-color)=['\"]#([0-9a-fA-F]{4}|[0-9a-fA-F]{8})['\"]/m",
        function ($matches) {
            $attribute = $matches[1];
            $opacityAttribute = $attribute === "stop-color" ? "stop-opacity" : "{$attribute}-opacity";
            $result = convertHexColor($matches[2]);
            $color = $result["color"];
            $opacity = $result["opacity"];
            return "{$attribute}='{$color}' {$opacityAttribute}='{$opacity}'";
        },
        $svg,
    );

    return $svg;
}

/**
 * Converts an SVG card to a PNG image
 *
 * @param string $svg The SVG for the card as a string
 * @param int $cardWidth The width of the card
 * @return string The generated PNG data
 */
function convertSvgToPng(string $svg, int $cardWidth, int $cardHeight): string
{
    // trim off all whitespaces to make it a valid SVG string
    $svg = trim($svg);

    // remove style and animations
    $svg = removeAnimations($svg);

    // replace newlines with spaces
    $svg = str_replace("\n", " ", $svg);

    // escape svg for shell
    $svg = escapeshellarg($svg);

    // `--pipe`: read input from pipe (stdin)
    // `--export-filename -`: write output to stdout
    // `-w 495 -h 195`: set width and height of the output image
    // `--export-type png`: set the output format to PNG
    $cmd = "echo {$svg} | inkscape --pipe --export-filename - -w {$cardWidth} -h {$cardHeight} --export-type png";

    // convert svg to png
    $png = shell_exec($cmd); // skipcq: PHP-A1009

    // check if the conversion was successful
    if (empty($png)) {
        // `2>&1`: redirect stderr to stdout
        $error = shell_exec("$cmd 2>&1"); // skipcq: PHP-A1009
        throw new InvalidArgumentException("Failed to convert SVG to PNG: {$error}", 500);
    }

    // return the generated png
    return $png;
}

/**
 * Return headers and response based on type
 *
 * @param string|array $output The stats (array) or error message (string) to display
 * @param array<string,string>|NULL $params Request parameters
 * @param int $errorCode The HTTP error code (used for JSON responses)
 * @return array The Content-Type header and the response body, and status code in case of an error
 */
function generateOutput(string|array $output, array $params = null, int $errorCode = 200): array
{
    $params = $params ?? $_REQUEST;

    $requestedType = $params["type"] ?? "svg";

    // output JSON data
    if ($requestedType === "json") {
        // generate array from output
        $data = gettype($output) === "string" ? ["error" => $output, "code" => $errorCode] : $output;
        return [
            "contentType" => "application/json",
            "body" => json_encode($data),
        ];
    }

    // generate SVG card
    $svg = gettype($output) === "string" ? generateErrorCard($output, $params) : generateCard($output, $params);

    // some renderers such as inkscape doesn't support transparent colors in hex format, so we need to convert them
    $svg = convertHexColors($svg);

    // output PNG card
    if ($requestedType === "png") {
        try {
            // extract width from SVG
            $cardWidth = (int) preg_replace("/.*width=[\"'](\d+)px[\"'].*/", "$1", $svg);
            $cardHeight = (int) preg_replace("/.*height=[\"'](\d+)px[\"'].*/", "$1", $svg);
            $png = convertSvgToPng($svg, $cardWidth, $cardHeight);
            return [
                "contentType" => "image/png",
                "body" => $png,
            ];
        } catch (Exception $e) {
            return [
                "contentType" => "image/svg+xml",
                "status" => 500,
                "body" => generateErrorCard($e->getMessage(), $params),
            ];
        }
    }

    // remove animations if disable_animations is set
    if (isset($params["disable_animations"]) && $params["disable_animations"] == "true") {
        $svg = removeAnimations($svg);
    }

    // output SVG card
    return [
        "contentType" => "image/svg+xml",
        "body" => $svg,
    ];
}

/**
 * Set headers and output response
 *
 * @param string|array $output The Content-Type header and the response body
 * @param int $responseCode The HTTP response code to send (stored for JSON consumers but always returns 200 for images)
 * @return void The function exits after sending the response
 */
function renderOutput(string|array $output, int $responseCode = 200): void
{
    $response = generateOutput($output, null, $responseCode);
    // Always return HTTP 200 for SVG/PNG so GitHub's image proxy (Camo) displays error cards
    // instead of broken images. The original error code is included in JSON responses.
    http_response_code(200);
    header("Content-Type: {$response["contentType"]}");
    exit($response["body"]);
}
