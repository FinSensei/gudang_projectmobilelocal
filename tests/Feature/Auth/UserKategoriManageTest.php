<?php

namespace Tests\Feature\Auth;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\User;
use Tests\TestCase;

class UserKategoriManageTest extends TestCase
{
    public function test_admin_bisa_edit_user_lain(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@x.id', 'password' => bcrypt('x'), 'role' => 'admin']);
        $p = User::create(['name' => 'Pekerja', 'email' => 'p@x.id', 'password' => bcrypt('x'), 'role' => 'pekerja']);

        $this->actingAs($admin);

        $this->put("/users/{$p->id}", [
            'name' => 'Pekerja Baru',
            'email' => 'p@x.id',
            'role' => 'admin',
            'password' => '',
        ])->assertRedirect();

        $p->refresh();
        $this->assertSame('Pekerja Baru', $p->name);
        $this->assertSame('admin', $p->role);
    }

    public function test_admin_tidak_bisa_menghapus_dirinya_sendiri(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@x.id', 'password' => bcrypt('x'), 'role' => 'admin']);

        $this->actingAs($admin);

        $this->delete("/users/{$admin->id}")->assertRedirect();

        $this->assertNotNull($admin->fresh(), 'admin tidak boleh terhapus');
    }

    public function test_admin_bisa_menghapus_user_lain_soft_delete(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@x.id', 'password' => bcrypt('x'), 'role' => 'admin']);
        $p = User::create(['name' => 'Pekerja', 'email' => 'p@x.id', 'password' => bcrypt('x'), 'role' => 'pekerja']);

        $this->actingAs($admin);

        $this->delete("/users/{$p->id}")->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $p->id]);
        $this->assertNull(User::find($p->id), 'user hilang dari listing');
    }

    public function test_kategori_terpakai_tidak_bisa_dihapus(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@x.id', 'password' => bcrypt('x'), 'role' => 'admin']);
        $k = Kategori::create(['nama_kategori' => 'Peralatan Makan']);
        Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'K1', 'kategori_id' => $k->id, 'stok_total' => 10, 'stok_baik' => 10, 'stok_rusak' => 0]);

        $this->actingAs($admin);

        $this->delete("/kategori/{$k->id}")->assertRedirect();

        $this->assertNotNull($k->fresh(), 'kategori terpakai tidak boleh terhapus');
    }

    public function test_kategori_tak_terpakai_bisa_dihapus(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@x.id', 'password' => bcrypt('x'), 'role' => 'admin']);
        $k = Kategori::create(['nama_kategori' => 'Kosong']);

        $this->actingAs($admin);

        $this->delete("/kategori/{$k->id}")->assertRedirect();

        // Kategori pakai SoftDeletes -> cek deleted_at, bukan null
        $this->assertSoftDeleted('kategori', ['id' => $k->id]);
    }

    public function test_pekerja_tidak_bisa_kelola_user(): void
    {
        $p = User::create(['name' => 'P', 'email' => 'p@x.id', 'password' => bcrypt('x'), 'role' => 'pekerja']);

        $this->actingAs($p);
        $this->get('/users')->assertForbidden();
    }
}
