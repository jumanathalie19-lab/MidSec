<?php
// helpers/Request.php

class Request {

    // ------------------------------------------------------------
    //  Ensures the incoming request uses the expected HTTP method.
    //  Terminates with 405 if it doesn't match.
    // ------------------------------------------------------------
    public static function requireMethod(string $method): void {
        $actual = $_SERVER['REQUEST_METHOD'] ?? '';

        if (strtoupper($actual) !== strtoupper($method)) {
            Response::error(
                "Method not allowed. Expected {$method}.",
                405
            );
        }
    }

    // ------------------------------------------------------------
    //  Reads and decodes the JSON request body into an array.
    //  Returns an empty array if the body is missing or invalid,
    //  so downstream ?? checks in the controller behave safely.
    // ------------------------------------------------------------
    public static function json(): array {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [];
        }

        return $data;
    }

    // ------------------------------------------------------------
    //  Trims and strips tags from a string input. Safe to call
    //  on already-empty or non-string values.
    // ------------------------------------------------------------
    public static function sanitizeString($value): string {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return trim(strip_tags($value));
    }

    // ------------------------------------------------------------
//  Validates an optional date string (e.g. from a query param
//  like ?date_from=2026-08-01). Returns null if the value is
//  null or empty — callers pass their own $_GET[...] ?? null
//  extraction in, this method only validates/normalizes.
//  Terminates with 400 if a non-empty value isn't a valid date
//  in the given format.
// ------------------------------------------------------------
public static function optionalDate(?string $value, string $format = 'Y-m-d'): ?string {
    if ($value === null || trim($value) === '') {
        return null;
    }

    $value = trim($value);
    $date  = DateTime::createFromFormat($format, $value);

    $errors = DateTime::getLastErrors();
    $hasErrors = $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

    if (!$date || $hasErrors || $date->format($format) !== $value) {
        Response::error(
            "Invalid date format. Expected {$format} (e.g. 2026-08-01).",
            400
        );
    }

    return $value;
}
}