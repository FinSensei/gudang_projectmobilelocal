<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\Kategori;
use App\Models\Pengembalian;
use Illuminate\Support\Facades\DB;

/**
 * Simulasi performa & konsistensi data.
 */
class SimulasiPerformaTest extends TestCase
{
    public function test_dashboard_tidak_n_plus_1(): void
    {
        // 20 kategori + 20 barang
        $k = Kategori::create(['nama_kategori'=>'Makan']);
        for ($i = 1; $i <= 20; $i++) {
            Barang::create(['nama_barang'=>"Barang $i",'satuan'=>'pcs','kode_qr'=>"K$i",'kategori_id'=>$k->id,'stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get('/')->assertOk();

        $queries = DB::getQueryLog();
        DB::flushQueryLog();

        // Dashboard: 1 untuk barang, 1 acara, 1 kategori, 1 lowStock = ~4
        // Kalau N+1, query membengkak (20+ query untuk 20 barang)
        $this->assertLessThan(20, count($queries), 'indikasi N+1: '.count($queries).' query. '.json_encode(array_map(fn($q)=>$q['query'], $queries)));
    }

    public function test_riwayat_tidak_n_plus_1(): void
    {
        $b = Barang::create(['nama_barang'=>'S','satuan'=>'pcs','kode_qr'=>'K1','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'selesai']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>5]);

        for ($i = 1; $i <= 5; $i++) {
            $p = Pengembalian::create(['tanggal_pulang'=>'2026-01-02','dicatat_oleh'=>'P']);
            $p->acara()->attach($a->id);
            $p->detailPengembalian()->create(['barang_id'=>$b->id,'jumlah_kembali'=>5]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get('/riwayat')->assertOk();

        $queries = DB::getQueryLog();
        DB::flushQueryLog();

        $this->assertLessThan(15, count($queries), 'indikasi N+1: '.count($queries).' query');
    }

    public function test_scan_tidak_n_plus_1(): void
    {
        $b = Barang::create(['nama_barang'=>'S','satuan'=>'pcs','kode_qr'=>'SCAN1','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>5]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get('/b/SCAN1')->assertOk();

        $queries = DB::getQueryLog();
        DB::flushQueryLog();

        $this->assertLessThan(10, count($queries), 'indikasi N+1: '.count($queries).' query');
    }
}