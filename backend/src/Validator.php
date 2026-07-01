<?php

namespace App;

/**
 * Minimal server-side input validator. Covers the course requirement
 * ("Input validation on both client and server sides") without pulling in
 * a full validation library — each controller calls Validator::check()
 * with simple rule strings and gets back an array of field => message
 * errors (empty array = valid).
 */
class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules e.g. ['email' => 'required|email', 'capacity' => 'required|int|min:1']
     * @return array<string, string> field => first error message
     */
    public static function check(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            foreach (explode('|', $ruleString) as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $error = self::applyRule($rule, $field, $value, $params);
                if ($error) {
                    $errors[$field] = $error;
                    break; // stop at first failing rule per field
                }
            }
        }

        return $errors;
    }

    private static function applyRule(string $rule, string $field, mixed $value, array $params): ?string
    {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '') {
                    return "{$field} is required.";
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "{$field} must be a valid email address.";
                }
                break;

            case 'int':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    return "{$field} must be a number.";
                }
                break;

            case 'min':
                if ($value !== null && $value !== '' && (float) $value < (float) $params[0]) {
                    return "{$field} must be at least {$params[0]}.";
                }
                break;

            case 'minlen':
                if ($value !== null && strlen((string) $value) < (int) $params[0]) {
                    return "{$field} must be at least {$params[0]} characters.";
                }
                break;

            case 'in':
                if ($value !== null && $value !== '' && !in_array($value, $params, true)) {
                    return "{$field} must be one of: " . implode(', ', $params) . '.';
                }
                break;
        }

        return null;
    }
}
