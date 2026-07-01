<?php

declare(strict_types=1);

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

    // We describe the stream descriptors (0 - stdin, 1 - stdout, 2 - stderr)
    $descriptors = [
        0 => ["pipe", "r"], // We will write SVG to stdin
        1 => ["pipe", "w"], // We will read PNG from stdout
        2 => ["pipe", "w"]  // We will read errors from stderr
    ];

    // `--pipe`: read input from pipe (stdin)
    // `--export-filename -`: write output to stdout
    // `-w 495 -h 195`: set width and height of the output image
    // `--export-type png`: set the output format to PNG
    $cmd = "inkscape --pipe --export-filename - -w {$cardWidth} -h {$cardHeight} --export-type png";

    $process = proc_open($cmd, $descriptors, $pipes);

    if (!is_resource($process)) {
        throw new InvalidArgumentException("Failed to initialize system Inkscape process interface.", 500);
    }

    fwrite($pipes[0], $svg);
    fclose($pipes[0]);

    $png = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $returnCode = proc_close($process);

    if ($returnCode !== 0 || empty($png)) {
        throw new InvalidArgumentException("Failed to convert SVG to PNG via proc_open: {$error}", 500);
    }

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

    if ($requestedType === "png") {
        $params["disable_animations"] = "true";
    }

    $cardData = outputIsError($output)
        ? generateErrorCard($output, $params)
        : generateCard($output, $params)
    ;

    $svg = $cardData["svg"];

    // output PNG card
    if ($requestedType === "png") {
        try {
            $png = convertSvgToPng($svg, (int)$cardData["width"], (int)$cardData["height"]);

            return [
                "contentType" => "image/png",
                "body" => $png,
            ];
        }
        catch (Exception $e) {
            return [
                "contentType" => "image/svg+xml",
                "status" => 500,
                "body" => generateErrorCard($e->getMessage(), $params),
            ];
        }
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
