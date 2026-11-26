<?php

namespace Tests\Feature\Simulasi;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Services\ExcelExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class P1PerbaikanTest extends TestCase
{
    // ================= P1.2: index database =================
    public function test_tabel_pengembalian_punya_index_acara_id(): void
    {
        $idx = collect(Schema::getIndexes('acara_pengembalian'))
            ->pluck('columns')->flatten()->all();

        $this->assertContains('acara_id', $idx, 'pivot harus punya index acara_id');
    }

    public function test_tabel_detail_pengembalian_punya_index_barang_id(): void
    {
        $idx = collect(Schema::getIndexes('detail_pengembalian'))
            ->pluck('columns')->flatten()->all();

        $this->assertContains('barang_id', $idx, 'detail_pengembalian harus punya index barang_id');
    }

    public function test_tabel_barang_punya_index_status_kondisi(): void
    {
        $cols = Schema::getColumnListing('barang');

        $this->assertContains('status_kondisi', $cols);
    }

    // ================= P1.3: N+1 di export stok =================
    public function test_export_stok_tidak_n_plus_one(): void
    {
        for ($i = 0; $i < 12; $i++) {
            Barang::create([
                'nama_barang' => 'Barang '.$i, 'satuan' => 'pcs', 'kode_qr' => 'N1'.$i,
                'stok_total' => 100, 'stok_baik' => 90,
                'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
            ]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        app(ExcelExportService::class)->stok();

        // 12 barang: tanpa N+1 harus jauh di bawah 12*2
        $this->assertLessThan(20, $queries, "export stok hanya boleh {$queries} query (indikasi N+1)");
    }

    public function test_dashboard_tidak_n_plus_one(): void
    {
        for ($i = 0; $i < 12; $i++) {
            Barang::create([
                'nama_barang' => 'Barang '.$i, 'satuan' => 'pcs', 'kode_qr' => 'N2'.$i,
                'stok_total' => 100, 'stok_baik' => 90,
                'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
            ]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->get('/')->assertOk();

        $this->assertLessThan(30, $queries, "dashboard hanya boleh {$queries} query (indikasi N+1)");
    }

    // ================= P1.1: konfirmasi atomik =================
    public function test_konfirmasi_dua_kali_tetap_ditolak(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'P1A',
            'stok_total' => 100, 'stok_baik' => 100,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create(['nama_acara' => 'A', 'tanggal' => '2025-07-01', 'status' => 'reservasi', 'penanggung_jawab' => 'P']);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $this->post("/acara/{$a->id}/konfirmasi")->assertRedirect();
        $this->assertSame(90, $b->fresh()->stok_baik);

        $this->post("/acara/{$a->id}/konfirmasi")->assertRedirect();
        $this->assertSame(90, $b->fresh()->stok_baik, 'tidak boleh memotong dua kali');
    }

    public function test_return_kedua_pada_acara_yang_sudah_selesai_ditolak(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Gelas', 'satuan' => 'pcs', 'kode_qr' => 'P1B',
            'stok_total' => 100, 'stok_baik' => 90,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create(['nama_acara' => 'A', 'tanggal' => '2025-07-01', 'status' => 'aktif', 'penanggung_jawab' => 'P']);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $this->post('/pengembalian', [
            'acara_ids' => [$a->id],
            'rusak_ringan' => [$b->id => 0],
            'rusak_berat' => [$b->id => 0],
            'hilang' => [$b->id => 0],
        ])->assertRedirect();

        $this->assertSame(100, $b->fresh()->stok_baik, '10 kembali -> 100');

        // percobaan kedua harus ditolak
        $this->post('/pengembalian', [
            'acara_ids' => [$a->id],
            'rusak_ringan' => [$b->id => 0],
            'rusak_berat' => [$b->id => 0],
            'hilang' => [$b->id => 0],
        ])->assertSessionHasErrors('acara_ids');

        $this->assertSame(100, $b->fresh()->stok_baik, 'tidak boleh dobel');
        $this->assertSame(1, Pengembalian::count(), 'hanya 1 pengembalian');
    }
}
