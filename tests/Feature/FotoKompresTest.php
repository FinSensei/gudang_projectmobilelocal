<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FotoKompresTest extends TestCase
{
    private function b(): Barang
    {
        return Barang::create([
            'nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'K1',
            'stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0,
        ]);
    }

    public function test_foto_besar_dikecilkan_saat_upload(): void
    {
        Storage::fake('public');
        $b = $this->b();

        // gambar 2000x2000 (kitchen sink) -> harus diperkecil
        $file = UploadedFile::fake()->image('besar.jpg', 2000, 2000);

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => 'N/A',
            'foto'        => $file,
        ])->assertRedirect();

        $b->refresh();
        $path = Storage::disk('public')->path($b->foto);
        $info = getimagesize($path);

        // dimensi maksimal 800px
        $this->assertLessThanOrEqual(800, $info[0], 'lebar maks 800px');
        $this->assertLessThanOrEqual(800, $info[1], 'tinggi maks 800px');
    }

    public function test_file_kecil_tidak_diperbesar(): void
    {
        Storage::fake('public');
        $b = $this->b();

        $file = UploadedFile::fake()->image('kecil.jpg', 300, 300);

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => 'N/A',
            'foto'        => $file,
        ])->assertRedirect();

        $b->refresh();
        $info = getimagesize(Storage::disk('public')->path($b->foto));

        $this->assertSame(300, $info[0], 'gambar kecil tidak diubah');
        $this->assertSame(300, $info[1]);
    }

    public function test_foto_tetap_bisa_dibuka_setelah_dikompres(): void
    {
        Storage::fake('public');
        $b = $this->b();

        $file = UploadedFile::fake()->image('besar.jpg', 1600, 1200);

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok',
            'satuan'      => 'pcs',
            'ukuran'      => 'N/A',
            'foto'        => $file,
        ])->assertRedirect();

        $b->refresh();
        $path = Storage::disk('public')->path($b->foto);
        $this->assertFileExists($path);

        $before = filesize($path);
        $this->assertGreaterThan(0, $before, 'file tidak boleh kosong/rusak');
    }
}