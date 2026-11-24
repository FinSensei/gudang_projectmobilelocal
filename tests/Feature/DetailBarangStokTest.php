<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;

class DetailBarangStokTest extends TestCase
{
    private function b(): Barang
    {
        return Barang::create([
            'nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'DB1',
            'stok_total'=>100,'stok_baik'=>90,
            'stok_rusak_ringan'=>6,'stok_rusak_berat'=>4,
        ]);
    }

    public function test_halaman_edit_menampilkan_rusak_ringan_dan_berat(): void
    {
        $b = $this->b();

        $this->get("/barang/{$b->id}/edit")
            ->assertOk()
            ->assertSee('Rusak Ringan', false)
            ->assertSee('Rusak Berat', false)
            ->assertSee('Stok Baik', false);
    }

    public function test_halaman_edit_menampilkan_angka_yang_benar(): void
    {
        $b = $this->b();

        $html = $this->get("/barang/{$b->id}/edit")->getContent();

        // angka 6 dan 4 harus muncul di kartu stok
        $this->assertStringContainsString('>6<', $html);
        $this->assertStringContainsString('>4<', $html);
    }

    public function test_halaman_scan_menampilkan_rincian_rusak(): void
    {
        $b = $this->b();

        $this->get('/b/DB1')
            ->assertOk()
            ->assertSee('Rusak Ringan', false)
            ->assertSee('Rusak Berat', false);
    }
}