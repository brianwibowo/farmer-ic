<?php

use App\Http\Controllers\Activity\DailyActivityLogController;
use App\Http\Controllers\AI\AiConversationController;
use App\Http\Controllers\AI\AiKnowledgeDocumentController;
use App\Http\Controllers\AI\AiMessageController;
use App\Http\Controllers\AI\AiVoiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FarmBlockController;
use App\Http\Controllers\FarmManagementController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Finance\FinanceSummaryController;
use App\Http\Controllers\Finance\FinancialBudgetController;
use App\Http\Controllers\Finance\FinancialCategoryController;
use App\Http\Controllers\Finance\FinancialProjectionController;
use App\Http\Controllers\Finance\FinancialTransactionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Sop\SopTemplateController;
use App\Http\Controllers\Sop\SopTemplateCopyController;
use App\Http\Controllers\Sop\SopTemplateTaskController;
use App\Http\Controllers\Sop\SopTemplateVersionController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UserManagementController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public pages do not provision users or resolve the legacy team-based session.
// AI API routes below keep their existing authentication and team middleware.
Route::inertia('/', 'farmer/Dashboard')->defaults('farmer_guest', true)->name('home');
Route::inertia('kebun/detail', 'farmer/KebunDetail')->defaults('farmer_guest', true)->name('kebun.detail');
Route::inertia('kebun/aktivitas', 'farmer/KebunAktivitas')->defaults('farmer_guest', true)->name('kebun.aktivitas');
Route::inertia('/kebun', 'farmer/Kebun')->defaults('farmer_guest', true)->name('kebun');
Route::inertia('/aktivitas', 'farmer/Aktivitas')->defaults('farmer_guest', true)->name('aktivitas');
Route::inertia('/rincian-aktivitas', 'farmer/RincianAktivitas')->defaults('farmer_guest', true)->name('rincian-aktivitas');
Route::inertia('/laporan', 'farmer/Laporan')->defaults('farmer_guest', true)->name('laporan');

Route::prefix('petugas')->name('petugas.')->middleware(['auth', 'verified', 'can:petugas.access'])->group(function (): void {
    Route::inertia('/', 'petugas/Home')->name('home');
    Route::inertia('kebun', 'petugas/Kebun')->name('kebun');
    Route::inertia('aktivitas', 'petugas/Aktivitas')->name('aktivitas');
    Route::inertia('aktivitas/buat', 'petugas/ActivityCreate')->name('aktivitas.create');
    Route::inertia('laporan', 'petugas/Laporan')->name('laporan');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
    Route::get('blocks', [FarmBlockController::class, 'farms'])->name('blocks.farms');
    Route::post('kebun/{farm}/blocks/{block}/batches', [FarmBlockController::class, 'storeBatch'])
        ->whereUuid(['farm', 'block'])->name('blocks.batches.store');
    Route::resource('kebun/{farm}/blocks', FarmBlockController::class)
        ->whereUuid(['farm', 'block'])->names('blocks');
    Route::get('finance', [FinanceController::class, 'index'])->name('finance');
    Route::get('finance/transactions/export', [FinanceSummaryController::class, 'export'])->name('finance.transactions.export');
    Route::resource('finance/transactions', FinancialTransactionController::class)->only(['index', 'show', 'update', 'destroy'])->names('finance.transactions')->whereUuid('transaction');
    Route::resource('finance/categories', FinancialCategoryController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('finance.categories')->whereUuid('category');
    Route::resource('finance/budgets', FinancialBudgetController::class)->only(['index', 'show', 'update', 'destroy'])->names('finance.budgets')->whereUuid('budget');
    Route::resource('finance/projections', FinancialProjectionController::class)->only(['index', 'show', 'update', 'destroy'])->names('finance.projections')->whereUuid('projection');
    Route::post('finance/farms/{farm}/transactions', [FinancialTransactionController::class, 'store'])->whereUuid('farm')->name('finance.transactions.store');
    Route::post('finance/farms/{farm}/budgets', [FinancialBudgetController::class, 'store'])->whereUuid('farm')->name('finance.budgets.store');
    Route::post('finance/farms/{farm}/projections', [FinancialProjectionController::class, 'store'])->whereUuid('farm')->name('finance.projections.store');
});
Route::get('/bypass', function (Request $request) {
    $team = $request->user()->currentTeam;
    Gate::authorize('ai.access', $team);

    return redirect()->route('ai.page', ['current_team' => $team->slug]);
})->middleware(['auth', 'verified'])->name('bypass');

Route::middleware(['auth', 'verified'])->prefix('kebun/{farm}/daily-logs')->whereUuid('farm')->group(function (): void {
    Route::get('/', [DailyActivityLogController::class, 'index'])->name('daily-logs.index');
    Route::post('/', [DailyActivityLogController::class, 'store'])->name('daily-logs.store');
    Route::get('{dailyLog}', [DailyActivityLogController::class, 'show'])
        ->whereUuid('dailyLog')
        ->name('daily-logs.show');
});
Route::prefix('admin')->middleware(['auth', 'verified', 'can:admin.access'])->group(function (): void {
    Route::redirect('/', '/admin/dashboard')->name('admin.home');
    Route::get('dashboard', DashboardController::class)->name('admin.dashboard');

    Route::get('sops', [SopTemplateController::class, 'index'])->name('sops.index');
    Route::get('farms/{farm}/sops/create', [SopTemplateController::class, 'create'])->whereUuid('farm')->name('sops.create');
    Route::post('farms/{farm}/sops', [SopTemplateController::class, 'store'])->whereUuid('farm')->name('sops.store');
    Route::get('sops/{sopTemplate}', [SopTemplateController::class, 'show'])->whereUuid('sopTemplate')->name('sops.show');
    Route::get('sops/{sopTemplate}/edit', [SopTemplateController::class, 'edit'])->whereUuid('sopTemplate')->name('sops.edit');
    Route::put('sops/{sopTemplate}', [SopTemplateController::class, 'update'])->whereUuid('sopTemplate')->name('sops.update');
    Route::post('sops/{sopTemplate}/versions', [SopTemplateVersionController::class, 'store'])->whereUuid('sopTemplate')->name('sops.versions.store');
    Route::post('sops/{sopTemplate}/versions/{version}/publish', [SopTemplateVersionController::class, 'publish'])->whereUuid(['sopTemplate', 'version'])->name('sops.versions.publish');
    Route::post('sops/{sopTemplate}/versions/{version}/tasks', [SopTemplateTaskController::class, 'store'])->whereUuid(['sopTemplate', 'version'])->name('sops.tasks.store');
    Route::put('sops/{sopTemplate}/versions/{version}/tasks/{task}', [SopTemplateTaskController::class, 'update'])->whereUuid(['sopTemplate', 'version', 'task'])->name('sops.tasks.update');
    Route::delete('sops/{sopTemplate}/versions/{version}/tasks/{task}', [SopTemplateTaskController::class, 'destroy'])->whereUuid(['sopTemplate', 'version', 'task'])->name('sops.tasks.destroy');
    Route::post('sops/{sopTemplate}/versions/{version}/copy', [SopTemplateCopyController::class, 'store'])->whereUuid(['sopTemplate', 'version'])->name('sops.copy');

    Route::get('farm-management/{farm}/force-delete', [FarmManagementController::class, 'confirmForceDelete'])
        ->whereUuid('farm')->name('farm-management.force-delete');
    Route::delete('farm-management/{farm}/force-delete', [FarmManagementController::class, 'forceDestroy'])
        ->whereUuid('farm')->name('farm-management.force-destroy');
    Route::resource('farm-management', FarmManagementController::class)
        ->parameters(['farm-management' => 'farm'])
        ->whereUuid('farm');
});

Route::prefix('admin')->middleware(['auth', 'verified', 'can:users.manage'])->group(function (): void {
    Route::get('user-management/deleted', [UserManagementController::class, 'deleted'])->name('user-management.deleted');
    Route::patch('user-management/{managedUser}/restore', [UserManagementController::class, 'restore'])
        ->whereUuid('managedUser')->withTrashed()->name('user-management.restore');
    Route::resource('user-management', UserManagementController::class)
        ->parameters(['user-management' => 'managedUser'])
        ->whereUuid('managedUser');
});

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
