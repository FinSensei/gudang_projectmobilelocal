<?php

namespace Tests\Feature\Simulasi;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\DetailPengembalian;
use App\Models\Pengembalian;
use App\Services\CheckoutService;
use App\Services\PengembalianService;
use Tests\TestCase;

/**
 * Reproduksi: undo pengembalian lalu catat kembali lagi.
 * Ringkasan tidak boleh berlipat / kembali > keluar.
 */
class SimulasiUndoUlangTest extends TestCase
{
    private function b(): Barang
    {
        return Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S1', 'stok_total' => 100, 'stok_baik' => 100, 'stok_rusak' => 0]);
    }

    public function test_undo_lalu_return_lagi_ringkasan_tidak_berlipat(): void
    {
        $b = $this->b();

        $a = Acara::create(['nama_acara' => 'Konser', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        app(CheckoutService::class)->buat($a, [['barang_id' => $b->id, 'jumlah' => 40]]);

        // return pertama: 35 kembali, 5 hilang
        $p1 = Pengembalian::create(['tanggal_pulang' => '2026-01-02', 'dicatat_oleh' => 'P']);
        $p1->acara()->attach($a->id);
        $p1->detailPengembalian()->create(['barang_id' => $b->id, 'jumlah_kembali' => 35]);
        app(PengembalianService::class)->hitung($p1);

        $this->assertSame(35, (int) DetailPengembalian::where('pengembalian_id', $p1->id)->sum('jumlah_kembali'));

        // UNDO
        $this->post("/pengembalian/{$p1->id}/undo")->assertRedirect();

        // setelah undo, ringkasan "unit kembali" harus 0 (pengembalian dibatalkan)
        $kembaliTotal = DetailPengembalian::whereHas('pengembalian', fn ($q) => $q->whereNull('deleted_at'))
            ->sum('jumlah_kembali');
        $this->assertSame(0, (int) $kembaliTotal, 'unit kembali setelah undo harus 0');

        // return kedua
        $p2 = Pengembalian::create(['tanggal_pulang' => '2026-01-03', 'dicatat_oleh' => 'P']);
        $p2->acara()->attach($a->id);
        $p2->detailPengembalian()->create(['barang_id' => $b->id, 'jumlah_kembali' => 40]);
        app(PengembalianService::class)->hitung($p2);

        $kembaliTotal2 = DetailPengembalian::whereHas('pengembalian', fn ($q) => $q->whereNull('deleted_at'))
            ->sum('jumlah_kembali');
        $this->assertSame(40, (int) $kembaliTotal2, 'unit kembali setelah return ke-2 = 40, bukan 75');
    }

    public function test_keluar_tidak_menghitung_acara_batal(): void
    {
        $b = $this->b();
        $a = Acara::create(['nama_acara' => 'Batal', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        app(CheckoutService::class)->buat($a, [['barang_id' => $b->id, 'jumlah' => 30]]);

        // batalkan
        $this->post("/acara/{$a->id}/batalkan")->assertRedirect();

        // "unit keluar" tidak boleh menghitung acara yang dibatalkan
        $keluar = DetailAcara::whereHas('acara', fn ($q) => $q->where('status', '!=', 'batal'))
            ->sum('jumlah_keluar');
        $this->assertSame(0, (int) $keluar, 'acara batal tidak dihitung sebagai keluar');
    }
}
