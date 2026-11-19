<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Support\FarmImageFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FarmImageController extends Controller
{
    public function __invoke(Request $request, Farm $farm): StreamedResponse
    {
        Gate::authorize('view', $farm);
        $farm = Farm::visibleTo($request->user(), $farm->team)->findOrFail($farm->id);
        abort_unless(FarmImageFile::valid($farm), 404);
        $disk = Storage::disk('farm');
        abort_unless($disk->exists($farm->image_url), 404);

        return $disk->response($farm->image_url, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
