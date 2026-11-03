<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): RedirectResponse|Response
    {
        if (Gate::allows('admin.access')) {
            return redirect()->route('admin.dashboard');
        }

        if (Gate::allows('petugas.access')) {
            return redirect()->route('petugas.home');
        }

        Gate::authorize('owner.access');

        return Inertia::render('farmer/Dashboard');
    }
}
