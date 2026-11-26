<?php

namespace Tests\Feature\Pengembalian;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Models\DetailPengembalian;
use App\Services\PengembalianService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HitungPerformaTest extends TestCase
{
    private function b(int $baik = 100): Barang
    {
        return Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'HP'.uniqid(),
            'stok_total' => $baik, 'stok_baik' => $baik,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
    }

    private function acara(Barang $b, int $jumlah): Acara
    {
        $a = Acara::create([
            'nama_acara' => 'Acara', 'tanggal' => '2026-06-10', 'status' => 'aktif',
            'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => $jumlah]);
        return $a;
    }

    public function test_hitung_tidak_n_plus_one(): void
    {
        $b = $this->b(100);
        $a = $this->acara($b, 50);

        $p = Pengembalian::create(['tanggal_pulang' => '2026-06-11', 'dicatat_oleh' => 'P']);
        $p->acara()->attach($a->id);
        DetailPengembalian::create([
            'pengembalian_id' => $p->id, 'barang_id' => $b->id,
            'jumlah_kembali' => 30, 'jumlah_rusak_ringan' => 10,
            'jumlah_rusak_berat' => 5, 'jumlah_hilang' => 5,
        ]);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        app(PengembalianService::class)->hitung($p->fresh());

        $this->assertLessThan(10, $queries, "hitung hanya boleh {$queries} query");
    }

    public function test_hitung_kelebihan_kembali_dicatat_ke_log(): void
    {
        $b = $this->b(100);
        $a = $this->acara($b, 10);

        $p = Pengembalian::create(['tanggal_pulang' => '2026-06-11', 'dicatat_oleh' => 'P']);
        $p->acara()->attach($a->id);
        DetailPengembalian::create([
            'pengembalian_id' => $p->id, 'barang_id' => $b->id,
            'jumlah_kembali' => 50, 'jumlah_rusak_ringan' => 0,
            'jumlah_rusak_berat' => 0, 'jumlah_hilang' => 0,
        ]);

        app(PengembalianService::class)->hitung($p->fresh());

        $detail = $p->detailPengembalian()->first();
        $this->assertSame(10, (int) $detail->jumlah_kembali);
        $this->assertSame(0, (int) $detail->jumlah_hilang);

        $log = \App\Models\LogAktivitas::where('aksi', 'pengembalian_clamp')
            ->where('entitas', 'detail_pengembalian')
            ->first();
        $this->assertNotNull($log, 'log aktivitas harus mencatat clamping');
    }
}
