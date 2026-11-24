<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;

class ExcelStokFormatTest extends TestCase
{
    /** Header tabel Stok ada di baris 3 (baris 1 judul, baris 2 kosong) */
    private function loadRows($res): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($path, $res->streamedContent() ?: $res->getContent());
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray();
        @unlink($path);
        return $rows;
    }

    public function test_header_stok_urutan_no_kode_nama_photo_jumlah_ukuran_keterangan(): void
    {
        Barang::create(['nama_barang'=>'Toples saus','satuan'=>'pcs','kode_qr'=>'PLW-BL-001','ukuran'=>'D.10.5 x T.10','stok_total'=>193,'stok_baik'=>193,'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,'lokasi'=>'Rak A1']);

        $res = $this->get('/stok/excel');
        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $rows = $this->loadRows($res);
        $header = array_map(fn($v) => (string) $v, $rows[2]);

        $this->assertSame('No',          $header[0]);
        $this->assertSame('Kode Barang', $header[1]);
        $this->assertSame('Nama Barang', $header[2]);
        $this->assertSame('Photo',       $header[3]);
        $this->assertSame('Total Stok',  $header[4]);
        $this->assertSame('Stok Baik',    $header[5]);
        $this->assertSame('Rusak Ringan', $header[6]);
        $this->assertSame('Rusak Berat',  $header[7]);
        $this->assertSame('Keterangan',   $header[8]);

        // data mulai baris 4
        $this->assertSame(1,               (int) $rows[3][0]);
        $this->assertSame('PLW-BL-001',    $rows[3][1]);
        $this->assertSame('Toples saus',    $rows[3][2]);
        $this->assertSame('N/A',           $rows[3][3]);
        $this->assertSame(193,             (int) $rows[3][4]);   // Total = baik+rusak
        $this->assertSame(193,             (int) $rows[3][5]);   // Baik
        $this->assertSame(0,               (int) $rows[3][6]);   // Rusak Ringan
        $this->assertSame(0,               (int) $rows[3][7]);   // Rusak Berat
    }

    public function test_baris_datanya_mengurut_dari_no_1(): void
    {
        Barang::create(['nama_barang'=>'A','satuan'=>'pcs','kode_qr'=>'K1','stok_total'=>5,'stok_baik'=>5,'stok_rusak'=>0]);
        Barang::create(['nama_barang'=>'B','satuan'=>'pcs','kode_qr'=>'K2','stok_total'=>6,'stok_baik'=>6,'stok_rusak'=>0]);

        $rows = $this->loadRows($this->get('/stok/excel'));

        $this->assertSame(1, (int) $rows[3][0]);
        $this->assertSame(2, (int) $rows[4][0]);
    }

    public function test_keterangan_menampilkan_rusak(): void
    {
        Barang::create(['nama_barang'=>'A','satuan'=>'pcs','kode_qr'=>'K1','stok_total'=>10,'stok_baik'=>7,'stok_rusak_ringan'=>3]);

        $rows = $this->loadRows($this->get('/stok/excel'));
        $this->assertSame(3, (int) $rows[3][6], 'kolom Rusak Ringan = 3');
    }
}