<?php

namespace App\Services;

use App\Models\Pengembalian;
use App\Models\Acara;
use App\Models\DetailAcara;
use Illuminate\Support\Facades\DB;

class PengembalianService
{
    /**
     * Hitung kehilangan untuk satu gelombang pengembalian.
     * Hanya acara yang ditandai pada pengembalian ini yang diselesaikan;
     * acara lain yang masih aktif tidak dihitung hilang.
     */
    public function hitung(Pengembalian $p): void
    {
        DB::transaction(function () use ($p) {
            $acaraIds = $p->acara()->pluck('acara.id')->all();

            foreach ($p->detailPengembalian as $detail) {
                $qtyOut = DetailAcara::whereIn('acara_id', $acaraIds)
                    ->where('barang_id', $detail->barang_id)
                    ->sum('jumlah_keluar');

                $kembali = (int) $detail->jumlah_kembali;
                $hilang = $qtyOut - $kembali;

                $detail->jumlah_hilang = $hilang;
                $detail->save();

                $barang = $detail->barang;
                $barang->stok_baik += $kembali;
                $barang->stok_total -= $hilang;
                $barang->save();
            }

            Acara::whereIn('id', $acaraIds)->update(['status' => 'selesai']);
        });
    }
}