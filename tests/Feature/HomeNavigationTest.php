<?php

namespace Tests\Feature;

use App\Models\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $slug, array $overrides = []): NavigationItem
    {
        return NavigationItem::create($overrides + [
            'navigation_item_slug' => $slug,
            'navigation_item_name_ar' => 'عربي '.$slug,
            'navigation_item_name_en' => 'English '.$slug,
            'navigation_item_icon' => 'store',
            'navigation_item_url' => '/gazamarket/'.$slug,
            'navigation_item_sort_order' => 1,
        ]);
    }

    private function menus(): array
    {
        $html = $this->get('/gazamarket/home')->assertOk()->getContent();

        preg_match('/<nav class="sidebar-nav".*?<\/nav>/s', $html, $sidebar);
        preg_match('/<nav class="bottom-nav".*?<\/nav>/s', $html, $bottom);

        return [$sidebar[0] ?? '', $bottom[0] ?? ''];
    }

    public function test_both_menus_show_the_active_items_in_order(): void
    {
        $this->item('market', ['navigation_item_sort_order' => 2]);
        $this->item('home', ['navigation_item_sort_order' => 1]);
        $this->item('off', ['navigation_item_is_active' => false]);

        foreach ($this->menus() as $menu) {
            $this->assertStringNotContainsString('عربي off', $menu);
            $this->assertLessThan(strpos($menu, 'عربي market'), strpos($menu, 'عربي home'));
            $this->assertStringContainsString('data-name-en="English market"', $menu);
            $this->assertStringContainsString('href="/gazamarket/market"', $menu);
        }
    }

    public function test_desktop_and_mobile_flags_control_each_menu(): void
    {
        $this->item('desk', ['navigation_item_is_mobile' => false]);
        $this->item('phone', ['navigation_item_is_desktop' => false]);

        [$sidebar, $bottom] = $this->menus();

        $this->assertStringContainsString('عربي desk', $sidebar);
        $this->assertStringNotContainsString('عربي phone', $sidebar);
        $this->assertStringContainsString('عربي phone', $bottom);
        $this->assertStringNotContainsString('عربي desk', $bottom);
    }

    public function test_coming_soon_badge_and_empty_url(): void
    {
        $this->item('delivery', ['navigation_item_is_coming_soon' => true, 'navigation_item_url' => null]);

        foreach ($this->menus() as $menu) {
            $this->assertStringContainsString('data-i18n="sidebar.soon"', $menu);
            $this->assertStringContainsString('href="#"', $menu);
        }
    }

    public function test_an_edit_shows_up_without_waiting_for_the_cache(): void
    {
        $item = $this->item('market');
        $this->menus();

        $item->update(['navigation_item_name_ar' => 'اسم جديد']);

        foreach ($this->menus() as $menu) {
            $this->assertStringContainsString('اسم جديد', $menu);
        }
    }

    public function test_empty_table_hides_the_sidebar_menu_and_keeps_the_add_button(): void
    {
        [$sidebar, $bottom] = $this->menus();

        $this->assertSame('', $sidebar);
        $this->assertStringContainsString('bottom-nav__add', $bottom);
    }

    // بيحتاج: composer require mallardduck/blade-lucide-icons
    public function test_icons_are_rendered_and_an_unknown_name_does_not_break_the_page(): void
    {
        $this->item('market');
        $this->item('bad', ['navigation_item_icon' => 'no-such-icon']);

        [$sidebar] = $this->menus();

        $this->assertSame(2, substr_count($sidebar, '<svg'));
    }
}