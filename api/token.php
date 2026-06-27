<?php

declare(strict_types=1);


/**
 * Load all available GitHub tokens from environment variables
 *
 * @return array<string> List of discovered tokens
 */
function loadTokensFromEnv(): array
{
    $tokens = isset($_ENV["TOKEN"])
        ? [ $_ENV["TOKEN"] ]
        : []
    ;

    for ($index = 2; isset($_ENV["TOKEN{$index}"]); $index++) {
        $tokens[] = $_ENV["TOKEN{$index}"];
    }

    return $tokens;
}


/**
 * Get a reference to the static token pool
 *
 * @return array<string> Reference to the tokens array
 */
function &getTokenPool(): array
{
    static $tokens = null;

    if ($tokens === null) {
        $tokens = loadTokensFromEnv();
    }

    return $tokens;
}


/**
 * Get a random token from the token pool
 *
 * @return string GitHub token
 * 
 * @throws AssertionError if no tokens are available
 */
function getGitHubToken(): string
{
    $tokens = &getTokenPool();

    if (empty($tokens)) {
        throw new AssertionError("There is no GitHub token available.", 500);
    }

    $token = $tokens[array_rand($tokens)];
    
    return $token;
}


/**
 * Remove a token from the token pool if it is rate-limited
 *
 * @param string $token Token to remove
 * @return void
 * @throws AssertionError if no tokens are available after removal
 */
function removeGitHubToken(string $token): void
{
    $tokens = &getTokenPool();
    $index = array_search($token, $tokens, true);

    if ($index !== false) {
        unset($tokens[$index]);
        
        $tokens = array_values($tokens);
    }

    if (empty($tokens)) {
        throw new AssertionError(
            "We are being rate-limited! Check <a href='https://git.io' font-weight='bold'>git.io/streak-ratelimit</a> for details.",
            429,
        );
    }
}
