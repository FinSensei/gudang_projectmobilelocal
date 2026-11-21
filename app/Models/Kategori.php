<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Kategori extends Model {
    use SoftDeletes;
    protected $table='kategori';
    protected $fillable=['nama_kategori','deskripsi'];
    public function barang(): HasMany { return $this->hasMany(Barang::class,'kategori_id'); }
}