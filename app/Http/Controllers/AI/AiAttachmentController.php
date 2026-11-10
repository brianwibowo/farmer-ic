<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiAttachment;
use App\Models\AiConversation;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiAttachmentController extends Controller
{
    public function __invoke(Request $request, Team $current_team, AiAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);
        $actor = $request->user();
        $conversation = AiConversation::visibleTo($actor, $current_team)
            ->whereKey($attachment->ai_conversation_id)->where('user_id', $actor->id)->firstOrFail();
        $attachment = AiAttachment::visibleTo($actor, $current_team)
            ->whereKey($attachment->id)->where('ai_conversation_id', $conversation->id)
            ->where('user_id', $actor->id)->firstOrFail();

        abort_unless(in_array($attachment->mime_type, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true), 415);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
