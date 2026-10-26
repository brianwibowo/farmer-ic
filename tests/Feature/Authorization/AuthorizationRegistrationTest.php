<?php

namespace Tests\Feature\Authorization;

use App\Contracts\KebunAssignments;
use App\Policies\ResourceAccessPolicy;
use App\Support\Access\ResourceAccess;
use App\Support\Access\UnconfiguredKebunAssignments;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationRegistrationTest extends TestCase
{
    public function test_authorization_is_registered_during_application_boot(): void
    {
        // No test adapter: verify the application's real container binding.
        $this->assertTrue($this->app->bound(KebunAssignments::class));
        $this->assertInstanceOf(UnconfiguredKebunAssignments::class, app(KebunAssignments::class));
        $this->assertTrue(Gate::has('ai.access'));
        $this->assertSame(ResourceAccessPolicy::class, Gate::policies()[ResourceAccess::class] ?? null);
        $this->assertInstanceOf(ResourceAccessPolicy::class, Gate::getPolicyFor(ResourceAccess::class));
    }
}
