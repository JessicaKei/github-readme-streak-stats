<?php

declare(strict_types=1);

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
