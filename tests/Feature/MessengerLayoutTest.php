<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The messenger used to claim a slice of the viewport — min(72vh, 720px) with a
 * 540px floor — which, stacked under the portal page header, only fitted on a
 * viewport taller than 1000px. On a laptop or at any browser zoom the thread and
 * the composer fell past the bottom of the scroll container, and because the wheel
 * over the thread scrolls the thread rather than the page, the conversation simply
 * could not be reached. These assertions keep it filling the space it is given.
 */
class MessengerLayoutTest extends TestCase
{
    public function test_the_messenger_fills_its_page_instead_of_pinning_a_viewport_height(): void
    {
        $css = file_get_contents(public_path('css/messenger_interface.css'));

        $this->assertStringContainsString('flex: 1 1 auto;', $css);
        $this->assertStringContainsString('max-height: 100%;', $css);
        $this->assertStringNotContainsString('height: min(72vh', $css);
        $this->assertStringNotContainsString('min-height: 540px', $css);
        $this->assertStringNotContainsString('height: calc(100vh - 150px)', $css);
    }

    public function test_the_floor_height_leaves_room_for_the_page_header_on_short_viewports(): void
    {
        $css = file_get_contents(public_path('css/messenger_interface.css'));

        // The portal page header plus container padding costs roughly 210px, so a
        // taller floor than this reintroduces the clipping on zoomed viewports.
        preg_match_all('/min-height: (\d+)px/', $css, $matches);

        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $floor) {
            $this->assertLessThanOrEqual(320, (int) $floor);
        }
    }

    public function test_the_message_thread_can_actually_scroll(): void
    {
        $css = file_get_contents(public_path('css/messenger_interface.css'));

        // A grid row sized to auto, or a grid item left at min-height: auto, grows to
        // its content — so the thread's overflow-y: auto had no smaller box to scroll
        // within and long conversations were clipped by the container instead.
        $this->assertStringContainsString('grid-template-rows: minmax(0, 1fr);', $css);
        $this->assertStringContainsString('.messenger-chat { display: flex; min-width: 0; min-height: 0;', $css);
        $this->assertStringContainsString('.messenger-sidebar {', $css);
        $this->assertMatchesRegularExpression('/\.messenger-sidebar \{[^}]*min-height: 0;/s', $css);

        // The header and composer must not absorb the leftover height.
        $this->assertMatchesRegularExpression('/\.messenger-chat-head \{[^}]*flex: 0 0 auto;/', $css);
        $this->assertMatchesRegularExpression('/\.messenger-composer \{[^}]*flex: 0 0 auto;/', $css);
        $this->assertMatchesRegularExpression('/\.messenger-thread \{[^}]*flex: 1 1 0;[^}]*min-height: 0;[^}]*overflow-y: auto;/', $css);
    }

    public function test_the_instructor_chat_page_pins_its_min_height_wrapper(): void
    {
        // The instructor layout wrapper is min-height based so its dashboards can
        // grow; without a definite height the messenger sizes to its own messages
        // and the thread loses its internal scrollbar.
        $view = file_get_contents(resource_path('views/instructor/instructor/chat.blade.php'));

        $this->assertStringContainsString('.main > .page-content-fit { height: 100%; min-height: 0; }', $view);
    }

    public function test_an_opened_thread_with_no_history_explains_itself(): void
    {
        $component = file_get_contents(resource_path('views/components/portal/messenger.blade.php'));

        $this->assertStringContainsString('No messages in this conversation yet.', $component);
        $this->assertStringContainsString('data-messenger-thread-empty', $component);
        $this->assertStringContainsString("thread.querySelector('[data-messenger-thread-empty]')?.remove()", $component);
    }
}
