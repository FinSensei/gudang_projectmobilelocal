<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\Pengembalian;

class ExcelRekapHilangTest extends TestCase
{
    private function loadRows(): array
    {
        $xlsx = app(\App\Services\ExcelExportService::class)->riwayat();
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $xlsx->save($path);
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray();
        @unlink($path);
        return $rows;
    }

    private function seedData(): void
    {
        $b = Barang::create(['nama_barang'=>'Pemanas air','satuan'=>'pcs','kode_qr'=>'P1','stok_total'=>50,'stok_baik'=>50,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Musrembang','penanggung_jawab'=>'Ibu Peni','tanggal'=>'2026-11-13','status'=>'selesai']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>10]);
        $p = Pengembalian::create(['tanggal_pulang'=>'2026-11-14','dicatat_oleh'=>'Petugas']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id'=>$b->id,'jumlah_kembali'=>8,'jumlah_hilang'=>2]);
        $b->update(['stok_baik'=>58,'stok_total'=>48]);
    }

    public function test_kolom_terakhir_jadi_stok_hilang(): void
    {
        $this->seedData();
        $rows = $this->loadRows();

        // cari baris header tabel (yang mengandung 'Nama Barang')
        $header = null;
        foreach ($rows as $r) {
            if (in_array('Nama Barang', array_map(fn($v) => (string) $v, $r), true)) {
                $header = array_map(fn($v) => (string) $v, $r);
                break;
            }
        }
        $this->assertNotNull($header, 'header tabel tidak ditemukan');

        // kolom ke-7 (index 6) harus "Stok hilang", bukan "Keterangan"
        $this->assertSame('Stok hilang', $header[6], 'kolom terakhir harus Stok hilang');
        $this->assertSame('Keterangan', $header[1], 'kolom keterangan ke-2 tetap Keterangan');
    }

    public function test_nilai_kolom_terakhir_berupa_angka_hilang(): void
    {
        $this->seedData();
        $rows = $this->loadRows();

        // baris data: yang mengandung nama barang
        $dataRow = null;
        foreach ($rows as $r) {
            if (in_array('Pemanas air', array_map(fn($v) => (string) $v, $r), true)) {
                $dataRow = $r;
                break;
            }
        }
        $this->assertNotNull($dataRow, 'baris data tidak ditemukan');

        $this->assertSame(2, (int) $dataRow[6], 'kolom Stok hilang harus angka 2');
        $this->assertNotSame('hilang 2', (string) $dataRow[6], 'tidak boleh berupa teks "hilang 2"');
    }
}