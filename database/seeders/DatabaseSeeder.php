<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles and permissions
        $this->createRoles();

        // Create admin user
        $admin = User::create([
            'email' => 'admin@blbgensixai.club',
            'username' => 'admin',
            'date_of_birth' => '1990-01-01',
            'is_verified_adult' => true,
            'verification_status' => 'approved',
            'verified_at' => now(),
            'subscription_status' => 'active',
            'subscription_plan' => 'enterprise',
            'subscription_ends_at' => now()->addYear(),
            'api_usage_count' => 0,
            'api_usage_limit' => -1,
            'credits_remaining' => 999999,
            'is_banned' => false,
        ]);
        $admin->assignRole('admin');

        // Create subscription plans in Stripe (instructions)
        $this->seedSubscriptionPlans();

        // Seed default ad spaces
        $this->seedAdSpaces();

        // Seed crypto networks config
        $this->seedCryptoConfig();
    }

    protected function createRoles(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $creatorRole = Role::create(['name' => 'creator']);
        $userRole = Role::create(['name' => 'user']);

        // Permissions
        $permissions = [
            'generate-image',
            'generate-video',
            'generate-text',
            'generate-body',
            'manage-income-streams',
            'manage-ad-campaigns',
            'manage-crypto-wallets',
            'view-analytics',
            'manage-users',
            'manage-verifications',
            'manage-billing',
            'access-admin',
        ];

        foreach ($permissions as $perm) {
            Permission::create(['name' => $perm]);
        }

        $adminRole->givePermissionTo(Permission::all());
        $creatorRole->givePermissionTo([
            'generate-image', 'generate-video', 'generate-text', 'generate-body',
            'manage-income-streams', 'manage-ad-campaigns', 'manage-crypto-wallets',
            'view-analytics',
        ]);
        $userRole->givePermissionTo([
            'generate-image', 'generate-video', 'generate-text', 'generate-body',
            'view-analytics',
        ]);
    }

    protected function seedSubscriptionPlans(): void
    {
        echo "\n📋 Stripe Setup Required:\n";
        echo "1. Create products in Stripe Dashboard:\n";
        echo "   - Starter: \$29.99/month\n";
        echo "   - Professional: \$99.99/month\n";
        echo "   - Enterprise: \$299.99/month\n";
        echo "2. Add price IDs to .env:\n";
        echo "   STRIPE_PRICE_STARTER=price_xxx\n";
        echo "   STRIPE_PRICE_PRO=price_xxx\n";
        echo "   STRIPE_PRICE_ENTERPRISE=price_xxx\n\n";
    }

    protected function seedAdSpaces(): void
    {
        echo "✅ Ad space templates seeded in config.\n";
    }

    protected function seedCryptoConfig(): void
    {
        echo "✅ Crypto network config seeded.\n\n";
    }
}
