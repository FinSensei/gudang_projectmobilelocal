<?php

namespace Tests\Feature\Pengembalian;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\DetailPengembalian;
use App\Models\Pengembalian;
use Tests\TestCase;

class RiwayatBarangUndoTest extends TestCase
{
    /** Bikin barang + acara + pengembalian, lalu undo (soft delete pengembalian). */
    private function seedUndo(): Barang
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'U1',
            'stok_total' => 100, 'stok_baik' => 90, 'stok_rusak' => 0,
        ]);

        $a = Acara::create([
            'nama_acara' => 'Acara Uji', 'tanggal' => '2025-06-10', 'status' => 'selesai',
            'penanggung_jawab' => 'Petugas',
        ]);
        DetailAcara::create([
            'acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10,
        ]);

        $p = Pengembalian::create([
            'tanggal_pulang' => '2025-06-11', 'dicatat_oleh' => 'Petugas', 'catatan' => null,
        ]);
        $p->acara()->attach($a->id);
        DetailPengembalian::create([
            'pengembalian_id' => $p->id, 'barang_id' => $b->id,
            'jumlah_keluar' => 10, 'jumlah_kembali' => 9, 'jumlah_hilang' => 1,
        ]);

        // Undo -> soft delete pengembalian, tapi DetailPengembalian tetap ada
        $p->delete();

        return $b;
    }

    public function test_halaman_riwayat_barang_tidak_error(): void
    {
        $b = $this->seedUndo();

        $this->get("/barang/{$b->id}/riwayat")->assertOk();
    }

    public function test_riwayat_barang_tetap_menampilkan_transaksi_keluar(): void
    {
        $b = $this->seedUndo();

        $this->get("/barang/{$b->id}/riwayat")
            ->assertOk()
            ->assertSee('Acara Uji');
    }

    public function test_beranda_tidak_error(): void
    {
        $this->seedUndo();

        $this->get('/')->assertOk();
    }

    public function test_halaman_kembali_tidak_error(): void
    {
        $this->seedUndo();

        $this->get('/riwayat')->assertOk();
    }
}
