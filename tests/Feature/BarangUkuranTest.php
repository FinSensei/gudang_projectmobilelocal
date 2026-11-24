<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barang;

class BarangUkuranTest extends TestCase
{
    use RefreshDatabase;

    public function test_barang_menyimpan_ukuran(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => '21 cm',
            'kode_qr'     => 'S1',
            'stok_total'  => 10,
            'stok_baik'   => 10,
            'stok_rusak'  => 0,
        ]);

        $this->assertSame('21 cm', $b->fresh()->ukuran);
    }

    public function test_barang_tanpa_ukuran_tetap_bisa_disimpan(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Gelas',
            'satuan'      => 'pcs',
            'kode_qr'     => 'G1',
            'stok_total'  => 5,
            'stok_baik'   => 5,
            'stok_rusak'  => 0,
        ]);

        $this->assertNull($b->fresh()->ukuran);
    }

    public function test_scan_page_menampilkan_ukuran(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => '21 cm',
            'kode_qr'     => 'SCAN1',
            'stok_total'  => 10,
            'stok_baik'   => 10,
            'stok_rusak'  => 0,
        ]);

        $this->get('/b/SCAN1')
            ->assertOk()
            ->assertSee('21 cm');
    }

    public function test_form_tambah_barang_menyimpan_ukuran(): void
    {
        $this->post('/barang', [
            'nama'    => ['Piring'],
            'jumlah'  => [12],
            'ukuran'  => ['26 cm'],
        ])->assertRedirect();

        $this->assertDatabaseHas('barang', [
            'nama_barang' => 'Piring',
            'ukuran'      => '26 cm',
        ]);
    }
}