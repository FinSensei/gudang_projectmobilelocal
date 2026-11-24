<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;

class BarangUkuranEditTest extends TestCase
{
    private function b(array $attr = []): Barang
    {
        return Barang::create(array_merge([
            'nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1',
            'stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0,
        ], $attr));
    }

    // ---------- 1. Halaman edit punya input ukuran ----------
    public function test_halaman_edit_punya_input_ukuran(): void
    {
        $b = $this->b(['ukuran'=>'21 cm']);

        $this->get("/barang/{$b->id}/edit")
            ->assertOk()
            ->assertSee('name="ukuran"', false)
            ->assertSee('value="21 cm"', false);
    }

    // ---------- 2. Bisa ubah ukuran lewat edit ----------
    public function test_edit_bisa_mengubah_ukuran(): void
    {
        $b = $this->b(['ukuran'=>'21 cm']);

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => '25 cm',
            'lokasi'      => '',
            'kategori_id' => '',
        ])->assertRedirect();

        $this->assertSame('25 cm', $b->fresh()->ukuran);
    }

    // ---------- 3. Default "N/A" saat barang dibuat tanpa ukuran ----------
    public function test_barang_baru_tanpa_ukuran_dapat_default_na(): void
    {
        $this->post('/barang', [
            'nama'    => ['Gelas'],
            'jumlah'  => [12],
        ])->assertRedirect();

        $b = Barang::where('nama_barang', 'Gelas')->first();
        $this->assertSame('N/A', $b->ukuran, 'default ukuran harus N/A');
    }

    // ---------- 4. Ukuran kosong di form juga jadi "N/A" ----------
    public function test_ukuran_kosong_dijadikan_na(): void
    {
        $this->post('/barang', [
            'nama'    => ['Gelas'],
            'jumlah'  => [12],
            'ukuran'  => [''],
        ])->assertRedirect();

        $this->assertSame('N/A', Barang::where('nama_barang','Gelas')->first()->ukuran);
    }

    // ---------- 5. Edit dengan ukuran dikosongkan -> "N/A" ----------
    public function test_edit_ukuran_dikosongkan_jadi_na(): void
    {
        $b = $this->b(['ukuran'=>'21 cm']);

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => '',
            'lokasi'      => '',
            'kategori_id' => '',
        ])->assertRedirect();

        $this->assertSame('N/A', $b->fresh()->ukuran);
    }

    // ---------- 6. Barang dibuat tanpa key ukuran -> default "N/A" ----------
    public function test_barang_tanpa_key_ukuran_dapat_na(): void
    {
        $b = $this->b();   // tanpa 'ukuran'

        $this->assertSame('N/A', $b->fresh()->ukuran);
        $this->get('/b/S1')->assertOk()->assertSee('N/A');
    }
}