<?php

use App\Http\Controllers\AI\AiConversationController;
use App\Http\Controllers\AI\AiKnowledgeDocumentController;
use App\Http\Controllers\AI\AiMessageController;
use App\Http\Controllers\AI\AiVoiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public pages do not provision users or resolve the legacy team-based session.
// AI API routes below keep their existing authentication and team middleware.
Route::inertia('/', 'farmer/Dashboard')->defaults('farmer_guest', true)->name('home');
Route::inertia('finance', 'farmer/Finance')->defaults('farmer_guest', true)->name('finance');
Route::inertia('kebun/detail', 'farmer/KebunDetail')->defaults('farmer_guest', true)->name('kebun.detail');
Route::inertia('kebun/aktivitas', 'farmer/KebunAktivitas')->defaults('farmer_guest', true)->name('kebun.aktivitas');
Route::inertia('/kebun', 'farmer/Kebun')->defaults('farmer_guest', true)->name('kebun');
Route::inertia('/aktivitas', 'farmer/Aktivitas')->defaults('farmer_guest', true)->name('aktivitas');
Route::inertia('/rincian-aktivitas', 'farmer/RincianAktivitas')->defaults('farmer_guest', true)->name('rincian-aktivitas');
Route::inertia('/laporan', 'farmer/Laporan')->defaults('farmer_guest', true)->name('laporan');
Route::get('/bypass', function (Request $request) {
    $team = $request->user()->currentTeam;
    Gate::authorize('ai.access', $team);

    return redirect()->route('ai.page', ['current_team' => $team->slug]);
})->middleware(['auth', 'verified'])->name('bypass');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('ai', fn () => Inertia::render('farmer/Index'))->name('ai.page');

        Route::prefix('ai')->name('ai.')->group(function () {
            Route::get('conversations', [AiConversationController::class, 'index'])->name('conversations.index');
            Route::post('conversations', [AiConversationController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('conversations.store');
            Route::get('conversations/{conversation}', [AiConversationController::class, 'show'])->name('conversations.show');
            Route::post('messages', [AiMessageController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('messages.store');
            Route::post('conversations/{conversation}/messages', [AiMessageController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('conversation-messages.store');
            Route::post('voice/transcribe', [AiVoiceController::class, 'transcribe'])
                ->middleware('throttle:10,1')
                ->name('voice.transcribe');
            Route::post('voice/synthesize', [AiVoiceController::class, 'synthesize'])
                ->middleware('throttle:20,1')
                ->name('voice.synthesize');
            Route::get('knowledge', [AiKnowledgeDocumentController::class, 'index'])->name('knowledge.index');
            Route::post('knowledge', [AiKnowledgeDocumentController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('knowledge.store');
            Route::patch('knowledge/{document}', [AiKnowledgeDocumentController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('knowledge.update');
            Route::delete('knowledge/{document}', [AiKnowledgeDocumentController::class, 'destroy'])
                ->middleware('throttle:30,1')
                ->name('knowledge.destroy');
            Route::post('knowledge/{document}/publish', [AiKnowledgeDocumentController::class, 'publish'])
                ->middleware('throttle:30,1')
                ->name('knowledge.publish');
            Route::post('knowledge/{document}/archive', [AiKnowledgeDocumentController::class, 'archive'])
                ->middleware('throttle:30,1')
                ->name('knowledge.archive');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
