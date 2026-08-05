<?php

namespace Tests\Feature;

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
}

