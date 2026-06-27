<?php

declare(strict_types=1);

require_once "github.php";
require_once "whitelist.php";


/**
 * Calculate the array of years to fetch data for based on registration date.
 *
 * @param string $userCreatedDateTimeString ISO 8601 datetime string (YYYY-MM-DDTHH:MM:SSZ).
 * @param int|null $startingYear Override the minimum year to get graphs for.
 * 
 * @return array<int> List of years to request.
 */
function calculateYearsRange(string $userCreatedDateTimeString, ?int $startingYear): array
{
    $currentYear = intval(date("Y"));
    $userCreatedYear = intval(explode("-", $userCreatedDateTimeString)[0]);
    
    $minimumYear = $startingYear ?: $userCreatedYear;

    // Limitation: Git was created in 2005
    $minimumYear = max($minimumYear, 2005);
    
    return range($minimumYear, $currentYear);
}


/**
 * Normalize the aliased GraphQL response into a standardized legacy format array.
 *
 * @param stdClass $responseObj Decoded GraphQL response with yearly aliases.
 * @param array<int> $years Requested list of years.
 * @param string $createdAt User's account creation date string.
 * 
 * @return array<int,stdClass> List of standardized contribution graph response objects by year.
 */
function standardizeAdvancedResponse(stdClass $responseObj, array $years, string $createdAt): array
{
    $standardizedResponses = [];

    foreach ($years as $year) {
        $alias = "year_" . $year;
        
        if (isset($responseObj->data->user->$alias)) {
            $dummyResponse = new stdClass();
            $dummyResponse->data = new stdClass();
            $dummyResponse->data->user = new stdClass();
            $dummyResponse->data->user->createdAt = $createdAt;
            $dummyResponse->data->user->contributionsCollection = $responseObj->data->user->$alias;
            
            $standardizedResponses[$year] = $dummyResponse;
        }
    }

    return $standardizedResponses;
}


/**
 * Get all HTTP request responses for user's contributions using a packed GraphQL query.
 *
 * @param string $user GitHub username to get graphs for.
 * @param int|null $startingYear Override the minimum year to get graphs for.
 * 
 * @return array<int,stdClass> List of standardized contribution graph response objects by year.
 */
function getContributionGraphs(string $user, ?int $startingYear = null): array
{
    if (!isWhitelisted($user)) {
        throw new InvalidArgumentException("User not in whitelist.", 403);
    }

    $createdAt = fetchUserCreationDate($user);
    $years = calculateYearsRange($createdAt, $startingYear);
    $responseObj = fetchAdvancedContributionData($user, $years);
    $standardizedResponses = standardizeAdvancedResponse($responseObj, $years, $createdAt);

    return $standardizedResponses;
}


/**
 * Get an array of all dates with the number of contributions.
 *
 * @param array<int,stdClass> $contributionCalendars List of GraphQL response objects by year.
 * 
 * @return array<string,int> Y-M-D dates mapped to the number of contributions.
 */
function getContributionDates(array $contributionGraphs): array
{
    $contributions = [];
    $today = date("Y-m-d");
    $tomorrow = date("Y-m-d", strtotime("tomorrow"));

    // sort contribution calendars by year key
    ksort($contributionGraphs);

    foreach ($contributionGraphs as $graph) {
        $weeks = $graph->data->user->contributionsCollection->contributionCalendar->weeks;

        foreach ($weeks as $week) {
            foreach ($week->contributionDays as $day) {
                $date = $day->date;
                $count = $day->contributionCount;

                // count contributions up to today, and also include tomorrow's contributions if the user has already made a contribution
                if ($date <= $today || ($date == $tomorrow && $count > 0)) {
                    // add contributions to the array
                    $contributions[$date] = $count;
                }
            }
        }
    }

    return $contributions;
}


/**
 * Normalize name of the day (e.g., "  tuesday " -> "Tue").
 *
 * @param string $day Raw weekday string.
 * 
 * @return string Normalize name of the day.
 */
function normalizeDay(string $day): string
{
    $clean = strtolower(trim($day));
    $short = substr($clean, 0, 3);
    $normalizedDay = ucfirst($short);

    return $normalizedDay;
}


/**
 * Normalize names of days of the week (eg. ["Sunday", " mon", "TUE"] -> ["Sun", "Mon", "Tue"]).
 *
 * @param array<string> $days List of days of the week.
 * 
 * @return array<string> List of normalized days of the week.
 */
function normalizeDays(array $days): array
{
    static $validDays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    
    $normalizedDays = [];

    foreach ($days as $day) {
        $normalizedDay = normalizeDay($day);
        
        if (in_array($normalizedDay, $validDays, true)) {
            $normalizedDays[] = $normalizedDay;
        }
    }

    return $normalizedDays;
}


/**
 * Check if a day is an excluded day of the week.
 *
 * @param string $date Date to check (Y-m-d).
 * @param array<string> $excludedDays List of days of the week to exclude.
 * 
 * @return bool True if the day is excluded, false otherwise.
 */
function isExcludedDay(string $date, array $excludedDays): bool
{
    if (empty($excludedDays)) {
        return false;
    }

    // "D" = Mon, Tue, Wed, etc.
    $day = date("D", strtotime($date));

    $isExcluded = in_array($day, $excludedDays, true);

    return $isExcluded;
}


/**
 * Create a standardized streak data array.
 *
 * @param string $start Y-M-D start date.
 * @param string $end Y-M-D end date.
 * @param int $length Length of the streak.
 * 
 * @return array{start: string, end: string, length: int}
 */
function createStreakObject(string $start, string $end, int $length): array
{
    $streak = [
        "start" => $start,
        "end" => $end,
        "length" => $length
    ];

    return $streak;
}


/**
 * Core mathematical engine to calculate streaks from sequential contribution data.
 *
 * @param array<string,int> $contributions Sequential dates mapped to contribution counts.
 * @param string $currentDate The final date in the sequence (today or current week Sunday).
 * @param string $startDate The first date in the sequence (account creation or start week Sunday).
 * @param string $mode Calculation mode identifier (e.g., "daily" or "weekly").
 * @param string $dateOffset Relative PHP date string to subtract on streak breakdown (e.g., "-1 day" or "-7 days").
 * @param string $firstContribution The exact date of the user's very first contribution/registration.
 * @param array<string> $excludedDays Optional list of 3-letter weekdays to forgive empty contribution slots.
 * 
 * @return array{
 *     mode: string,
 *     totalContributions: int,
 *     firstContribution: string,
 *     longestStreak: array{start: string, end: string, length: int},
 *     currentStreak: array{start: string, end: string, length: int}
 * } Standardized streak statistics block.
 */
function createContributionStats(array $contributions, string $currentDate, string $startDate, string $mode, string $dateOffset, string $firstContribution, array $excludedDays = []): array
{
    $totalContributions = 0;
    $currentLength = 0;
    $currentStart = $startDate;
    $longestStreak = createStreakObject($startDate, $startDate, 1);

    foreach ($contributions as $date => $count) {
        $totalContributions += $count;
        
        $isStreakActive = $count > 0 || ($currentLength > 0 && isExcludedDay($date, $excludedDays));

        if ($isStreakActive) {
            $currentLength++;

            if ($currentLength === 1) {
                $currentStart = $date;
            }
        } 
        elseif ($date !== $currentDate) { 
            if ($currentLength > $longestStreak["length"]) {
                $previousPeriod = date("Y-m-d", strtotime($dateOffset, strtotime($date)));

                $longestStreak = createStreakObject($currentStart, $previousPeriod, $currentLength);
            }

            $currentLength = 0;
        }
    }

    $currentStreak;

    if ($currentLength === 0) {
        $currentStreak = createStreakObject($currentDate, $currentDate, 0);
    }
    else {
        if ($currentLength > $longestStreak["length"]) {
            $longestStreak = createStreakObject($currentStart, $currentDate, $currentLength);
        }

        $currentStreak = createStreakObject($currentStart, $currentDate, $currentLength);
    }

    $stats = [
        "mode" => $mode,
        "totalContributions" => $totalContributions,
        "firstContribution" => $firstContribution,
        "longestStreak" => $longestStreak,
        "currentStreak" => $currentStreak,
    ];

    return $stats;
}


/**
 * Get a stats array with the contribution count, daily streak, and dates.
 *
 * @param array<string,int> $contributions Y-M-D contribution dates with contribution counts.
 * @param array<string> $excludedDays List of days of the week to exclude.
 * 
 * @return array<string,mixed> Streak stats.
 */
function getContributionStats(array $contributions, array $excludedDays = []): array
{
    if (empty($contributions)) {
        throw new AssertionError("No contributions found.", 204);
    }

    $today = array_key_last($contributions);
    $first = array_key_first($contributions);

    $stats = createContributionStats($contributions, $today, $first, "daily", "-1 day", $first, $excludedDays);
    $stats["excludedDays"] = $excludedDays;

    return $stats;
}


/**
 * Get the start date (Sunday) of the week for a given date.
 *
 * @param string $date Date in Y-m-d format.
 * 
 * @return string Sunday date of the current week (Y-m-d).
 */
function getStartOfWeek(string $date): string
{
    $timestamp = strtotime($date);
    $dayOfWeek = (int)date("w", $timestamp);
    
    // Subtract days in seconds. If today is Sunday ($dayOfWeek = 0), the same date will be returned.
    $sunday = date("Y-m-d", $timestamp - ($dayOfWeek * 86400));

    return $sunday;
}


/**
 * Group daily contributions into weekly blocks starting from Sunday.
 *
 * @param array<string,int> $contributions Y-M-D contribution dates with contribution counts.
 * @return array<string,int> Sunday dates mapped to total weekly contribution counts.
 */
function groupContributionsByWeek(array $contributions): array
{
    $weeks = [];

    foreach ($contributions as $date => $count) {
        $week = getStartOfWeek($date);

        $weeks[$week] = ($weeks[$week] ?? 0) + $count;
    }

    return $weeks;
}


/**
 * Get a stats array with the contribution count, weekly streak, and dates
 *
 * @param array<string,int> $contributions Y-M-D contribution dates with contribution counts
 * @return array<string,mixed> Streak stats
 */
function getWeeklyContributionStats(array $contributions): array
{
    if (empty($contributions)) {
        throw new AssertionError("No contributions found.", 204);
    }

    $today = array_key_last($contributions);
    $first = array_key_first($contributions);
    $thisWeek = getStartOfWeek($today);
    $firstWeek = getStartOfWeek($first);
    $weeks = groupContributionsByWeek($contributions);
    $stats = createContributionStats($weeks, $thisWeek, $firstWeek, "weekly", "-7 days", $first);
    
    return $stats;
}
