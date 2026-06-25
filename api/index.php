<?php

declare(strict_types=1);

// Enable buffering at startup
ob_start();

// load functions
require_once dirname(__DIR__, 1) . "/vendor/autoload.php";
require_once "stats.php";
require_once "card.php";
require_once "cache.php";
require_once "generator.php";

// load .env
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1));
$dotenv->safeLoad();

// if environment variables are not loaded, display error
if (!isset($_ENV["TOKEN"])) {
    $message = file_exists(dirname(__DIR__, 1) . "/.env")
        ? "Missing token in config. Check Contributing.md for details."
        : ".env was not found. Check Contributing.md for details.";
    renderOutput($message, 500);
}

// redirect to demo site if user is not given
if (!isset($_REQUEST["user"])) {
    header("Location: demo/");
    exit();
}

try {
    $stats = generateStreakStats($_REQUEST["user"], $_REQUEST);
    
    // set cache to refresh once per day (24 hours)
    $cacheSeconds = CACHE_DURATION;
    header("Expires: " . gmdate("D, d M Y H:i:s", time() + $cacheSeconds) . " GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: public, max-age=$cacheSeconds");
    
    renderOutput($stats);
    
} catch (InvalidArgumentException | AssertionError $error) {
    error_log("Error {$error->getCode()}: {$error->getMessage()}");
    if ($error->getCode() >= 500) {
        error_log($error->getTraceAsString());
    }
    
    // If an error occurs, reset the Vercel Edge cache so that it does not remember the broken plate.
    header("Cache-Control: no-cache, no-store, must-revalidate");
    renderOutput($error->getMessage(), $error->getCode());
}

// FLUSH AND OUTPUT THE BUFFER AT THE VERY END
$output = ob_get_clean();
header("Content-Length: " . strlen($output));
echo $output;
exit();
