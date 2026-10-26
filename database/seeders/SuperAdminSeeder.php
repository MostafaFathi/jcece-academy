<?php

namespace Database\Seeders;

use App\Models\User;
use App\RoleName;
use App\UserStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;

class SuperAdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = (string) config('jcec.super_admin.email');
        $password = (string) config('jcec.super_admin.password');
        if ($password === '' && app()->environment('local', 'testing')) {
            $password = 'password';
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            throw new RuntimeException('A valid super-admin email and seed password are required.');
        }

        $this->call(RolesAndPermissionsSeeder::class);

        DB::transaction(function () use ($email, $password): void {
            $owners = User::withTrashed()->where('is_super_admin', true)->get();

            if ($owners->count() > 1) {
                throw new RuntimeException('More than one super-admin account exists. Resolve this before seeding.');
            }

            $owner = $owners->first();

            if ($owner && ($owner->trashed() || $owner->email !== $email)) {
                throw new RuntimeException('The existing super-admin account does not match the configured email.');
            }

            if (! $owner) {
                if (User::withTrashed()->where('email', $email)->exists()) {
                    throw new RuntimeException('The configured super-admin email already belongs to another account.');
                }

                $owner = new User;
                $owner->forceFill([
                    'name' => 'مدير النظام الأعلى',
                    'email' => $email,
                    'password' => $password,
                    'email_verified_at' => now(),
                    'preferred_locale' => 'ar',
                    'status' => UserStatus::Active,
                    'is_super_admin' => true,
                ])->save();
            }

            $owner->syncRoles([RoleName::Admin->value]);
            $owner->syncPermissions(Permission::query()->where('guard_name', 'web')->pluck('name')->all());
        });
    }
}
