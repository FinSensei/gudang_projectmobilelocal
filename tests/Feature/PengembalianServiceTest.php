<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Models\DetailPengembalian;
use App\Services\PengembalianService;

class PengembalianServiceTest extends TestCase
{

    public function test_pengembalian_menghitung_hilang_dari_beberapa_event_tanpa_menghitung_event_yang_masih_keluar(): void
    {
        // stok_baik 350 = 1000 - 300(A) - 200(B) - 150(C)  → checkout sudah terjadi
        $sendok = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'SENDOK1',
            'stok_total' => 1000, 'stok_baik' => 350, 'stok_rusak' => 0,
        ]);

        $a = Acara::create(['nama_acara' => 'A', 'status' => 'aktif']);
        $b = Acara::create(['nama_acara' => 'B', 'status' => 'aktif']);
        $c = Acara::create(['nama_acara' => 'C', 'status' => 'aktif']);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $sendok->id, 'jumlah_keluar' => 300]);
        DetailAcara::create(['acara_id' => $b->id, 'barang_id' => $sendok->id, 'jumlah_keluar' => 200]);
        DetailAcara::create(['acara_id' => $c->id, 'barang_id' => $sendok->id, 'jumlah_keluar' => 150]);

        $p = Pengembalian::create(['tanggal_pulang' => '2026-11-21', 'dicatat_oleh' => 'operator']);
        $p->acara()->attach([$a->id, $b->id]);
        DetailPengembalian::create(['pengembalian_id' => $p->id, 'barang_id' => $sendok->id, 'jumlah_kembali' => 490]);

        app(PengembalianService::class)->hitung($p->refresh());

        $sendok->refresh();
        $this->assertSame(990, $sendok->stok_total, 'total harus 990 (hilang 10)');
        $this->assertSame(840, $sendok->stok_baik, 'baik harus 840 (350 + 490 kembali)');
        $this->assertSame('aktif', $c->refresh()->status, 'C belum kembali, harus tetap aktif');
        $this->assertSame(150, $sendok->stokKeluar(), 'keluar 150 (C) — bukan hilang');
    }
}