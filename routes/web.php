<?php

use App\Http\Controllers\ContributionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ContributionPlanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FundAllocationRuleController;
use App\Http\Controllers\FundController;
use App\Http\Controllers\FundSettingsController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectTypeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MembershipApplicationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\RecetteController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::post('/locale', [LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/adhesion', [MembershipApplicationController::class, 'create'])->name('membership.apply');
    // Garde-fou contre les envois en rafale ; la limite des demandes valides est dans le contrôleur.
    Route::post('/adhesion', [MembershipApplicationController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('membership.store');
    Route::get('/adhesion/envoyee', [MembershipApplicationController::class, 'sent'])->name('membership.sent');
});

// Recette de l'application : page sans compte, protégée par un code secret (RECETTE_TOKEN).
Route::prefix('recette/{token}')->name('recette.')->group(function () {
    Route::get('/', [RecetteController::class, 'index'])->name('index');
    Route::post('/retours', [RecetteController::class, 'store'])->middleware('throttle:60,1')->name('store');
    Route::post('/retours/{feedback}/reponses', [RecetteController::class, 'reply'])->middleware('throttle:60,1')->name('reply');
    Route::get('/fichiers/{attachment}', [RecetteController::class, 'attachment'])->name('attachment');
    Route::get('/export', [RecetteController::class, 'export'])->name('export');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'active_member', 'policy', 'permission:view dashboard'])
    ->name('dashboard');

// Règlement intérieur : consultable à tout moment, à accepter avant d'accéder au reste de l'application.
Route::middleware(['auth', 'verified', 'active_member'])->group(function () {
    Route::get('/reglement-interieur', [PolicyController::class, 'show'])->name('policies.show');
    Route::post('/reglement-interieur/accepter', [PolicyController::class, 'accept'])->name('policies.accept');
});

Route::middleware(['auth', 'verified', 'active_member', 'policy'])->group(function () {
    // Types de projets et critères
    Route::get('/types-de-projets', [ProjectTypeController::class, 'index'])->name('project-types.index');
    Route::post('/types-de-projets', [ProjectTypeController::class, 'store'])->name('project-types.store');
    Route::put('/types-de-projets/{projectType}', [ProjectTypeController::class, 'update'])->name('project-types.update');
    Route::delete('/types-de-projets/{projectType}', [ProjectTypeController::class, 'destroy'])->name('project-types.destroy');
    Route::post('/types-de-projets/{projectType}/criteres', [ProjectTypeController::class, 'storeCriterion'])->name('project-types.criteria.store');
    Route::post('/types-de-projets/{projectType}/criteres/reprendre', [ProjectTypeController::class, 'importCriteria'])->name('project-types.criteria.import');
    Route::put('/criteres-types/{projectTypeCriterion}', [ProjectTypeController::class, 'updateCriterion'])->name('project-types.criteria.update');
    Route::patch('/criteres-types/{projectTypeCriterion}/deplacer', [ProjectTypeController::class, 'moveCriterion'])->name('project-types.criteria.move');
    Route::delete('/criteres-types/{projectTypeCriterion}', [ProjectTypeController::class, 'destroyCriterion'])->name('project-types.criteria.destroy');

    // Instruction, vote, décaissement et remboursements d'un dossier
    Route::patch('/criteres/{projectCriterion}', [ProjectController::class, 'checkCriterion'])->name('projects.criteria.check');
    Route::patch('/projects/{project}/decision', [ProjectController::class, 'decide'])->name('projects.decide');
    Route::post('/projects/{project}/decaissement', [ProjectController::class, 'prepareDisbursement'])->name('projects.disburse');
    Route::post('/projects/{project}/remboursements', [ProjectController::class, 'recordRepayment'])->name('projects.repayments.store');
    Route::patch('/votes/{vote}/dates', [VoteController::class, 'reschedule'])->name('votes.reschedule');

    // Validations des transactions
    Route::get('/validations', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/validations', [ApprovalController::class, 'store'])->name('approvals.store');
    Route::patch('/validations/{approvalRequest}/valider', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::patch('/validations/{approvalRequest}/refuser', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::patch('/validations/{approvalRequest}/relancer', [ApprovalController::class, 'retry'])->name('approvals.retry');

    // Rapports, relevés, vérification annuelle
    Route::get('/rapports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/rapports/export', [ReportController::class, 'export'])->middleware('throttle:20,1')->name('reports.export');
    Route::post('/rapports/partager', [ReportController::class, 'share'])->name('reports.share');
    Route::get('/rapports/partages/{sharedReport}', [ReportController::class, 'shared'])->name('reports.shared');
    Route::get('/rapports/partages/{sharedReport}/pdf', [ReportController::class, 'sharedPdf'])->name('reports.shared.pdf');
    Route::post('/rapports/verifications', [ReportController::class, 'storeAudit'])->name('reports.audits.store');
    Route::patch('/rapports/verifications/{auditReview}', [ReportController::class, 'confirmAudit'])->name('reports.audits.confirm');

    // Retrait d'un membre
    Route::get('/retrait', [WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::post('/retrait', [WithdrawalController::class, 'store'])->name('withdrawals.store');
    Route::put('/retraits/{withdrawalRequest}/calendrier', [WithdrawalController::class, 'schedule'])->name('withdrawals.schedule');
    Route::patch('/retraits/{withdrawalRequest}/annuler', [WithdrawalController::class, 'cancel'])->name('withdrawals.cancel');
    Route::post('/retraits/versements/{withdrawalInstallment}', [WithdrawalController::class, 'pay'])->name('withdrawals.pay');

    // Pénalités de cotisation
    Route::patch('/cotisations/echeances/{contributionDue}/penalite', [ContributionController::class, 'waivePenalty'])->name('contributions.dues.waive-penalty');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/tout-lire', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/parametres-fonds', [FundSettingsController::class, 'index'])->name('fund-settings.index');
    Route::put('/parametres-fonds/regles', [FundSettingsController::class, 'updateRules'])->name('fund-settings.rules.update');
    Route::post('/parametres-fonds/zones', [FundSettingsController::class, 'storeZone'])->name('fund-settings.zones.store');
    Route::put('/parametres-fonds/zones/{zone}', [FundSettingsController::class, 'updateZone'])->name('fund-settings.zones.update');
    Route::delete('/parametres-fonds/zones/{zone}', [FundSettingsController::class, 'destroyZone'])->name('fund-settings.zones.destroy');
    Route::post('/parametres-fonds/types-transactions', [FundSettingsController::class, 'storeTransactionType'])->name('fund-settings.transaction-types.store');
    Route::put('/parametres-fonds/types-transactions/{transactionType}', [FundSettingsController::class, 'updateTransactionType'])->name('fund-settings.transaction-types.update');
    Route::delete('/parametres-fonds/types-transactions/{transactionType}', [FundSettingsController::class, 'destroyTransactionType'])->name('fund-settings.transaction-types.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfilePhotoController::class, 'store'])->middleware('throttle:10,1')->name('profile.photo.store');
    Route::delete('/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
    Route::post('/members', [MemberController::class, 'store'])->name('members.store');
    Route::get('/members/{memberProfile}', [MemberController::class, 'show'])->name('members.show');
    Route::get('/members/{memberProfile}/edit', [MemberController::class, 'edit'])->name('members.edit');
    Route::put('/members/{memberProfile}', [MemberController::class, 'update'])->name('members.update');
    Route::patch('/members/{memberProfile}/suspend', [MemberController::class, 'suspend'])->name('members.suspend');
    Route::patch('/members/{memberProfile}/activate', [MemberController::class, 'activate'])->name('members.activate');
    Route::delete('/members/{memberProfile}', [MemberController::class, 'destroy'])->name('members.destroy');

    Route::get('/contributions/proofs/{paymentProof}/download', [ContributionController::class, 'downloadProof'])
        ->name('contributions.proofs.download');

    Route::get('/contributions', [ContributionController::class, 'index'])->name('contributions.index');
    Route::get('/contributions/create', [ContributionController::class, 'create'])->name('contributions.create');
    Route::post('/contributions', [ContributionController::class, 'store'])->name('contributions.store');
    Route::get('/contributions/{contributionPayment}', [ContributionController::class, 'show'])->name('contributions.show');
    Route::get('/contributions/{contributionPayment}/edit', [ContributionController::class, 'edit'])->name('contributions.edit');
    Route::put('/contributions/{contributionPayment}', [ContributionController::class, 'update'])->name('contributions.update');
    Route::patch('/contributions/{contributionPayment}/validate', [ContributionController::class, 'validatePayment'])->name('contributions.validate');
    Route::patch('/contributions/{contributionPayment}/reject', [ContributionController::class, 'rejectPayment'])->name('contributions.reject');
    Route::delete('/contributions/{contributionPayment}', [ContributionController::class, 'destroy'])->name('contributions.destroy');
    Route::post('/contributions/process-dues', [ContributionController::class, 'processDues'])->name('contributions.process-dues');
    Route::patch('/contributions/dues/{contributionDue}/waive', [ContributionController::class, 'waiveDue'])->name('contributions.dues.waive');

    Route::get('/contribution-plans', [ContributionPlanController::class, 'index'])->name('contribution-plans.index');
    Route::get('/contribution-plans/create', [ContributionPlanController::class, 'create'])->name('contribution-plans.create');
    Route::post('/contribution-plans', [ContributionPlanController::class, 'store'])->name('contribution-plans.store');
    Route::get('/contribution-plans/{contributionPlan}/edit', [ContributionPlanController::class, 'edit'])->name('contribution-plans.edit');
    Route::put('/contribution-plans/{contributionPlan}', [ContributionPlanController::class, 'update'])->name('contribution-plans.update');
    Route::patch('/contribution-plans/{contributionPlan}/activate', [ContributionPlanController::class, 'activate'])->name('contribution-plans.activate');
    Route::patch('/contribution-plans/{contributionPlan}/deactivate', [ContributionPlanController::class, 'deactivate'])->name('contribution-plans.deactivate');
    Route::delete('/contribution-plans/{contributionPlan}', [ContributionPlanController::class, 'destroy'])->name('contribution-plans.destroy');

    Route::get('/funds/movements/create', [FundController::class, 'createMovement'])->name('funds.movements.create');
    Route::get('/funds/movements', [FundController::class, 'movements'])->name('funds.movements');
    Route::post('/funds/movements', [FundController::class, 'storeMovement'])->name('funds.movements.store');
    Route::get('/funds/transfer', [FundController::class, 'transferForm'])->name('funds.transfer.form');
    Route::post('/funds/transfer', [FundController::class, 'transfer'])->name('funds.transfer');
    Route::patch('/funds/movements/{fundMovement}/cancel', [FundController::class, 'cancelMovement'])->name('funds.movements.cancel');
    Route::post('/funds/recalculate', [FundController::class, 'recalculate'])->name('funds.recalculate');
    Route::post('/funds/accounts', [FundController::class, 'storeAccount'])->name('funds.accounts.store');
    Route::put('/funds/accounts/{fundAccount}', [FundController::class, 'updateAccount'])->name('funds.accounts.update');
    Route::patch('/funds/accounts/{fundAccount}/toggle', [FundController::class, 'toggleAccount'])->name('funds.accounts.toggle');
    Route::post('/funds/examples', [FundController::class, 'storeExamples'])->name('funds.examples.store');
    Route::post('/funds/reset', [FundController::class, 'resetFunds'])->name('funds.reset');
    Route::post('/funds/accounts/{fundAccount}/adjust', [FundController::class, 'adjustAccount'])->name('funds.accounts.adjust');
    Route::delete('/funds/examples', [FundController::class, 'destroyExamples'])->name('funds.examples.destroy');
    Route::get('/funds/{fundAccount}', [FundController::class, 'show'])->name('funds.show');
    Route::get('/funds', [FundController::class, 'index'])->name('funds.index');

    Route::get('/fund-allocation-rules', [FundAllocationRuleController::class, 'index'])->name('fund-allocation-rules.index');
    Route::get('/fund-allocation-rules/create', [FundAllocationRuleController::class, 'create'])->name('fund-allocation-rules.create');
    Route::post('/fund-allocation-rules', [FundAllocationRuleController::class, 'store'])->name('fund-allocation-rules.store');
    Route::get('/fund-allocation-rules/{fundAllocationRule}/edit', [FundAllocationRuleController::class, 'edit'])->name('fund-allocation-rules.edit');
    Route::put('/fund-allocation-rules/{fundAllocationRule}', [FundAllocationRuleController::class, 'update'])->name('fund-allocation-rules.update');
    Route::patch('/fund-allocation-rules/{fundAllocationRule}/activate', [FundAllocationRuleController::class, 'activate'])->name('fund-allocation-rules.activate');
    Route::patch('/fund-allocation-rules/{fundAllocationRule}/deactivate', [FundAllocationRuleController::class, 'deactivate'])->name('fund-allocation-rules.deactivate');
    Route::patch('/fund-allocation-rules/{fundAllocationRule}/set-default', [FundAllocationRuleController::class, 'setDefault'])->name('fund-allocation-rules.set-default');

    Route::get('/projects/documents/{projectDocument}/download', [ProjectDocumentController::class, 'download'])
        ->name('projects.documents.download');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('/projects/{project}/submit', [ProjectController::class, 'submit'])->name('projects.submit');
    Route::post('/projects/{project}/evaluate', [ProjectController::class, 'evaluate'])->name('projects.evaluate');
    Route::patch('/projects/{project}/approve-by-committee', [ProjectController::class, 'approveByCommittee'])->name('projects.approve-by-committee');
    Route::patch('/projects/{project}/reject-by-committee', [ProjectController::class, 'rejectByCommittee'])->name('projects.reject-by-committee');
    Route::patch('/projects/{project}/submit-to-vote', [ProjectController::class, 'submitToVote'])->name('projects.submit-to-vote');
    Route::patch('/projects/{project}/fund', [ProjectController::class, 'fund'])->name('projects.fund');
    Route::patch('/projects/{project}/start', [ProjectController::class, 'start'])->name('projects.start');
    Route::patch('/projects/{project}/complete', [ProjectController::class, 'complete'])->name('projects.complete');
    Route::patch('/projects/{project}/fail', [ProjectController::class, 'fail'])->name('projects.fail');
    Route::patch('/projects/{project}/cancel-execution', [ProjectController::class, 'cancelExecution'])->name('projects.cancel-execution');
    Route::post('/projects/{project}/milestones', [ProjectController::class, 'storeMilestone'])->name('projects.milestones.store');
    Route::patch('/projects/milestones/{projectMilestone}', [ProjectController::class, 'updateMilestone'])->name('projects.milestones.update');
    Route::post('/projects/{project}/progress-reports', [ProjectController::class, 'storeProgressReport'])->name('projects.progress-reports.store');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::post('/projects/{project}/documents', [ProjectDocumentController::class, 'store'])->name('projects.documents.store');
    Route::delete('/projects/documents/{projectDocument}', [ProjectDocumentController::class, 'destroy'])->name('projects.documents.destroy');

    Route::get('/votes', [VoteController::class, 'index'])->name('votes.index');
    Route::get('/votes/create', [VoteController::class, 'create'])->name('votes.create');
    Route::post('/votes', [VoteController::class, 'store'])->name('votes.store');
    Route::get('/votes/{vote}', [VoteController::class, 'show'])->name('votes.show');
    Route::post('/votes/{vote}/cast', [VoteController::class, 'cast'])->name('votes.cast');
    Route::patch('/votes/{vote}/close', [VoteController::class, 'close'])->name('votes.close');
    Route::patch('/votes/{vote}/cancel', [VoteController::class, 'cancel'])->name('votes.cancel');
    Route::post('/votes/{vote}/conflicts', [VoteController::class, 'declareConflict'])->name('votes.conflicts.store');
    Route::patch('/votes/conflicts/{voteConflict}', [VoteController::class, 'resolveConflict'])->name('votes.conflicts.resolve');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents/upload', [DocumentController::class, 'upload'])->name('documents.upload');
    Route::patch('/documents/{document}/move', [DocumentController::class, 'move'])->name('documents.move');
    Route::post('/document-folders', [DocumentController::class, 'storeFolder'])->name('document-folders.store');
    Route::patch('/document-folders/{documentFolder}', [DocumentController::class, 'updateFolder'])->name('document-folders.update');
    Route::delete('/document-folders/{documentFolder}', [DocumentController::class, 'destroyFolder'])->name('document-folders.destroy');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'addVersion'])->name('documents.versions.store');
    Route::patch('/documents/{document}/submit-review', [DocumentController::class, 'submitReview'])->name('documents.submit-review');
    Route::patch('/documents/{document}/publish', [DocumentController::class, 'publish'])->name('documents.publish');
    Route::patch('/documents/{document}/constitution', [DocumentController::class, 'updateConstitution'])->name('documents.constitution');
    Route::patch('/documents/{document}/archive', [DocumentController::class, 'archive'])->name('documents.archive');
    Route::patch('/documents/{document}/restore', [DocumentController::class, 'restore'])->name('documents.restore');
    Route::put('/documents/{document}/access', [DocumentController::class, 'updateAccess'])->name('documents.access.update');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/versions/{documentVersion}/download', [DocumentController::class, 'download'])->name('documents.versions.download');
    Route::post('/document-categories', [DocumentController::class, 'storeCategory'])->name('document-categories.store');
    Route::patch('/document-categories/{documentCategory}/toggle', [DocumentController::class, 'toggleCategory'])->name('document-categories.toggle');

    Route::get('/administration/adhesions', [MembershipApplicationController::class, 'index'])->name('membership-applications.index');
    Route::patch('/administration/adhesions/{membershipApplication}', [MembershipApplicationController::class, 'decide'])->name('membership-applications.decide');

    Route::patch('/preferences/tableau-de-bord', [\App\Http\Controllers\PreferenceController::class, 'updateDashboard'])->name('preferences.dashboard');
    Route::get('/administration', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/administration/roles', [AdminController::class, 'storeRole'])->name('admin.roles.store');
    Route::put('/administration/roles/{role}', [AdminController::class, 'updateRole'])->name('admin.roles.update');
    Route::delete('/administration/roles/{role}', [AdminController::class, 'destroyRole'])->name('admin.roles.destroy');
    Route::patch('/administration/users/{user}/role', [AdminController::class, 'updateUserRole'])->name('admin.users.role');
    Route::post('/administration/permissions', [AdminController::class, 'storePermission'])->name('admin.permissions.store');
    Route::delete('/administration/permissions/{permission}', [AdminController::class, 'destroyPermission'])->name('admin.permissions.destroy');
    Route::put('/administration/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
    Route::post('/administration/currency', [AdminController::class, 'convertCurrency'])->name('admin.currency.convert');
});

require __DIR__.'/auth.php';
