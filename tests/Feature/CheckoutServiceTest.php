<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Services\CheckoutService;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_mengurangi_stok_baik_dan_mencatat_detail(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $acara = Acara::create(['nama_acara'=>'A','status'=>'aktif']);

        app(CheckoutService::class)->buat($acara, [['barang_id'=>$b->id,'jumlah'=>30]]);

        $b->refresh();
        $this->assertSame(70, $b->stok_baik, 'baik berkurang 30');
        $this->assertSame(100, $b->stok_total, 'total tetap (barang masih dimiliki)');
        $this->assertSame(1, DetailAcara::where('acara_id', $acara->id)->count());
    }

    public function test_checkout_menolak_jika_stok_baik_tidak_cukup(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S2','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);
        $acara = Acara::create(['nama_acara'=>'A','status'=>'aktif']);

        try {
            app(CheckoutService::class)->buat($acara, [['barang_id'=>$b->id,'jumlah'=>30]]);
            $this->fail('harusnya menolak stok tidak cukup');
        } catch (\RuntimeException $e) {
            // expected
        }

        $b->refresh();
        $this->assertSame(10, $b->stok_baik, 'stok tidak berubah');
        $this->assertSame(0, DetailAcara::count(), 'tidak ada detail tercatat');
    }

    public function test_checkout_multi_item_mengurangi_tiap_barang(): void
    {
        $a = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S3','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $b = Barang::create(['nama_barang'=>'Gelas','satuan'=>'pcs','kode_qr'=>'G1','stok_total'=>50,'stok_baik'=>50,'stok_rusak'=>0]);
        $acara = Acara::create(['nama_acara'=>'A','status'=>'aktif']);

        app(CheckoutService::class)->buat($acara, [
            ['barang_id'=>$a->id,'jumlah'=>20],
            ['barang_id'=>$b->id,'jumlah'=>10],
        ]);

        $a->refresh(); $b->refresh();
        $this->assertSame(80, $a->stok_baik);
        $this->assertSame(40, $b->stok_baik);
    }
}