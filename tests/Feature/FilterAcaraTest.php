<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Acara;

class FilterAcaraTest extends TestCase
{
    private function a(string $nama, string $tanggal): Acara
    {
        return Acara::create([
            'nama_acara'=>$nama,'tanggal'=>$tanggal,'status'=>'aktif',
            'penanggung_jawab'=>'Petugas',
        ]);
    }

    public function test_filter_tahun_menyaring_acara(): void
    {
        $this->a('Acara 2025', '2025-06-10');
        $this->a('Acara 2024', '2024-06-10');

        $this->get('/proses?tahun=2025')
            ->assertOk()
            ->assertSee('Acara 2025')
            ->assertDontSee('Acara 2024');
    }

    public function test_filter_tanggal_menyaring_acara(): void
    {
        $this->a('Acara A', '2025-06-10');
        $this->a('Acara B', '2025-06-11');

        $this->get('/proses?tanggal=2025-06-10')
            ->assertOk()
            ->assertSee('Acara A')
            ->assertDontSee('Acara B');
    }

    public function test_tanpa_filter_menampilkan_semua(): void
    {
        $this->a('Acara 2025', '2025-06-10');
        $this->a('Acara 2024', '2024-06-10');

        $this->get('/proses')
            ->assertOk()
            ->assertSee('Acara 2025')
            ->assertSee('Acara 2024');
    }

    public function test_halaman_proses_punya_filter_tanggal_dan_tahun(): void
    {
        $this->get('/proses')
            ->assertOk()
            ->assertSee('name="tanggal"', false)
            ->assertSee('name="tahun"', false)
            ->assertSee('Reset', false);
    }
}