<?php

namespace Tests\Unit;

use App\Models\Farm;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserIsPicTest extends TestCase
{
    /** @return array<string, array{string|null, string|null, string|null, string|null, bool}> */
    public static function picCases(): array
    {
        return [
            'assigned petugas is PIC' => ['petugas', 'farm-a', 'farm-a', 'user-a', true],
            'assigned petugas is not PIC' => ['petugas', 'farm-a', 'farm-a', 'user-b', false],
            'farm has no PIC' => ['petugas', 'farm-a', 'farm-a', null, false],
            'petugas has no assignment' => ['petugas', null, null, null, false],
            'assigned farm is missing' => ['petugas', 'farm-a', null, null, false],
            'loaded farm differs from assignment' => ['petugas', 'farm-a', 'farm-b', 'user-a', false],
            'owner cannot be PIC' => ['owner', 'farm-a', 'farm-a', 'user-a', false],
            'admin cannot be PIC' => ['admin', 'farm-a', 'farm-a', 'user-a', false],
            'superadmin cannot be PIC' => ['superadmin', 'farm-a', 'farm-a', 'user-a', false],
            'unknown role cannot be PIC' => ['unknown', 'farm-a', 'farm-a', 'user-a', false],
            'missing role cannot be PIC' => [null, 'farm-a', 'farm-a', 'user-a', false],
        ];
    }

    #[DataProvider('picCases')]
    public function test_pic_status_uses_petugas_role_and_assigned_farm(
        ?string $roleName,
        ?string $assignedFarmId,
        ?string $loadedFarmId,
        ?string $picUserId,
        bool $expected,
    ): void {
        // In-memory model fixtures; no database persistence or policy access is exercised.
        $user = (new User)->forceFill(['id' => 'user-a', 'assigned_farm_id' => $assignedFarmId]);
        $role = $roleName === null ? null : (new Role)->forceFill(['name' => $roleName]);
        $farm = $loadedFarmId === null ? null : (new Farm)->forceFill([
            'id' => $loadedFarmId,
            'pic_user_id' => $picUserId,
        ]);
        $user->setRelation('role', $role)->setRelation('assignedFarm', $farm);

        $this->assertSame($expected, $user->isPic());
    }
}
