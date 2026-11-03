<?php

namespace Tests\Feature\Petugas;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetugasTaskListTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_listing_requires_a_real_assignment_even_for_developer_portal_access(): void
    {
        foreach (['petugas', 'superadmin'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get('/petugas/tasks')->assertForbidden();
        }
    }

    public function test_task_listing_remains_authenticated_and_portal_protected(): void
    {
        $this->get('/petugas/tasks')->assertRedirect('/login');
        foreach (['owner', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/petugas/tasks')->assertForbidden();
        }
    }
}
