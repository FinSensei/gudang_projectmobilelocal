<?php

namespace App\Services;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    /**
     * Catat barang keluar untuk satu acara.
     * Mengurangi stok_baik tiap barang, menolak jika stok tidak cukup (rollback).
     */
    public function buat(Acara $acara, array $items): void
    {
        DB::transaction(function () use ($acara, $items) {
            foreach ($items as $it) {
                $b = Barang::findOrFail($it['barang_id']);
                if ($b->stok_baik < $it['jumlah']) {
                    throw new \RuntimeException("Stok {$b->nama_barang} tidak cukup (baik {$b->stok_baik}).");
                }
                $b->stok_baik -= $it['jumlah'];
                $b->save();
                DetailAcara::create([
                    'acara_id'    => $acara->id,
                    'barang_id'   => $b->id,
                    'jumlah_keluar' => $it['jumlah'],
                ]);
            }
        });
    }
}