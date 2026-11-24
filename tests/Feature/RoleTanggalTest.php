<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\User;

class RoleTanggalTest extends TestCase
{
    private function admin(): User
    {
        return User::create(['name'=>'Admin','email'=>'a@x.id','password'=>bcrypt('x'),'role'=>'admin']);
    }

    // ---------- 1. Admin tidak bisa ubah role sendiri ----------
    public function test_admin_tidak_bisa_mengubah_role_sendiri(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->put("/users/{$admin->id}", [
            'name'     => 'Admin',
            'email'    => 'a@x.id',
            'role'     => 'pekerja',   // coba turunkan diri sendiri
            'password' => '',
        ])->assertRedirect();

        $this->assertSame('admin', $admin->fresh()->role, 'role admin tidak boleh berubah jadi pekerja');
    }

    public function test_admin_tetap_bisa_ubah_role_user_lain(): void
    {
        $admin = $this->admin();
        $p = User::create(['name'=>'P','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja']);
        $this->actingAs($admin);

        $this->put("/users/{$p->id}", [
            'name'     => 'P',
            'email'    => 'p@x.id',
            'role'     => 'admin',
            'password' => '',
        ])->assertRedirect();

        $this->assertSame('admin', $p->fresh()->role, 'role user lain tetap bisa diubah');
    }

    // ---------- 2. Acara tidak bisa tanggal lampau ----------
    public function test_acara_tanggal_lampau_ditolak(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'T1','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);

        $this->post('/acara', [
            'nama_acara' => 'Acara Lama',
            'tanggal'    => '2020-01-01',   // masa lalu
            'items'      => [['barang_id'=>$b->id,'jumlah'=>1]],
        ])->assertSessionHasErrors('tanggal');

        $this->assertSame(0, Acara::count(), 'acara tidak boleh dibuat');
    }

    public function test_acara_tanggal_hari_ini_boleh(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'T2','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);

        $this->post('/acara', [
            'nama_acara' => 'Hari Ini',
            'tanggal'    => today()->toDateString(),
            'items'      => [['barang_id'=>$b->id,'jumlah'=>1]],
        ])->assertRedirect();

        $this->assertSame(1, Acara::count(), 'acara hari ini boleh dibuat');
    }

    public function test_form_acara_punya_min_tanggal_hari_ini(): void
    {
        $this->get('/acara')
            ->assertOk()
            ->assertSee('min="'.today()->toDateString().'"', false);
    }
}