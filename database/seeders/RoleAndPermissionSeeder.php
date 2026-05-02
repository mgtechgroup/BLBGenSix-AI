<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'creator', 'user'];
        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role]);
        }

        $permissions = [
            'generate-image', 'generate-video', 'generate-text', 'generate-body',
            'manage-income-streams', 'manage-ad-campaigns', 'manage-crypto-wallets',
            'view-analytics', 'manage-users', 'manage-verifications',
            'manage-billing', 'access-admin',
        ];

        foreach ($permissions as $perm) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm]);
        }

        // Assign all to admin
        $admin = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::all());
    }
}
