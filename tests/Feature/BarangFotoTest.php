<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BarangFotoTest extends TestCase
{
    private function b(array $attr = []): Barang
    {
        return Barang::create(array_merge([
            'nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'F1',
            'stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0,
        ], $attr));
    }

    public function test_halaman_edit_punya_input_foto(): void
    {
        $b = $this->b();

        $this->get("/barang/{$b->id}/edit")
            ->assertOk()
            ->assertSee('name="foto"', false)
            ->assertSee('type="file"', false);
    }

    public function test_upload_foto_tersimpan_dan_path_masuk_db(): void
    {
        Storage::fake('public');
        $b = $this->b();

        $file = UploadedFile::fake()->image('sendok.jpg', 200, 200);

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => 'N/A',
            'lokasi'      => '',
            'kategori_id' => '',
            'foto'        => $file,
        ])->assertRedirect();

        $b->refresh();
        $this->assertNotNull($b->foto, 'path foto harus tersimpan');
        Storage::disk('public')->assertExists($b->foto);
    }

    public function test_foto_lama_tetap_kalau_tidak_upload_baru(): void
    {
        Storage::fake('public');
        $b = $this->b(['foto' => 'barang/lama.jpg']);
        Storage::disk('public')->put('barang/lama.jpg', 'x');

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => 'N/A',
        ])->assertRedirect();

        $this->assertSame('barang/lama.jpg', $b->fresh()->foto, 'foto lama tidak boleh hilang');
    }

    public function test_foto_lama_diganti_dan_file_lama_terhapus(): void
    {
        Storage::fake('public');
        $b = $this->b(['foto' => 'barang/lama.jpg']);
        Storage::disk('public')->put('barang/lama.jpg', 'x');

        $file = UploadedFile::fake()->image('baru.jpg', 100, 100);

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => 'N/A',
            'foto'        => $file,
        ])->assertRedirect();

        $b->refresh();
        $this->assertNotSame('barang/lama.jpg', $b->foto);
        Storage::disk('public')->assertMissing('barang/lama.jpg');
        Storage::disk('public')->assertExists($b->foto);
    }

    public function test_scan_page_menampilkan_gambar(): void
    {
        Storage::fake('public');
        $b = $this->b(['foto' => 'barang/sendok.jpg']);
        Storage::disk('public')->put('barang/sendok.jpg', 'x');

        $this->get('/b/F1')
            ->assertOk()
            ->assertSee('storage/barang/sendok.jpg', false);
    }

    public function test_file_bukan_gambar_ditolak(): void
    {
        Storage::fake('public');
        $b = $this->b();

        $file = UploadedFile::fake()->create('bukan-gambar.pdf', 10, 'application/pdf');

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => 'N/A',
            'foto'        => $file,
        ])->assertSessionHasErrors('foto');

        $this->assertNull($b->fresh()->foto, 'foto tidak boleh tersimpan');
    }

    public function test_upload_via_galeri_tersimpan(): void
    {
        Storage::fake('public');
        $b = $this->b();

        $file = UploadedFile::fake()->image('galeri.jpg', 200, 200);

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => 'N/A',
            'foto_galeri' => $file,
        ])->assertRedirect();

        $b->refresh();
        $this->assertNotNull($b->foto, 'foto dari galeri harus tersimpan');
        Storage::disk('public')->assertExists($b->foto);
    }

    public function test_halaman_edit_punya_dua_input_foto(): void
    {
        $b = $this->b();

        $this->get("/barang/{$b->id}/edit")
            ->assertOk()
            ->assertSee('id="foto-kamera"', false)
            ->assertSee('id="foto-galeri"', false);
    }
}