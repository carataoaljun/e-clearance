<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalOverlayDesignTest extends TestCase
{
    public function test_all_portal_layouts_load_the_shared_overlay_design(): void
    {
        foreach ([
            resource_path('views/layouts/portal.blade.php'),
            resource_path('views/instructor/layouts/instructor.blade.php'),
            resource_path('views/mainAdmin/layouts/admin.blade.php'),
        ] as $layout) {
            $source = file_get_contents($layout);
            $this->assertStringContainsString("asset('css/portal_overlays.css')", $source);
            $this->assertStringContainsString('panel-close', $source);
            $this->assertStringContainsString('aria-controls="notifPanel"', $source);
        }
    }

    public function test_overlay_styles_are_compact_responsive_and_liquid_glass(): void
    {
        $css = file_get_contents(public_path('css/portal_overlays.css'));

        $this->assertStringContainsString('width: min(390px', $css);
        $this->assertStringContainsString('width:min(540px', $css);
        $this->assertStringContainsString('backdrop-filter: blur(28px)', $css);
        $this->assertStringContainsString('@media(max-width:700px)', $css);
    }

    public function test_main_admin_now_has_topbar_notification_access(): void
    {
        $source = file_get_contents(resource_path('views/mainAdmin/layouts/admin.blade.php'));

        $this->assertStringContainsString('id="notifBtn"', $source);
        $this->assertStringContainsString('function closeAdminOverlays()', $source);
    }
}
