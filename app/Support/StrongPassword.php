<?php

namespace App\Support;

final class StrongPassword
{
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
