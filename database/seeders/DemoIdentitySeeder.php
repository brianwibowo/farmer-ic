<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoIdentitySeeder extends Seeder
{
    /** @var array<string, array{name: string, email: string, password: string}> */
    public const ACCOUNTS = [
        'superadmin' => ['name' => 'SuperAdmin Demo', 'email' => 'superadmin@farmer-ic.test', 'password' => 'SuperAdmin123!'],
        'owner' => ['name' => 'Owner Demo', 'email' => 'owner@farmer-ic.test', 'password' => 'Owner123!'],
        'admin' => ['name' => 'Admin Demo', 'email' => 'admin@farmer-ic.test', 'password' => 'Admin123!'],
        'petugas' => ['name' => 'Petugas Demo', 'email' => 'petugas@farmer-ic.test', 'password' => 'Petugas123!'],
    ];

    public function run(): void
    {
        $roles = [];
        foreach ([
            'superadmin' => 'Akses pengembang untuk seluruh area demo.',
            'owner' => 'Pemilik usaha tani dengan akses baca dan AI.',
            'admin' => 'Pengelola operasional kebun.',
            'petugas' => 'Petugas lapangan dengan assignment satu kebun.',
        ] as $name => $description) {
            $roles[$name] = Role::query()->updateOrCreate(['name' => $name], ['description' => $description]);
        }

        $users = [];
        foreach (self::ACCOUNTS as $role => $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);
            $user->forceFill([
                'name' => $account['name'],
                'role_id' => $roles[$role]->id,
                'password' => $account['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
            $users[$role] = $user->refresh();
        }

        $team = Team::query()->withTrashed()->firstOrNew(['slug' => 'farm-ic-demo']);
        if ($team->trashed()) {
            $team->restore();
        }
        $team->forceFill(['name' => 'Farm IC Demo', 'is_personal' => false])->save();

        $teamRoles = [
            'superadmin' => TeamRole::Owner->value,
            'owner' => TeamRole::Owner->value,
            'admin' => TeamRole::Admin->value,
            'petugas' => TeamRole::Member->value,
        ];
        foreach ($teamRoles as $role => $teamRole) {
            DB::table('team_members')->updateOrInsert(
                ['team_id' => $team->id, 'user_id' => $users[$role]->id],
                ['role' => $teamRole, 'created_at' => now(), 'updated_at' => now()],
            );
            $users[$role]->forceFill(['current_team_id' => $team->id])->save();
        }
    }
}
