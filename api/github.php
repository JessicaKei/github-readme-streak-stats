<?php

declare(strict_types=1);

require_once "token.php";


/**
 * Build a GraphQL query to fetch the user's account creation date
 *
 * @param string $user GitHub username
 * 
 * @return string GraphQL query text
 */
function buildUserCreationQuery(string $user): string
{
    $query = "query {
        user(login: \"$user\") {
            createdAt
        }
    }";

    return $query;
}


/**
 * Build ONE dynamic GraphQL query for ALL required years using aliases
 *
 * @param string $user GitHub username
 * @param array<int> $years Array of years to fetch calendars for
 * 
 * @return string Full GraphQL query string
 */
function buildAdvancedContributionQuery(string $user, array $years): string
{
    $yearlyFields = "";
    foreach ($years as $year) {
        $start = "$year-01-01T00:00:00Z";
        $end = "$year-12-31T23:59:59Z";
        
        $yearlyFields .= "
            year_{$year}: contributionsCollection(from: \"$start\", to: \"$end\") {
                contributionCalendar {
                    weeks {
                        contributionDays {
                            contributionCount
                            date
                        }
                    }
                }
            }
        ";
    }

    return "query {
        user(login: \"$user\") {
            $yearlyFields
        }
    }";
}


/** Create a CurlHandle for a POST request to GitHub's GraphQL API
 *
 * @param string $query GraphQL query
 * @param string $token GitHub token to use for the request
 * 
 * @return CurlHandle The curl handle for the request
 */
function getGraphQLCurlHandle(string $query, string $token): CurlHandle
{
    $headers = [
        "Authorization: bearer $token",
        "Content-Type: application/json",
        "Accept: application/vnd.github.v4.idl",
        "User-Agent: GitHub-Readme-Streak-Stats",
    ];

    $body = ["query" => $query];

    // create curl request
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/graphql");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_VERBOSE, false);

    return $ch;
}


/**
 * Execute a single GraphQL query to GitHub API and decode the response
 *
 * @param string $query GraphQL query text
 * 
 * @return stdClass|null Decoded JSON response or null on error
 */
function fetchGraphQLRequest(string $query): ?stdClass
{
    while (true) {
        $token = getGitHubToken();
        $ch = getGraphQLCurlHandle($query, $token);
        $contents = curl_exec($ch);

        curl_close($ch);

        $response = is_string($contents)
            ? json_decode($contents)
            : null
        ;

        $message = $response->errors[0]->message
            ?? ($response->message
                ?? ""
            )
        ;

        if (str_contains(strtolower($message), "rate limit exceeded")) {
            removeGitHubToken($token);

            continue;
        }

        return $response;
    }
}


/**
 * Fetch the user's account creation date from GitHub API
 *
 * @param string $user GitHub username
 * 
 * @return string ISO 8601 datetime string (YYYY-MM-DDTHH:MM:SSZ)
 * 
 * @throws AssertionError if the API response is invalid or missing the date
 */
function fetchUserCreationDate(string $user): string
{
    $query = buildUserCreationQuery($user);
    $response = fetchGraphQLRequest($query);

    $createdAt = $response->data->user->createdAt
        ?? null
    ;

    if (empty($createdAt)) {
        throw new AssertionError("Failed to retrieve user registration date. Check GitHub API status.", 500);
    }

    return $createdAt;
}


/**
 * Fetch contribution calendars for multiple years in ONE single GraphQL request
 *
 * @param string $user GitHub username
 * @param array<int> $years List of years to fetch calendars for
 * 
 * @return stdClass Decoded GraphQL response object
 * 
 * @throws AssertionError if the response is empty or contains API errors
 */
function fetchAdvancedContributionData(string $user, array $years): stdClass
{
    $query = buildAdvancedContributionQuery($user, $years);
    $response = fetchGraphQLRequest($query);

    if (empty($response) || empty($response->data) || !empty($response->errors)) {
        $message = $response->errors[0]->message
            ?? ($response->message ?? "Failed to fetch aggregated GraphQL data.")
        ;

        throw new AssertionError("GitHub API Error: " . $message, 500);
    }

    return $response;
}
