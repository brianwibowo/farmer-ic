<?php

use App\Http\Controllers\Activity\AdminLogbookController;
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
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Owner\OwnerController;
use App\Http\Controllers\Owner\OwnerTenantController;
use App\Http\Controllers\Petugas\PetugasActivityController;
use App\Http\Controllers\Petugas\PetugasGardenController;
use App\Http\Controllers\Petugas\PetugasHomeController;
use App\Http\Controllers\Petugas\PetugasTaskController;
use App\Http\Controllers\Sop\SopTemplateController;
use App\Http\Controllers\Sop\SopTemplateTaskController;
use App\Http\Controllers\Sop\SopTemplateVersionController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UserManagementController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', HomeController::class)->middleware(['auth', 'verified'])->name('home');

Route::middleware(['auth', 'verified', 'can:owner.access'])->group(function (): void {
    Route::get('kebun', [OwnerController::class, 'farms'])->name('kebun');
    Route::get('kebun/detail', function (Request $request) {
        $id = (string) $request->query('id', '');

        return Str::isUuid($id) ? to_route('owner.farms.show', $id) : to_route('kebun');
    })->name('kebun.detail');
    Route::get('kebun/aktivitas', fn (Request $request) => to_route('aktivitas',
        Str::isUuid((string) $request->query('id', '')) ? ['farm_id' => $request->query('id')] : []))->name('kebun.aktivitas');
    Route::get('kebun/{farm}', [OwnerController::class, 'farm'])->whereUuid('farm')->name('owner.farms.show');
    Route::get('kebun/{farm}/blok/{block}', [OwnerController::class, 'block'])
        ->whereUuid(['farm', 'block'])->name('owner.blocks.show');
    Route::get('aktivitas', [OwnerController::class, 'activities'])->name('aktivitas');
    Route::get('aktivitas/{activity}', [OwnerController::class, 'activity'])->whereUuid('activity')->name('owner.activities.show');
    Route::get('rincian-aktivitas', function (Request $request) {
        $id = (string) $request->query('id', '');

        return Str::isUuid($id) ? to_route('owner.activities.show', $id) : to_route('aktivitas');
    })->name('rincian-aktivitas');
    Route::redirect('laporan', '/')->name('laporan');
    Route::get('lainnya', [OwnerController::class, 'more'])->name('owner.more');
    Route::get('lainnya/sop', [OwnerController::class, 'sops'])->name('owner.sops.index');
    Route::get('lainnya/sop/{sopTemplate}', [OwnerController::class, 'sop'])->whereUuid('sopTemplate')->name('owner.sops.show');
    Route::get('lainnya/pengguna', [OwnerController::class, 'users'])->name('owner.users.index');
    Route::get('lainnya/pengguna/{managedUser}', [OwnerController::class, 'user'])->whereUuid('managedUser')->name('owner.users.show');
    Route::post('owner/tenant/{team:slug}', OwnerTenantController::class)->name('owner.tenant.switch');
});

Route::prefix('petugas')->name('petugas.')->middleware(['auth', 'verified', 'can:petugas.access'])->group(function (): void {
    Route::get('/', PetugasHomeController::class)->name('home');
    Route::get('kebun', PetugasGardenController::class)->name('kebun');
    Route::get('tasks', [PetugasTaskController::class, 'index'])->name('tasks.index');
    Route::inertia('aktivitas', 'petugas/Aktivitas')->name('aktivitas');
    Route::get('aktivitas/buat', [PetugasActivityController::class, 'create'])->name('aktivitas.create');
    Route::inertia('laporan', 'petugas/Laporan')->name('laporan');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
    Route::get('blocks', [FarmBlockController::class, 'farms'])->name('blocks.farms');
    Route::post('kebun/{farm}/blocks/{block}/batches', [FarmBlockController::class, 'storeBatch'])
        ->whereUuid(['farm', 'block'])->name('blocks.batches.store');
    Route::put('kebun/{farm}/blocks/{block}/batches/{batch}', [FarmBlockController::class, 'updateBatch'])
        ->whereUuid(['farm', 'block', 'batch'])->name('blocks.batches.update');
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
    Route::get('logbook', [AdminLogbookController::class, 'index'])->name('admin.logbook.index');
    Route::get('logbook/{farm}', [AdminLogbookController::class, 'farm'])->whereUuid('farm')->name('admin.logbook.farm');
    Route::get('logbook/{farm}/{dailyLog}', [AdminLogbookController::class, 'show'])->whereUuid(['farm', 'dailyLog'])->name('admin.logbook.show');

    Route::get('sops', [SopTemplateController::class, 'index'])->name('sops.index');
    Route::get('sops/create', [SopTemplateController::class, 'create'])->name('sops.create');
    Route::post('sops', [SopTemplateController::class, 'store'])->name('sops.store');
    Route::get('sops/{sopTemplate}', [SopTemplateController::class, 'show'])->whereUuid('sopTemplate')->name('sops.show');
    Route::get('sops/{sopTemplate}/edit', [SopTemplateController::class, 'edit'])->whereUuid('sopTemplate')->name('sops.edit');
    Route::put('sops/{sopTemplate}', [SopTemplateController::class, 'update'])->whereUuid('sopTemplate')->name('sops.update');
    Route::delete('sops/{sopTemplate}', [SopTemplateController::class, 'destroy'])->whereUuid('sopTemplate')->name('sops.destroy');
    Route::post('sops/{sopTemplate}/versions', [SopTemplateVersionController::class, 'store'])->whereUuid('sopTemplate')->name('sops.versions.store');
    Route::post('sops/{sopTemplate}/versions/{version}/publish', [SopTemplateVersionController::class, 'publish'])->whereUuid(['sopTemplate', 'version'])->name('sops.versions.publish');
    Route::post('sops/{sopTemplate}/versions/{version}/tasks', [SopTemplateTaskController::class, 'store'])->whereUuid(['sopTemplate', 'version'])->name('sops.tasks.store');
    Route::put('sops/{sopTemplate}/versions/{version}/tasks/{task}', [SopTemplateTaskController::class, 'update'])->whereUuid(['sopTemplate', 'version', 'task'])->name('sops.tasks.update');
    Route::delete('sops/{sopTemplate}/versions/{version}/tasks/{task}', [SopTemplateTaskController::class, 'destroy'])->whereUuid(['sopTemplate', 'version', 'task'])->name('sops.tasks.destroy');

    Route::get('farm-regions', \App\Http\Controllers\AdministrativeRegionController::class)->name('farm-regions.index');
    Route::get('farm-management/{farm}/deletion-check', [FarmManagementController::class, 'deletionCheck'])->whereUuid('farm')->name('farm-management.deletion-check');
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
