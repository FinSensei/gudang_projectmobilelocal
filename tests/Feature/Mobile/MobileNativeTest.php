<?php

namespace Tests\Feature\Mobile;

use Tests\TestCase;

class MobileNativeTest extends TestCase
{
    /** Halaman utama (pakai layout) — sudah login otomatis dari TestCase. */
    private function app(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    /** Halaman login (standalone, tidak pakai layout). */
    private function login(): string
    {
        auth()->logout();

        return $this->get('/login')->assertOk()->getContent();
    }

    private function assertMobileBaseline(string $html): void
    {
        $this->assertStringNotContainsString('maximum-scale=1', $html);
        $this->assertStringNotContainsString('user-scalable=no', $html);
        $this->assertStringContainsString('viewport-fit=cover', $html);
        $this->assertStringContainsString('name="theme-color"', $html);
        $this->assertStringContainsString('prefers-color-scheme: light', $html);
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
        $this->assertStringContainsString('-webkit-tap-highlight-color: transparent', $html);
        $this->assertStringContainsString('touch-action: manipulation', $html);
        $this->assertStringContainsString('-webkit-touch-callout', $html);
        $this->assertStringContainsString('overscroll-behavior', $html);
    }

    public function test_halaman_utama_punya_baseline_mobile(): void
    {
        $this->assertMobileBaseline($this->app());
    }

    public function test_halaman_login_punya_baseline_mobile(): void
    {
        $this->assertMobileBaseline($this->login());
    }

    public function test_hover_diganti_media_query_capability(): void
    {
        $this->assertStringContainsString('@media (hover: hover) and (pointer: fine)', $this->app());
    }

    public function test_halaman_login_tidak_pakai_100vh_saja(): void
    {
        $html = $this->login();
        $this->assertStringContainsString('100svh', $html);
    }

    public function test_input_login_punya_inputmode_dan_enterkeyhint(): void
    {
        $html = $this->login();
        $this->assertStringContainsString('inputmode="email"', $html);
        $this->assertStringContainsString('enterkeyhint=', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
    }

    public function test_halaman_login_tidak_menonaktifkan_zoom(): void
    {
        $html = $this->login();
        $this->assertStringNotContainsString('maximum-scale=1', $html);
        $this->assertStringNotContainsString('user-scalable=no', $html);
    }
}
