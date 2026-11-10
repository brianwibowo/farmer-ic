<?php

namespace App\Http\Controllers;

use App\Models\FarmLegality;
use App\Models\FarmLegalityDocument;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FarmLegalityDocumentController extends Controller
{
    public function __invoke(Request $request, FarmLegalityDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);
        $actor = $request->user();
        $team = $actor->currentTeam;
        abort_unless($team instanceof Team, 403);
        $document = FarmLegalityDocument::visibleTo($actor, $team)->whereKey($document->id)->firstOrFail();
        FarmLegality::visibleTo($actor, $team)->whereKey($document->farm_legality_id)->firstOrFail();
        abort_unless($document->disk === 'legality' && $document->mime_type === 'application/pdf', 415);
        $disk = Storage::disk('legality');
        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
