<?php

namespace App\Support;

final class StrongPassword
{
    /** Shared by the server rules and the `pattern` attribute on password inputs. */
    public const PATTERN = '(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}';

    public const REQUIREMENT_MESSAGE = 'Use at least 8 characters with uppercase, lowercase, a number, and a special character.';

    /** Login rules: the submitted password must look like a password this system would issue. */
    public static function loginRules(): array
    {
        return ['required', 'string', 'max:128', 'regex:/^'.self::PATTERN.'$/su'];
    }

    public static function loginMessages(string $field = 'password'): array
    {
        return [$field.'.regex' => self::REQUIREMENT_MESSAGE];
    }

    public static function generate(int $length = 12): string
    {
        $length = max(8, $length);
        $groups = [
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz',
            '0123456789',
            '!@#$%^&*',
        ];

        $characters = array_map(
            fn (string $group) => $group[random_int(0, strlen($group) - 1)],
            $groups
        );
        $pool = implode('', $groups);

        while (count($characters) < $length) {
            $characters[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$characters[$index], $characters[$swap]] = [$characters[$swap], $characters[$index]];
        }

        return implode('', $characters);
    }
}
