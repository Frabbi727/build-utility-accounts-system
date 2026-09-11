<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class IconComponentTest extends TestCase
{
    public function test_icon_renders_svg_with_default_attributes_and_class(): void
    {
        $view = $this->blade('<x-ui.icon name="dashboard" />');

        $view->assertSee('<svg', false);
        $view->assertSee('viewBox="0 0 24 24"', false);
        $view->assertSee('fill="none"', false);
        $view->assertSee('stroke="currentColor"', false);
        $view->assertSee('stroke-width="2"', false);
        $view->assertSee('stroke-linecap="round"', false);
        $view->assertSee('stroke-linejoin="round"', false);
        $view->assertSee('aria-hidden="true"', false);
        $view->assertSee('w-5 h-5', false);
    }

    public function test_icon_accepts_custom_class(): void
    {
        $view = $this->blade('<x-ui.icon name="dashboard" class="w-6 h-6 text-slate-500" />');

        $view->assertSee('class="w-6 h-6 text-slate-500"', false);
        $view->assertSee('aria-hidden="true"', false);
    }

    public function test_icon_renders_fallback_when_name_is_omitted_or_unknown(): void
    {
        $folderPath = 'M20 20a2 2 0 0 0 2-2V8';

        // Omitted name
        $viewOmitted = $this->blade('<x-ui.icon />');
        $viewOmitted->assertSee('<svg', false);
        $viewOmitted->assertSee($folderPath, false);

        // Unknown name
        $viewUnknown = $this->blade('<x-ui.icon name="unknown" />');
        $viewUnknown->assertSee('<svg', false);
        $viewUnknown->assertSee($folderPath, false);
    }

    public function test_all_supported_icons_and_aliases_render_correctly(): void
    {
        $icons = [
            'dashboard',
            'home',
            'billing',
            'receipt',
            'expenses',
            'cash',
            'masters',
            'office-building',
            'utilities',
            'bolt',
            'reports',
            'chart',
            'settings',
            'cog',
            'chevron-down',
            'chevron-right',
            'chevron-left',
            'search',
            'menu',
            'x',
            'close',
            'building',
            'user',
            'logout',
            'spinner',
            'loading',
        ];

        foreach ($icons as $icon) {
            $view = $this->blade('<x-ui.icon :name="$icon" />', ['icon' => $icon]);
            $view->assertSee('<svg', false);
            $view->assertSee('</svg>', false);
        }
    }
}
