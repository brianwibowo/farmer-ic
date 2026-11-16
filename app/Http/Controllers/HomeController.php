<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (Gate::allows('admin.access')) {
            return redirect()->route('admin.dashboard');
        }

        if (Gate::allows('petugas.access')) {
            return redirect()->route('petugas.home');
        }

        Gate::authorize('owner.access');

        return to_route('owner.executive-summary');
    }
}
