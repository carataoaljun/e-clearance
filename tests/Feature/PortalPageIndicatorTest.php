<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PortalPageIndicatorTest extends TestCase
{
    public function test_role_portal_layouts_load_the_shared_page_indicator(): void
    {
        $stylesheet = public_path('css/portal_page_indicator.css');
        $component = resource_path('views/components/portal/page-indicator.blade.php');
        $portalLayout = resource_path('views/layouts/portal.blade.php');
        $instructorLayout = resource_path('views/instructor/layouts/instructor.blade.php');
        $adminLayout = resource_path('views/mainAdmin/layouts/admin.blade.php');

        $this->assertFileExists($stylesheet);
        $this->assertFileExists($component);

        foreach ([$portalLayout, $instructorLayout] as $layout) {
            $source = (string) file_get_contents($layout);

            $this->assertStringContainsString("asset('css/portal_page_indicator.css')", $source);
            $this->assertStringContainsString('<x-portal.page-indicator', $source);
        }

        $adminSource = (string) file_get_contents($adminLayout);
        $this->assertStringNotContainsString("asset('css/portal_page_indicator.css')", $adminSource);
        $this->assertStringNotContainsString('<x-portal.page-indicator', $adminSource);
    }

    public function test_every_active_role_page_has_route_specific_indicator_metadata(): void
    {
        $portalSource = (string) file_get_contents(resource_path('views/layouts/portal.blade.php'));
        $instructorSource = (string) file_get_contents(resource_path('views/instructor/layouts/instructor.blade.php'));

        $sharedRoutes = [
            'student.dashboard',
            'student.clearance-updates',
            'student.submission-remark',
            'student.chat-support',
            'office.dashboard',
            'office.submissions',
            'office.clearance.requests',
            'treasurer.dashboard',
            'treasurer.clearance-updates',
            'treasurer.submission-remark',
            'registrar.dashboard',
            'registrar.student-clearance',
            'registrar.qr-scanner',
        ];

        foreach ($sharedRoutes as $route) {
            $this->assertStringContainsString("routeIs('{$route}')", $portalSource);
        }

        foreach (['instructor.dashboard', 'instructor.submissions.index', 'instructor.clearance', 'instructor.chat'] as $route) {
            $this->assertStringContainsString("routeIs('{$route}')", $instructorSource);
        }
    }

    public function test_dashboard_pages_do_not_render_a_duplicate_legacy_header(): void
    {
        $dashboards = [
            resource_path('views/office/dashboard.blade.php'),
            resource_path('views/treasurer/dashboard.blade.php'),
            resource_path('views/registrar/dashboard.blade.php'),
            resource_path('views/instructor/instructor/dashboard.blade.php'),
        ];

        foreach ($dashboards as $dashboard) {
            $source = (string) file_get_contents($dashboard);

            $this->assertStringNotContainsString("@section('hide-page-header'", $source);
            $this->assertStringNotContainsString('<div class="page-header">', $source);
        }
    }

    public function test_page_indicator_component_and_role_views_compile(): void
    {
        $views = [
            resource_path('views/components/portal/page-indicator.blade.php'),
            resource_path('views/layouts/portal.blade.php'),
            resource_path('views/instructor/layouts/instructor.blade.php'),
            resource_path('views/office/dashboard.blade.php'),
            resource_path('views/treasurer/dashboard.blade.php'),
            resource_path('views/registrar/dashboard.blade.php'),
            resource_path('views/instructor/instructor/dashboard.blade.php'),
        ];

        foreach ($views as $view) {
            $compiled = Blade::compileString((string) file_get_contents($view));

            $this->assertNotSame('', trim($compiled));
        }

        $rendered = Blade::render(<<<'BLADE'
            <x-portal.page-indicator
                title="Clearance Updates"
                description="Track the latest clearance activity."
                eyebrow="Clearance Activity"
                icon="bi bi-arrow-repeat"
                badge="Student · 2023-1421"
                badge-icon="bi bi-mortarboard"
                variant="student"
            />
        BLADE);

        $this->assertStringContainsString('portal-page-indicator--student', $rendered);
        $this->assertStringContainsString('Clearance Updates', $rendered);
        $this->assertStringContainsString('bi bi-arrow-repeat', $rendered);
        $this->assertStringContainsString('Student · 2023-1421', $rendered);
    }
}
