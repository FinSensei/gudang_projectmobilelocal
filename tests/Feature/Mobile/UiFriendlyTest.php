<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kunci desain "ramah untuk staf gudang non-teknis".
 * Kalau tes ini gagal, tampilan kembali ke gaya ramai/AI.
 */
class UiFriendlyTest extends TestCase
{
    private function admin(): \App\Models\User
    {
        return \App\Models\User::create([
            'name' => 'Admin Uji', 'email' => 'adminuji@gudang.id',
            'password' => 'rahasia123', 'role' => 'admin',
        ]);
    }

    private function css(): string
    {
        $this->actingAs($this->admin());
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('/<style>(.*?)<\/style>/s', $html, $m);
        $this->assertNotEmpty($m[1] ?? '', 'halaman harus punya <style>');
        return $m[1];
    }

    public function test_tidak_ada_angka_terlalu_tebal(): void
    {
        $css = $this->css();
        $this->assertStringNotContainsString('font-weight:800', $css, 'angka 800 terlalu tebal');
        $this->assertStringNotContainsString('font-weight:700', $css, 'pakai 400/500/600 saja');
    }

    public function test_tidak_ada_warna_ungu(): void
    {
        $this->assertStringNotContainsString('--purple', $this->css());
    }

    public function test_teks_panduan_minimal_15px(): void
    {
        $css = $this->css();
        preg_match('/\.hint\{[^}]*font-size:(\d+)px/', $css, $m);
        $this->assertNotEmpty($m, '.hint harus ada');
        $this->assertGreaterThanOrEqual(15, (int) $m[1], 'panduan jangan 14px');
    }

    public function test_kartu_tidak_masuk_satu_per_satu(): void
    {
        $css = $this->css();
        $this->assertStringNotContainsString('.card{animation', $css, 'animasi tiap kartu bikin ramai');
    }

    public function test_nav_admin_ada_lainnya(): void
    {
        $this->actingAs($this->admin());
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Lainnya', $html, 'menu admin harus dikelompokkan');
        $this->assertLessThanOrEqual(6, substr_count($html, '<nav'), 'nav jangan lebih dari 6 blok');
    }

    public function test_angka_stok_satu_ukuran(): void
    {
        $css = $this->css();
        // semua angka besar memakai satu kelas .angka
        $this->assertStringContainsString('.angka{', $css, 'ada kelas angka seragam');
        $this->assertStringNotContainsString('.num{font-size:30px', $css, 'angka 30px terpisah');
    }
}