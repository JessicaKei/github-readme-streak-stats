<?php

declare(strict_types=1);

require_once __DIR__ . "/card-generators/streak-card-generator.php";
require_once __DIR__ . "/card-generators/error-card-generator.php";
require_once __DIR__ . "/utils.php";

/**
 * Converts an SVG card to a PNG image
 *
 * @param string $svg The SVG for the card as a string
 * @param int $cardWidth The width of the card
 * @return string The generated PNG data
 */
function convertSvgToPng(string $svg, int $cardWidth, int $cardHeight): string
{
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
 * Wrap raw data blocks into a standardized JSON response envelope.
 *
 * @param array $data The statistics dataset array or raw exception descriptor mappings.
 * @param int $code Target application or HTTP execution logic status marker (0 maps to 200).
 *
 * @return array{contentType: string, status: int, body: string} Configured JSON response descriptor.
 */
function buildJsonResponse(array $data, int $code = 0): array
{
    return [
        "contentType" => "application/json",
        "status" => $code ?: 200,
        "body" => json_encode($data),
    ];
}


/**
 * Wrap standalone vector markup into a standardized SVG response envelope.
 *
 * @param string $svg The complete standalone SVG graphic layout markup string.
 * @param int $code Target application or HTTP execution logic status marker (0 maps to 200).
 *
 * @return array{contentType: string, status: int, body: string} Configured SVG response descriptor.
 */
function buildSvgResponse(string $svg, int $code = 0): array
{
    return [
        "contentType" => "image/svg+xml",
        "status" => $code ?: 200,
        "body" => $svg
    ];
}


/**
 * Wrap compressed binary stream data into a standardized PNG response envelope.
 *
 * @param string $png Binary raw stream payload blocks generated from the vector layout.
 * @param int $code Target application or HTTP execution logic status marker (0 maps to 200).
 * @return array{contentType: string, status: int, body: string} Configured PNG response descriptor.
 */
function buildPngResponse(string $png, int $code = 0): array
{
    return [
        "contentType" => "image/png",
        "status" => $code ?: 200,
        "body" => $png
    ];
}


/**
 * Strategy handler to evaluate and package failed runtime operational execution vectors.
 *
 * Branches execution parameters according to the required client layout formatting types,
 * managing safe static rendering conditions alongside explicit error code bindings.
 *
 * @param string $output The raw unvetted exception or operational validation failure message string.
 * @param array<string,string> $params Request configuration parameters dictionary context.
 * @param string $requestedType Target MIME strategy type specifier (json, png, or svg).
 * @param int $errorCode Evaluated terminal status code representing the logical system failure type.
 *
 * @return array{contentType: string, status: int, body: string} Configured operational error payload envelope.
 */
function buildErrorResponse(string $output, array $params, string $requestedType, int $errorCode): array
{
    if ($requestedType === "json") {
        $data = [
            "error" => $output,
            "code" => $errorCode
        ];

        return buildJsonResponse($data, $errorCode);
    }

    if ($requestedType === "png") {
        $params["disable_animations"] = "true";
    }

    $cardData = generateErrorCard($output, $params);

    if ($requestedType === "png") {
        $png = convertSvgToPng((string)$cardData["svg"], (int)$cardData["width"], (int)$cardData["height"]);

        return buildPngResponse($png, $errorCode);
    }

    return buildSvgResponse($cardData["svg"], $errorCode);
}


/**
 * Strategy handler to evaluate and compile successful core operational data layers.
 *
 * Dispatches active data mappings directly into specialized layout factories, managing
 * automated vector canvas dimension extraction blocks when static file exports are initiated.
 *
 * @param array $output Calculated repository metrics and core streak timelines dataset model.
 * @param array<string,string> $params Request configuration parameters dictionary context.
 * @param string $requestedType Target MIME strategy type specifier (json, png, or svg).
 *
 * @return array{contentType: string, status: int, body: string} Configured operational success payload envelope.
 */
function buildSuccessResponse(array $output, array $params, string $requestedType): array
{
    if ($requestedType === "json") {
        return buildJsonResponse($output);
    }

    if ($requestedType === "png") {
        $params["disable_animations"] = "true";
    }

    $cardData = generateCard($output, $params);

    if ($requestedType === "png") {
        $png = convertSvgToPng((string)$cardData["svg"], (int)$cardData["width"], (int)$cardData["height"]);

        return buildPngResponse($png);
    }

    return buildSvgResponse($cardData["svg"]);
}

/**
 * Factory to build a structured HTTP response dictionary based on requested format types.
 *
 * @param string|array $output The stats data model array or a raw error string message.
 * @param array<string,string>|null $params Request configuration parameters.
 * @param int $errorCode Standard HTTP status code used strictly for JSON consumers.
 *
 * @return array{contentType: string, body: string, status: int} Evaluated response payload descriptor.
 */
function buildResponse(string|array $output, ?array $params = null, int $errorCode = 200): array
{
    $params = resolveParams($params);
    $requestedType = $params["type"] ?? "svg";

    try {
        if (gettype($output) === "string") {
            $errorCode = $errorCode === 200 || $errorCode === 0
                ? 500
                : $errorCode
            ;

            return buildErrorResponse($output, $params, $requestedType, $errorCode);
        }

        return buildSuccessResponse($output, $params, $requestedType);
    }
    catch (\Exception $e) {
        try {
            return buildErrorResponse($e->getMessage(), $params, $requestedType, $e->getCode() ?: 500);
        }
        catch (\Exception $e) {
            $cardData = generateErrorCard($e->getMessage(), $params);

            return buildSvgResponse($cardData["svg"], $e->getCode() ?: 500);
        }
    }
}


/**
 * Flush content headers and output payload streams directly into the server network buffer.
 *
 * Authentically propagates HTTP status codes across all MIME types (including images and JSON),
 * preventing downstream proxy asset networks (such as GitHub Camo CDN) from caching short-lived
 * error cards, while forcing immediate updates once processing parameters stabilize.
 *
 * @param string|array $output Calculated repository metrics data model or a raw error string message.
 * @param int $responseCode Target logic processing or validation evaluation status code.
 * @return void Code halts sequence runtime execution immediately upon flushing the stream payload.
 */
function sendResponse(string|array $output, int $responseCode = 200): void
{
    try {
        $response = buildResponse($output, null, $responseCode);

        http_response_code($response["status"]);
        header("Content-Type: {$response["contentType"]}");

        exit($response["body"]);
    }
    catch (\Throwable $error) {
        http_response_code(500);
        header("Content-Type: text/plain; charset=utf-8");

        echo "FATAL ERROR IN CODE:\n";
        echo "Message: " . $error->getMessage() . "\n";
        echo "File: " . $error->getFile() . "\n";
        echo "Line: " . $error->getLine() . "\n";
        echo "Trace:\n" . $error->getTraceAsString();

        exit();
    }
}
