<?php

namespace Tests\Feature\Petugas;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PetugasTaskListTest extends TestCase
{
    use RefreshDatabase;

    public function test_petugas_can_open_the_task_list_fixture(): void
    {
        $actor = User::factory()->create(['role' => 'petugas']);

        $this->actingAs($actor)
            ->get('/petugas/tasks?status=overdue')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/Tasks')
                ->where('isFixture', true)
                ->where('filters.status', 'overdue')
                ->has('tasks', 3)
                ->where('tasks.0.subblok.farm.name', 'Kebun Cileuksa Demo')
                ->where('tasks.0.activity_category', 'maintenance')
                ->where('tasks.0.activity_subcategory', 'follow_up_fertilization')
                ->where('tasks.0.can.start', true)
                ->where('tasks.0.can.submit', false));
    }

    public function test_guests_and_non_petugas_cannot_open_the_task_list(): void
    {
        $this->get('/petugas/tasks')->assertRedirect('/login');

        foreach (['admin', 'owner'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get('/petugas/tasks')->assertForbidden();
        }

        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($superadmin)->get('/petugas/tasks')->assertOk();
    }
}
