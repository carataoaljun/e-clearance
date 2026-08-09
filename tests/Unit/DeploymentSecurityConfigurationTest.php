<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DeploymentSecurityConfigurationTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 2);
    }

    public function test_public_directory_contains_only_the_laravel_php_entry_point(): void
    {
        $publicDirectory = $this->projectRoot.DIRECTORY_SEPARATOR.'public';
        $phpFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($publicDirectory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (
                $file->isFile()
                && preg_match('/\.(?:php(?:\d*|s)|phtml|phar)$/i', $file->getFilename()) === 1
            ) {
                $phpFiles[] = str_replace(
                    '\\',
                    '/',
                    substr($file->getPathname(), strlen($publicDirectory) + 1),
                );
            }
        }

        sort($phpFiles, SORT_STRING);

        $this->assertSame(['index.php'], $phpFiles);
    }

    public function test_apache_rules_reject_direct_requests_for_php_scripts(): void
    {
        $rootRules = file_get_contents($this->projectRoot.DIRECTORY_SEPARATOR.'.htaccess');
        $publicRules = file_get_contents(
            $this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'.htaccess',
        );

        $this->assertIsString($rootRules);
        $this->assertIsString($publicRules);

        foreach ([$rootRules, $publicRules] as $rules) {
            $this->assertStringContainsString('(?:php(?:\d*|s)|phtml|phar)', $rules);
            $this->assertStringContainsString('[R=404,L,NC]', $rules);
        }

        $this->assertStringContainsString('^(?!public/index\.php$)', $rootRules);
        $this->assertStringContainsString('^(?!index\.php$)', $publicRules);
    }

    public function test_shared_hosting_php_configuration_hides_runtime_errors(): void
    {
        foreach (['.user.ini', 'public'.DIRECTORY_SEPARATOR.'.user.ini'] as $relativePath) {
            $settings = parse_ini_file(
                $this->projectRoot.DIRECTORY_SEPARATOR.$relativePath,
                false,
                INI_SCANNER_RAW,
            );

            $this->assertIsArray($settings);
            $this->assertSame('Off', $settings['display_errors'] ?? null);
            $this->assertSame('Off', $settings['display_startup_errors'] ?? null);
            $this->assertSame('On', $settings['log_errors'] ?? null);
            $this->assertSame('Off', $settings['expose_php'] ?? null);
        }
    }
}
