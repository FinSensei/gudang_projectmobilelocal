<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DetailAcara extends Model {
    protected $table='detail_acara';
    public $timestamps=true;
    protected $fillable=['acara_id','barang_id','jumlah_keluar'];
    protected function casts(): array { return ['jumlah_keluar'=>'integer']; }
    public function acara(): BelongsTo { return $this->belongsTo(Acara::class,'acara_id'); }
    public function barang(): BelongsTo { return $this->belongsTo(Barang::class,'barang_id'); }
}