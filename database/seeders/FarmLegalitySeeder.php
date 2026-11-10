<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\FarmLegality;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/** Add missing legality examples to recognized prototype farms without resetting edits. */
class FarmLegalitySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Seed legalitas hanya tersedia pada environment local atau testing.');
        }

        // Bootstrap a fixed, persisted seed actor, then authorize all protected work.
        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])
            ->where('is_active', true)->whereHas('role', fn ($roles) => $roles->where('name', 'superadmin'))->first();
        $team = $actor?->teams()->where('slug', 'farm-ic-demo')->where('is_personal', false)->first();
        if (! $actor instanceof User || ! $team instanceof Team) {
            throw new RuntimeException('Superadmin dan tenant seed yang sah harus tersedia sebelum mengisi legalitas.');
        }
        Gate::forUser($actor)->authorize('view', $team);
        Gate::forUser($actor)->authorize('viewAny', [Farm::class, $team]);
        Gate::forUser($actor)->authorize('viewAny', [FarmLegality::class, $team]);

        DB::transaction(function () use ($actor, $team): void {
            Team::visibleTo($actor, $team)->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $base = Farm::visibleTo($actor, $team)->where('team_id', $team->id)
                ->whereIn('name', DemoFarmSeeder::FARM_NAMES)->lockForUpdate()->first();
            if ($base instanceof Farm && $base->land_tenure === 'owned') {
                $this->insertMissing($actor, $team, $base, 'cileuksa', $this->ownedSummary('Kebun Cileuksa'));
            }
            foreach (DemoOwnerPortfolioData::sites() as $site) {
                $farm = Farm::visibleTo($actor, $team)->where('team_id', $team->id)
                    ->whereKey(DemoOwnerPortfolioData::id($team, 'farm:'.$site['key']))->lockForUpdate()->first();
                // Never attach terms to another farm or a tenure changed since the fixture was created.
                if (! $farm instanceof Farm || $farm->land_tenure !== $site['land_tenure']) {
                    continue;
                }
                $data = $farm->land_tenure === 'owned'
                    ? $this->ownedSummary($farm->name)
                    : $this->agreement($farm, $site);
                $this->insertMissing($actor, $team, $farm, $site['key'], $data);
            }
        });
    }

    /** @param array<string, mixed> $data */
    private function insertMissing(User $actor, Team $team, Farm $farm, string $key, array $data): void
    {
        if (FarmLegality::visibleTo($actor, $team)->where('farm_id', $farm->id)->exists()) {
            return;
        }
        $id = DemoOwnerPortfolioData::id($team, 'legality:'.$key);
        // Collision detection never exposes/reuses a hidden row or moves it to this farm.
        if (FarmLegality::query()->whereKey($id)->exists()) {
            throw new RuntimeException('ID seed legalitas telah digunakan di luar lahan yang diharapkan. Tidak ada data ditimpa.');
        }
        Gate::forUser($actor)->authorize('create', [FarmLegality::class, $farm]);
        $legality = new FarmLegality;
        $legality->forceFill(['id' => $id, 'farm_id' => $farm->id, ...$data])->save();
    }

    /** @return array<string, mixed> */
    private function ownedSummary(string $farmName): array
    {
        return [
            'pks_started_at' => null,
            'pks_ends_at' => null,
            'institution' => 'Administrasi aset internal',
            'summary' => $farmName.' tercatat sebagai lahan milik sendiri untuk kegiatan budidaya. Administrasi aset mengelola berkas kepemilikan, batas bidang, dan pembaruan dokumen. Tidak ada periode PKS sewa atau kerja sama.',
        ];
    }

    /** @param array<string, mixed> $site
     * @return array<string, mixed>
     */
    private function agreement(Farm $farm, array $site): array
    {
        [$start, $end, $summary] = match ($site['key']) {
            'cibogo' => ['2025-07-01', '2028-06-30', 'Kerja sama pemanfaatan lahan untuk budidaya tanaman. Mitra menyediakan lahan, sedangkan pengelola mengatur budidaya, pemeliharaan, dan pencatatan hasil. Evaluasi kerja sama dilakukan setiap akhir siklus tanam.'],
            'mekarsari' => ['2026-01-01', '2027-12-31', 'Sewa lahan pertanian untuk kegiatan budidaya. Pengelola bertanggung jawab atas pemeliharaan lahan dan saluran air selama masa perjanjian. Pembahasan perpanjangan dijadwalkan sebelum masa sewa berakhir.'],
            'cikeas' => ['2024-09-01', '2029-08-31', 'Kerja sama pengelolaan lahan pertanian secara bertahap. Pemanfaatan blok dan jadwal penanaman mengikuti kesepakatan pengelola dengan kelompok tani. Ringkasan kegiatan dan hasil panen ditinjau bersama setiap triwulan.'],
            default => throw new RuntimeException('Skenario legalitas lahan belum didefinisikan.'),
        };

        return [
            'pks_started_at' => $start,
            'pks_ends_at' => $end,
            'institution' => $farm->getAttribute('land_tenure_party') ?: $site['land_tenure_party'],
            'summary' => $summary,
        ];
    }
}
