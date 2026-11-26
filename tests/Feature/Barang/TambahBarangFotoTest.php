<?php

namespace Tests\Feature\Barang;

use App\Models\Barang;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TambahBarangFotoTest extends TestCase
{
    public function test_tambah_barang_tanpa_foto_tetap_bisa(): void
    {
        Storage::fake('public');

        $this->post('/barang', [
            'nama' => ['Sendok'],
            'ukuran' => ['21 cm'],
            'jumlah' => [24],
        ])->assertRedirect();

        $b = Barang::where('nama_barang', 'Sendok')->first();
        $this->assertNotNull($b);
        $this->assertNull($b->foto, 'foto kosong kalau tidak diupload');
    }

    public function test_tambah_barang_dengan_foto_tersimpan(): void
    {
        Storage::fake('public');

        $this->post('/barang', [
            'nama' => ['Sendok'],
            'ukuran' => ['21 cm'],
            'jumlah' => [24],
            'foto' => [UploadedFile::fake()->image('sendok.jpg', 200, 200)],
        ])->assertRedirect();

        $b = Barang::where('nama_barang', 'Sendok')->first();
        $this->assertNotNull($b->foto, 'foto harus tersimpan');
        Storage::disk('public')->assertExists($b->foto);
    }

    public function test_beberapa_barang_sekaligus_dengan_foto_masing_masing(): void
    {
        Storage::fake('public');

        $this->post('/barang', [
            'nama' => ['Sendok', 'Garpu'],
            'ukuran' => ['21 cm', '19 cm'],
            'jumlah' => [10, 20],
            'foto' => [
                UploadedFile::fake()->image('sendok.jpg', 150, 150),
                UploadedFile::fake()->image('garpu.jpg', 150, 150),
            ],
        ])->assertRedirect();

        $sendok = Barang::where('nama_barang', 'Sendok')->first();
        $garpu = Barang::where('nama_barang', 'Garpu')->first();

        $this->assertNotNull($sendok->foto);
        $this->assertNotNull($garpu->foto);
        $this->assertNotSame($sendok->foto, $garpu->foto, 'foto tidak boleh tertukar');
    }

    public function test_baris_tanpa_foto_tidak_menggeser_foto_baris_lain(): void
    {
        Storage::fake('public');

        $this->post('/barang', [
            'nama' => ['Sendok', 'Garpu'],
            'ukuran' => ['21 cm', '19 cm'],
            'jumlah' => [10, 20],
            'foto' => [null, UploadedFile::fake()->image('garpu.jpg', 150, 150)],
        ])->assertRedirect();

        $sendok = Barang::where('nama_barang', 'Sendok')->first();
        $garpu = Barang::where('nama_barang', 'Garpu')->first();

        $this->assertNull($sendok->foto, 'baris pertama tidak punya foto');
        $this->assertNotNull($garpu->foto, 'foto harus ikut baris kedua');
    }

    public function test_halaman_stok_punya_input_foto(): void
    {
        $this->get('/restock')
            ->assertOk()
            ->assertSee('name="foto[]"', false)
            ->assertSee('type="file"', false);
    }
}
