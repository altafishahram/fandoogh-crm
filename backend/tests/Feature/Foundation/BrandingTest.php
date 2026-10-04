<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Tests\TestCase;

final class BrandingTest extends TestCase
{
    public function test_all_panel_names_use_the_current_application_brand(): void
    {
        self::assertSame('دفتر املاکی', config('app.name'));

        foreach (['control', 'agency', 'agent'] as $panelId) {
            $brand = Filament::getPanel($panelId)->getBrandName();
            $name = $brand instanceof Htmlable ? $brand->toHtml() : $brand;

            self::assertStringContainsString('دفتر املاکی', $name);
            self::assertStringNotContainsString('ملک بان', $name);
            self::assertSame('16rem', Filament::getPanel($panelId)->getSidebarWidth());
        }
    }

    public function test_each_panel_login_displays_the_new_brand_and_existing_studio_credit(): void
    {
        foreach (['control', 'agency', 'agent'] as $panelId) {
            $this->get('/'.$panelId.'/login')
                ->assertOk()
                ->assertSee('دفتر املاکی')
                ->assertSee('طراحی‌شده توسط فندوق استودیو')
                ->assertDontSee('ملک بان');
        }
    }
}
