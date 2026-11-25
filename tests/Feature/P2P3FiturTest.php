<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Models\DetailPengembalian;
use Illuminate\Support\Facades\Storage;

class P2P3FiturTest extends TestCase
{
    private function b(string $kode): Barang
    {
        return Barang::create([
            'nama_barang'=>'Barang '.$kode,'satuan'=>'pcs','kode_qr'=>'F'.$kode,
            'stok_total'=>100,'stok_baik'=>95,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
    }

    private function acaraSelesai(Barang $b, string $nama): Acara
    {
        $a = Acara::create([
            'nama_acara'=>$nama,'tanggal'=>'2025-06-10','status'=>'selesai','penanggung_jawab'=>'P',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>10]);

        $p = Pengembalian::create(['tanggal_pulang'=>'2025-06-11','dicatat_oleh'=>'P','catatan'=>null]);
        $p->acara()->attach($a->id);
        DetailPengembalian::create([
            'pengembalian_id'=>$p->id,'barang_id'=>$b->id,
            'jumlah_kembali'=>9,'jumlah_rusak_ringan'=>1,'jumlah_rusak_berat'=>0,'jumlah_hilang'=>0,
        ]);

        return $a;
    }

    /** Unduh xlsx dari route (streamed) lalu baca barisnya. */
    private function xlsx(string $url): array
    {
        $res = $this->get($url)->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($path, $res->streamedContent());
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray();
        @unlink($path);
        return $rows;
    }

    // ================= P2a: PWA =================
    public function test_halaman_punya_manifest_web_app(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('apple-mobile-web-app-capable', false);
    }

    public function test_manifest_webmanifest_valid(): void
    {
        $res = $this->get('/manifest.webmanifest');
        $res->assertOk();

        $json = json_decode($res->getContent(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('short_name', $json);
        $this->assertArrayHasKey('start_url', $json);
        $this->assertArrayHasKey('display', $json);
        $this->assertArrayHasKey('icons', $json);
        $this->assertNotEmpty($json['icons']);
    }

    // ================= P2b: filter export per acara =================
    public function test_export_excel_bisa_difilter_per_acara(): void
    {
        $b1 = $this->b('1');
        $b2 = $this->b('2');
        $a1 = $this->acaraSelesai($b1, 'Acara Satu');
        $this->acaraSelesai($b2, 'Acara Dua');

        $rows = $this->xlsx('/riwayat/excel?acara_id='.$a1->id);

        $teks = json_encode($rows);
        $this->assertStringContainsString('Acara Satu', $teks, 'acara terpilih harus muncul');
        $this->assertStringNotContainsString('Acara Dua', $teks, 'acara lain tidak boleh muncul');
    }

    public function test_export_excel_tanpa_filter_menampilkan_semua(): void
    {
        $this->acaraSelesai($this->b('1'), 'Acara Satu');
        $this->acaraSelesai($this->b('2'), 'Acara Dua');

        $teks = json_encode($this->xlsx('/riwayat/excel'));

        $this->assertStringContainsString('Acara Satu', $teks);
        $this->assertStringContainsString('Acara Dua', $teks);
    }

    // ================= P3a: kartu "Perlu perhatian" =================
    public function test_beranda_punya_kartu_perlu_perhatian(): void
    {
        $this->get('/')->assertOk()->assertSee('Perlu perhatian', false);
    }

    public function test_reservasi_menunggu_konfirmasi_muncul_di_perhatian(): void
    {
        $b = $this->b('3');
        Acara::create([
            'nama_acara'=>'Reservasi Baru','tanggal'=>'2025-07-01','status'=>'reservasi','penanggung_jawab'=>'P',
        ]);

        $this->get('/')->assertOk()->assertSee('Reservasi Baru', false);
    }

    public function test_barang_stok_nol_muncul_di_perhatian(): void
    {
        Barang::create([
            'nama_barang'=>'Barang Habis','satuan'=>'pcs','kode_qr'=>'FHABIS',
            'stok_total'=>0,'stok_baik'=>0,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);

        $this->get('/')->assertOk()->assertSee('Barang Habis', false);
    }
}