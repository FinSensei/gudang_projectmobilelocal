<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPengembalian extends Model
{
    protected $table = 'detail_pengembalian';

    protected $fillable = [
        'pengembalian_id', 'barang_id',
        'jumlah_kembali', 'jumlah_rusak_ringan', 'jumlah_rusak_berat', 'jumlah_hilang', 'keterangan',
    ];

    protected $attributes = [
        'jumlah_rusak_ringan' => 0,
        'jumlah_rusak_berat' => 0,
    ];

    protected function casts(): array
    {
        return [
            'jumlah_kembali' => 'integer',
            'jumlah_rusak_ringan' => 'integer',
            'jumlah_rusak_berat' => 'integer',
            'jumlah_hilang' => 'integer',
        ];
    }

    public function pengembalian(): BelongsTo
    {
        return $this->belongsTo(Pengembalian::class, 'pengembalian_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
