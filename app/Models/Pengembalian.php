<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengembalian extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'pengembalian';
    protected $fillable = ['tanggal_pulang', 'dicatat_oleh', 'catatan'];

    protected function casts(): array
    {
        return ['tanggal_pulang' => 'date'];
    }

    public function acara(): BelongsToMany
    {
        return $this->belongsToMany(Acara::class, 'acara_pengembalian', 'pengembalian_id', 'acara_id');
    }

    public function detailPengembalian(): HasMany
    {
        return $this->hasMany(DetailPengembalian::class, 'pengembalian_id');
    }

    // Rincian per barang: keluar, kembali, hilang (untuk view rekap & PDF)
    public function detailRows(): array
    {
        $acaraIds = $this->acara->pluck('id')->all();
        $outMap = [];
        \App\Models\DetailAcara::whereIn('acara_id', $acaraIds)->get()->each(function ($d) use (&$outMap) {
            $outMap[$d->barang_id] = ($outMap[$d->barang_id] ?? 0) + $d->jumlah_keluar;
        });
        $rows = [];
        foreach ($this->detailPengembalian as $detail) {
            $rows[] = [
                'barang'  => $detail->barang->nama_barang,
                'keluar'  => $outMap[$detail->barang_id] ?? 0,
                'kembali' => $detail->jumlah_kembali,
                'hilang'  => $detail->jumlah_hilang,
                'tersedia' => $detail->barang->stok_baik,
            ];
        }
        return $rows;
    }
}