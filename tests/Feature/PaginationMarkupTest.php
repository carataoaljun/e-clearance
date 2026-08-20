<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every layout loads Bootstrap 5 and the portal CSS styles `.pagination` and
 * `.page-link`, but Laravel's paginator defaulted to its Tailwind view. With no
 * Tailwind stylesheet in the project, that view rendered both its `sm:hidden`
 * mobile block and its `hidden sm:flex` desktop block at once, and its chevron
 * `<svg class="w-5 h-5">` had no size rule, so it painted at full container width.
 */
class PaginationMarkupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['security_audit_logs', 'main_admin'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('main_admin', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
        });
        Schema::create('security_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 100);
            $table->string('actor_guard', 30)->nullable();
            $table->string('actor_id', 100)->nullable();
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_paginated_pages_render_bootstrap_markup_not_unstyled_tailwind(): void
    {
        // More than one page of activity so the links actually render.
        foreach (range(1, 30) as $index) {
            DB::table('security_audit_logs')->insert([
                'event' => 'authentication.login',
                'actor_guard' => 'student',
                'actor_id' => '2023-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0 Safari/537.36',
                'ip_address' => '10.0.0.'.$index,
                'created_at' => Carbon::now()->toDateTimeString(),
            ]);
        }

        $admin = MainAdmin::create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);

        $html = $this->actingAs($admin, 'admin')->get(route('activity.index'))->assertOk()->getContent();

        $this->assertStringContainsString('class="pagination', $html);
        $this->assertStringContainsString('page-link', $html);
        $this->assertStringContainsString('page-item', $html);

        // The Tailwind view's tells: unstyled utility classes and a bare chevron svg.
        $this->assertStringNotContainsString('sm:hidden', $html);
        $this->assertStringNotContainsString('hidden sm:flex', $html);
        $this->assertStringNotContainsString('w-5 h-5', $html);

        // Both views ship a mobile and a desktop block. The difference that matters is
        // that these are gated by Bootstrap display utilities, which the loaded
        // Bootstrap stylesheet honours — so only one block is ever visible, instead of
        // the duplicated Previous/Next pair plus numbered links in the bug report.
        $this->assertStringContainsString('flex-fill d-sm-none', $html);
        $this->assertStringContainsString('d-none flex-sm-fill d-sm-flex', $html);
    }
}
