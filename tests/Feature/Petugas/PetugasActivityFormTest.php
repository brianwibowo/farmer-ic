<?php

namespace Tests\Feature\Petugas;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The former category-selector form is deliberately replaced by a task picker. */
class PetugasActivityFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_form_redirects_to_tasks_instead_of_accepting_browser_context(): void
    {
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->actingAs($actor)->get('/petugas/aktivitas/buat?category=harvest&task_id=forged')
            ->assertRedirect('/petugas/tasks');
    }

    public function test_generic_form_remains_authenticated_and_portal_protected(): void
    {
        $this->get('/petugas/aktivitas/buat')->assertRedirect('/login');
        foreach (['owner', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/petugas/aktivitas/buat')->assertForbidden();
        }
    }
}
