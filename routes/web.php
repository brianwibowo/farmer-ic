<?php

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\AI\AiConversationController;
use App\Http\Controllers\AI\AiKnowledgeDocumentController;
use App\Http\Controllers\AI\AiMessageController;
use App\Http\Controllers\AI\AiVoiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (! Auth::check()) {
        /** @var User $user */
        $user = User::firstOrCreate(
            ['email' => 'petani@farmer-ic.local'],
            [
                'name' => 'Petani IC',
                'password' => bcrypt('farmer-ic-secret'),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->currentTeam) {
            $team = $user->personalTeam() ?? (new CreateTeam)->handle($user, 'Kebun Petani', isPersonal: true);
            $user->switchTeam($team);
        }

        Auth::login($user);
    }

    $team = Auth::user()?->currentTeam;

    return Inertia::render('farmer/Dashboard', [
        'currentTeam' => $team,
    ]);
})->name('home');

Route::inertia('finance', 'farmer/Finance')->name('finance');
Route::inertia('kebun/detail', 'farmer/KebunDetail')->name('kebun.detail');
Route::inertia('kebun/aktivitas', 'farmer/KebunAktivitas')->name('kebun.aktivitas');
Route::inertia('/kebun', 'farmer/Kebun')->name('kebun');
Route::inertia('/aktivitas', 'farmer/Aktivitas')->name('aktivitas');
Route::inertia('/rincian-aktivitas', 'farmer/RincianAktivitas')->name('rincian-aktivitas');
Route::get('/bypass', function () {
    /** @var User $user */
    $user = User::firstOrCreate(
        ['email' => 'petani@farmer-ic.local'],
        [
            'name' => 'Petani IC',
            'password' => bcrypt('farmer-ic-secret'),
            'email_verified_at' => now(),
        ]
    );

    if (! $user->currentTeam) {
        $team = $user->personalTeam() ?? (new CreateTeam)->handle($user, 'Kebun Petani', isPersonal: true);
        $user->switchTeam($team);
    }

    Auth::login($user);

    $teamSlug = $user->currentTeam->slug;

    return redirect()->route('ai.page', ['current_team' => $teamSlug]);
})->name('bypass');

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
