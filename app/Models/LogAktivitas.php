<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAktivitas extends Model
{
    /** Maksimal baris log yang disimpan */
    public const MAX_ROWS = 100;

    protected $table = 'log_aktivitas';

    protected $fillable = ['aksi', 'entitas', 'keterangan'];

    protected static function booted(): void
    {
        // Simpan hanya MAX_ROWS aktivitas terbaru — sisanya otomatis dibuang
        static::created(function () {
            $keep = self::orderByDesc('id')->limit(self::MAX_ROWS)->pluck('id');
            self::whereNotIn('id', $keep)->delete();
        });
    }
}
