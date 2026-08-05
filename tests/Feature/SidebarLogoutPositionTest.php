<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class SidebarLogoutPositionTest extends TestCase
{
    public function test_logout_groups_follow_the_navigation_in_all_sidebar_layouts(): void
    {
        $layouts = [
            resource_path('views/layouts/portal.blade.php'),
            resource_path('views/instructor/layouts/instructor.blade.php'),
            resource_path('views/mainAdmin/layouts/admin.blade.php'),
        ];

        foreach ($layouts as $layout) {
            $contents = (string) file_get_contents($layout);
            $navigationPosition = strpos($contents, 'sidebar-nav-links');
            $logoutPosition = strpos($contents, 'sidebar-account-group');

            $this->assertNotFalse($navigationPosition, "Sidebar navigation is missing from {$layout}.");
            $this->assertNotFalse($logoutPosition, "Logout group is missing from {$layout}.");
            $this->assertGreaterThan($navigationPosition, $logoutPosition, "Logout must follow the navigation in {$layout}.");

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($contents);
            libxml_clear_errors();
            $xpath = new DOMXPath($dom);
            $nestedLogoutGroups = $xpath->query(
                '//div[contains(concat(" ", normalize-space(@class), " "), " sidebar-nav-links ")]'
                .'//div[contains(concat(" ", normalize-space(@class), " "), " sidebar-account-group ")]',
            );

            $this->assertSame(1, $nestedLogoutGroups->length, "Logout must be part of the navigation in {$layout}.");
        }
    }

    public function test_logout_group_is_not_pinned_to_the_bottom(): void
    {
        $stylesheet = (string) file_get_contents(public_path('css/student_portal_chrome.css'));

        $this->assertMatchesRegularExpression(
            '/body\.student-portal-theme \.sidebar-account-group\s*\{[^}]*margin-top:\s*10px;/s',
            $stylesheet,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/body\.student-portal-theme \.sidebar-account-group\s*\{[^}]*margin-top:\s*auto;/s',
            $stylesheet,
        );
    }

    public function test_main_admin_uses_one_scrollable_mobile_menu(): void
    {
        $stylesheet = (string) file_get_contents(public_path('css/main_admin_portal.css'));

        $this->assertMatchesRegularExpression(
            '/body\.main-admin-portal \.sidebar-nav-links\s*\{[^}]*flex:\s*1 1 auto;[^}]*overflow-y:\s*auto;/s',
            $stylesheet,
        );
        $this->assertStringNotContainsString('body.main-admin-portal .sidebar-account-group', $stylesheet);
    }
}
