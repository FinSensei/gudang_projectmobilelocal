<?php

namespace Tests\Feature\Simulasi;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\Pengembalian;
use App\Services\CheckoutService;
use App\Services\PengembalianService;
use Tests\TestCase;

/**
 * Simulasi skenario nyata + cek invariant stok.
 * Invariant: stok_total = stok_baik + stok_rusak_ringan + stok_rusak_berat + stok_keluar
 */
class SimulasiInvarianTest extends TestCase
{
    private function invariant(Barang $b): int
    {
        $b->refresh();
        $keluar = $b->stokKeluar();

        return $b->stok_total - ($b->stok_baik + $b->stok_rusak_ringan + $b->stok_rusak_berat + $keluar);
    }

    private function b(string $nama, int $total, ?int $baik = null, int $rusak = 0): Barang
    {
        return Barang::create([
            'nama_barang' => $nama, 'satuan' => 'pcs', 'kode_qr' => 'Q-'.$nama,
            'stok_total' => $total, 'stok_baik' => $baik ?? $total, 'stok_rusak_ringan' => $rusak,
        ]);
    }

    // ---------- 1. Alur penuh: checkout -> kembali -> cek invariant ----------
    public function test_alur_checkout_kembali_invariant_terjaga(): void
    {
        $b = $this->b('Sendok', 100);

        $a = Acara::create(['nama_acara' => 'Konser', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        app(CheckoutService::class)->buat($a, [['barang_id' => $b->id, 'jumlah' => 40]]);

        $b->refresh();
        $this->assertSame(60, $b->stok_baik, 'baik berkurang 40');
        $this->assertSame(0, $this->invariant($b), 'invariant setelah checkout');

        $p = Pengembalian::create(['tanggal_pulang' => '2026-01-02', 'dicatat_oleh' => 'Petugas']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id' => $b->id, 'jumlah_kembali' => 35]);
        app(PengembalianService::class)->hitung($p);

        $b->refresh();
        $this->assertSame(95, $b->stok_baik, 'baik 60+35');
        $this->assertSame(95, $b->stok_total, 'total 100-5 hilang');
        $this->assertSame(0, $this->invariant($b), 'invariant setelah kembali');
    }

    // ---------- 2. Undo mengembalikan stok tepat ----------
    public function test_undo_mengembalikan_stok_tepat(): void
    {
        $b = $this->b('Sendok', 100);
        $a = Acara::create(['nama_acara' => 'Konser', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        $a->detailAcara()->create(['barang_id' => $b->id, 'jumlah_keluar' => 40]);
        $b->update(['stok_baik' => 60]);

        $p = Pengembalian::create(['tanggal_pulang' => '2026-01-02', 'dicatat_oleh' => 'P']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id' => $b->id, 'jumlah_kembali' => 35, 'jumlah_hilang' => 5]);
        $b->update(['stok_baik' => 95, 'stok_total' => 95]);

        $this->post("/pengembalian/{$p->id}/undo")->assertRedirect();

        $b->refresh();
        $this->assertSame(60, $b->stok_baik, 'baik balik ke 60');
        $this->assertSame(100, $b->stok_total, 'total balik ke 100');
        $this->assertSame('aktif', $a->fresh()->status);
        $this->assertSame(0, $this->invariant($b), 'invariant setelah undo');
    }

    // ---------- 3. Batalkan acara ----------
    public function test_batalkan_acara_invariant_terjaga(): void
    {
        $b = $this->b('Sendok', 100);
        $a = Acara::create(['nama_acara' => 'Konser', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        $a->detailAcara()->create(['barang_id' => $b->id, 'jumlah_keluar' => 40]);
        $b->update(['stok_baik' => 60]);

        $this->post("/acara/{$a->id}/batalkan")->assertRedirect();

        $b->refresh();
        $this->assertSame(100, $b->stok_baik, 'baik balik 100');
        $this->assertSame(0, $this->invariant($b), 'invariant setelah batalkan');
    }

    // ---------- 4. KURANGI saat barang sebagian di event ----------
    public function test_kurangi_saat_sebagian_di_event_invariant_terjaga(): void
    {
        $b = $this->b('Sendok', 100);          // total 100
        $a = Acara::create(['nama_acara' => 'Konser', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        $a->detailAcara()->create(['barang_id' => $b->id, 'jumlah_keluar' => 40]);
        $b->update(['stok_baik' => 60]);         // 40 di event

        // 40 sedang di event -> sisa bebas 20. Kurangi 20 (maksimal).
        $this->post("/barang/{$b->id}/kurangi", ['jumlah' => 20])->assertRedirect();

        $b->refresh();
        $this->assertSame(40, $b->stok_baik);
        $this->assertSame(80, $b->stok_total);
        $this->assertSame(0, $this->invariant($b), 'invariant setelah kurangi');
    }

    // ---------- 5. Kurangi melebihi baik DITOLAK (tidak minus) ----------
    public function test_kurangi_melebihi_baik_tidak_bikin_minus(): void
    {
        $b = $this->b('Sendok', 100);
        $a = Acara::create(['nama_acara' => 'Konser', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        $a->detailAcara()->create(['barang_id' => $b->id, 'jumlah_keluar' => 40]);
        $b->update(['stok_baik' => 60]);

        $this->post("/barang/{$b->id}/kurangi", ['jumlah' => 90])->assertRedirect();

        $b->refresh();
        $this->assertSame(60, $b->stok_baik, 'baik tidak berubah');
        $this->assertSame(100, $b->stok_total, 'total tidak berubah');
        $this->assertSame(0, $this->invariant($b));
    }

    // ---------- 6. Barang rusak tidak ikut dihitung kembali ----------
    public function test_barang_rusak_tidak_dihitung_sebagai_baik(): void
    {
        $b = $this->b('Sendok', 100, 90, 10);  // baik 90, rusak 10
        $this->assertSame(0, $this->invariant($b), 'invariant awal');

        $a = Acara::create(['nama_acara' => 'Konser', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        $a->detailAcara()->create(['barang_id' => $b->id, 'jumlah_keluar' => 20]);
        $b->update(['stok_baik' => 70]);

        $p = Pengembalian::create(['tanggal_pulang' => '2026-01-02', 'dicatat_oleh' => 'P']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id' => $b->id, 'jumlah_kembali' => 18]);
        app(PengembalianService::class)->hitung($p);

        $b->refresh();
        $this->assertSame(10, $b->stok_rusak, 'rusak tetap 10 (ringan)');
        $this->assertSame(88, $b->stok_baik, 'baik 70+18');
        $this->assertSame(0, $this->invariant($b), 'invariant akhir');
    }
}
