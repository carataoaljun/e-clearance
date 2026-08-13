<?php

namespace App\Support;

final class PersonName
{
    /**
     * Letters, spaces, hyphens and apostrophes — no digits and no other symbols.
     * Shared by the server rules and the `pattern` attribute on name inputs, so
     * the browser refuses exactly what the controller would refuse.
     */
    public const PATTERN = "[\p{L}\s'\-]+";

    public const REQUIREMENT_MESSAGE = 'Use letters only. Spaces, hyphens, and apostrophes are allowed; numbers and other special characters are not.';

    /** @return list<string> */
    public static function requiredRules(int $max = 20): array
    {
        return ['required', 'string', 'min:2', 'max:'.$max, self::regex()];
    }

    /** @return list<string> */
    public static function optionalRules(int $max = 20): array
    {
        return ['nullable', 'string', 'max:'.$max, self::regex()];
    }

    public static function regex(): string
    {
        return 'regex:/^'.self::PATTERN.'$/u';
    }

    /**
     * Message overrides so a rejected name explains the rule instead of showing
     * the raw "format is invalid" default.
     *
     * @return array<string, string>
     */
    public static function messages(string ...$fields): array
    {
        $messages = [];

        foreach ($fields as $field) {
            $messages[$field.'.regex'] = self::REQUIREMENT_MESSAGE;
        }

        return $messages;
    }
}
