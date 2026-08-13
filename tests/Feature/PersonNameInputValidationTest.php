<?php

namespace Tests\Feature;

use App\Support\PersonName;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PersonNameInputValidationTest extends TestCase
{
    /** Every view that collects a first, middle, or last name. */
    private const NAME_VIEWS = [
        'views/layouts/portal.blade.php',
        'views/portal/account/edit.blade.php',
        'views/instructor/layouts/instructor.blade.php',
        'views/mainAdmin/students/index.blade.php',
        'views/mainAdmin/instructors/index.blade.php',
        'views/mainAdmin/treasurers/index.blade.php',
        'views/mainAdmin/registrar/index.blade.php',
        'views/mainAdmin/personnel/index.blade.php',
    ];

    public function test_every_name_input_advertises_the_shared_pattern(): void
    {
        $checked = 0;

        foreach (self::NAME_VIEWS as $view) {
            $path = resource_path($view);
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $number => $line) {
                if (preg_match('/<input[^\n]*name="(?:firstname|middlename|lastname)"/', $line) !== 1) {
                    continue;
                }

                $checked++;
                $where = $view.':'.($number + 1);
                $this->assertStringContainsString('pattern="{{ \App\Support\PersonName::PATTERN }}"', $line, "Missing name pattern at {$where}.");
                $this->assertStringContainsString('title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"', $line, "Missing name title at {$where}.");
            }
        }

        $this->assertSame(35, $checked, 'A name input was added or removed without updating this test.');
    }

    public function test_the_main_admin_name_field_uses_the_same_pattern(): void
    {
        $source = file_get_contents(resource_path('views/mainAdmin/profile/edit.blade.php'));

        $this->assertMatchesRegularExpression(
            '/<input id="admin_name"[^\n]*pattern="\{\{ \\\\App\\\\Support\\\\PersonName::PATTERN \}\}"/',
            $source
        );
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function nameProvider(): array
    {
        return [
            'plain name' => ['Juan', true],
            'hyphenated name' => ['Maria-Clara', true],
            'spaced name' => ['Dela Cruz', true],
            'apostrophe' => ["O'Brien", true],
            'accented letters' => ['Muñoz', true],
            'trailing digit' => ['Juan2', false],
            'digits only' => ['12345', false],
            'at sign' => ['Juan@', false],
            'underscore' => ['Juan_Carlos', false],
            'period' => ['Jr.', false],
            'angle brackets' => ['<script>', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nameProvider')]
    public function test_name_rules_accept_letters_and_hyphens_only(string $name, bool $expected): void
    {
        $required = Validator::make(['firstname' => $name], ['firstname' => PersonName::requiredRules()]);
        $optional = Validator::make(['middlename' => $name], ['middlename' => PersonName::optionalRules()]);

        $this->assertSame($expected, $required->passes(), "Unexpected result for required name '{$name}'.");
        $this->assertSame($expected, $optional->passes(), "Unexpected result for optional name '{$name}'.");
    }

    public function test_a_rejected_name_explains_the_rule(): void
    {
        $validator = Validator::make(
            ['firstname' => 'Juan123'],
            ['firstname' => PersonName::requiredRules()],
            PersonName::messages('firstname')
        );

        $this->assertSame(PersonName::REQUIREMENT_MESSAGE, $validator->errors()->first('firstname'));
    }

    public function test_an_optional_name_may_be_left_blank(): void
    {
        $validator = Validator::make(['middlename' => null], ['middlename' => PersonName::optionalRules()]);

        $this->assertTrue($validator->passes());
    }
}
