<?php

namespace App\Services;

use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use Illuminate\Support\Facades\DB;

class PengembalianService
{
    /**
     * Hitung kehilangan untuk satu gelombang pengembalian.
     *
     * Per barang: keluar = baik + rusak ringan + rusak berat + hilang
     * - baik          -> stok_baik bertambah
     * - rusak ringan  -> stok_rusak_ringan bertambah
     * - rusak berat   -> stok_rusak_berat bertambah
     * - hilang        -> stok_total berkurang
     *
     * Kalau total (baik+ringan+berat) melebihi jumlah keluar, kelebihannya
     * dipotong dari rusak berat dulu, lalu ringan, terakhir baik — supaya
     * laporan tidak pernah mengarang barang.
     */
    public function hitung(Pengembalian $p): void
    {
        DB::transaction(function () use ($p) {
            $acaraIds = $p->acara()->pluck('acara.id')->all();

            foreach ($p->detailPengembalian as $detail) {
                $qtyOut = (int) DetailAcara::whereIn('acara_id', $acaraIds)
                    ->where('barang_id', $detail->barang_id)
                    ->sum('jumlah_keluar');

                $baik = (int) $detail->jumlah_kembali;
                $ringan = (int) $detail->jumlah_rusak_ringan;
                $berat = (int) $detail->jumlah_rusak_berat;

                // Tidak boleh lebih banyak dari yang keluar
                $lebih = ($baik + $ringan + $berat) - $qtyOut;
                if ($lebih > 0) {
                    $potong = min($berat, $lebih);
                    $berat -= $potong;
                    $lebih -= $potong;

                    $potong = min($ringan, $lebih);
                    $ringan -= $potong;
                    $lebih -= $potong;

                    $baik = max(0, $baik - $lebih);
                }

                $hilang = $qtyOut - ($baik + $ringan + $berat);

                $detail->jumlah_kembali = $baik;
                $detail->jumlah_rusak_ringan = $ringan;
                $detail->jumlah_rusak_berat = $berat;
                $detail->jumlah_hilang = $hilang;
                $detail->save();

                $barang = $detail->barang;
                $barang->stok_baik += $baik;
                $barang->stok_rusak_ringan += $ringan;
                $barang->stok_rusak_berat += $berat;
                $barang->stok_total -= $hilang;
                $barang->save();
            }

            Acara::whereIn('id', $acaraIds)->update(['status' => 'selesai']);
        });
    }
}
