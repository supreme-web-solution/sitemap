<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminTeamController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Discover\NicheResearchController;
use App\Http\Controllers\Invoices\InvoiceActionController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Invoices\InvoicePayController;
use App\Http\Controllers\Invoices\RecurringInvoiceController;
use App\Http\Controllers\Leads\CompetitorAuditController;
use App\Http\Controllers\Leads\LeadActivityController;
use App\Http\Controllers\Leads\LeadAuditController;
use App\Http\Controllers\Leads\LeadBoardController;
use App\Http\Controllers\Leads\LeadController;
use App\Http\Controllers\Leads\LeadExportController;
use App\Http\Controllers\Leads\LeadImportController;
use App\Http\Controllers\Leads\LeadOutreachController;
use App\Http\Controllers\Leads\LeadProfileController;
use App\Http\Controllers\Leads\LeadReviewController;
use App\Http\Controllers\Leads\LeadSearchController;
use App\Http\Controllers\Leads\LeadTaskController;
use App\Http\Controllers\Leads\SequenceEnrollmentController;
use App\Http\Controllers\Messaging\LeadConsentController;
use App\Http\Controllers\Messaging\LeadMessageController;
use App\Http\Controllers\Messaging\MessagingAccountController;
use App\Http\Controllers\Messaging\ZernioWebhookController;
use App\Http\Controllers\Outreach\EmailTemplateController;
use App\Http\Controllers\Outreach\FollowUpSequenceController;
use App\Http\Controllers\Payments\FlutterwaveWebhookController;
use App\Http\Controllers\Payments\PaymentAccountController;
use App\Http\Controllers\Payments\PayPalWebhookController;
use App\Http\Controllers\Payments\PaystackWebhookController;
use App\Http\Controllers\Payments\StripeWebhookController;
use App\Http\Controllers\Portal\ClientPortalController;
use App\Http\Controllers\Portal\LeadPortalController;
use App\Http\Controllers\Proposals\PackageController;
use App\Http\Controllers\Proposals\ProposalActionController;
use App\Http\Controllers\Proposals\ProposalController;
use App\Http\Controllers\Proposals\ProposalPublicController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Sites\ConceptPreviewController;
use App\Http\Controllers\Sites\DomainAllowedController;
use App\Http\Controllers\Sites\LeadSiteController;
use App\Http\Controllers\Sites\PreviewCommentController;
use App\Http\Controllers\Sites\PreviewPackageController;
use App\Http\Controllers\Sites\PreviewUnlockController;
use App\Http\Controllers\Sites\SiteChatController;
use App\Http\Controllers\Sites\SiteConceptController;
use App\Http\Controllers\Sites\SiteController;
use App\Http\Controllers\Sites\SiteDomainController;
use App\Http\Controllers\Sites\SiteExportController;
use App\Http\Controllers\Sites\SiteHealthController;
use App\Http\Controllers\Sites\SitePostController;
use App\Http\Controllers\Sites\SitePreviewController;
use App\Http\Controllers\Sites\SitePreviewInterestController;
use App\Http\Controllers\Sites\SitePreviewSettingsController;
use App\Http\Controllers\Sites\SitePublishController;
use App\Http\Controllers\Sites\SiteTransferController;
use App\Http\Controllers\Sites\SiteVersionController;
use App\Http\Controllers\Teams\BrandingController;
use App\Http\Controllers\Teams\IdealCustomerController;
use App\Http\Controllers\Teams\PlanUsageController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('p/concept/{siteConcept}', ConceptPreviewController::class)->name('sites.concepts.preview');
Route::get('p/{site:slug}/{path?}', SitePreviewController::class)->where('path', '.*')->name('sites.preview');
Route::get('internal/domains/allowed', DomainAllowedController::class)->name('domains.allowed');
Route::post('p/{site:slug}/interest', SitePreviewInterestController::class)
    ->middleware('throttle:preview-interest')
    ->name('sites.preview.interest');
Route::post('p/{site:slug}/unlock', [PreviewUnlockController::class, 'store'])
    ->middleware('throttle:preview-interest')
    ->name('sites.preview.unlock');
Route::post('p/{site:slug}/comments', [PreviewCommentController::class, 'store'])
    ->middleware('throttle:preview-interest')
    ->name('sites.preview.comments');
Route::post('p/{site:slug}/packages', [PreviewPackageController::class, 'store'])
    ->middleware('throttle:preview-interest')
    ->name('sites.preview.packages');

Route::get('i/{invoice:public_token}', [InvoicePayController::class, 'show'])->name('invoices.public');
Route::post('i/{invoice:public_token}/pay/{provider}', [InvoicePayController::class, 'pay'])
    ->middleware('throttle:invoice-payments')
    ->name('invoices.public.pay');
Route::get('i/{invoice:public_token}/return/{provider}', [InvoicePayController::class, 'complete'])
    ->middleware('throttle:invoice-payments')
    ->name('invoices.public.return');
Route::get('q/{proposal:public_token}', [ProposalPublicController::class, 'show'])->name('proposals.public');
Route::post('q/{proposal:public_token}/accept', [ProposalPublicController::class, 'accept'])
    ->middleware('throttle:invoice-payments')
    ->name('proposals.public.accept');
Route::post('q/{proposal:public_token}/decline', [ProposalPublicController::class, 'decline'])
    ->middleware('throttle:invoice-payments')
    ->name('proposals.public.decline');

Route::get('c/{lead:portal_token}', [ClientPortalController::class, 'show'])->name('portal.show');
Route::post('c/{lead:portal_token}/approve', [ClientPortalController::class, 'approve'])
    ->middleware('throttle:invoice-payments')
    ->name('portal.approve');
Route::post('c/{lead:portal_token}/feedback', [ClientPortalController::class, 'feedback'])
    ->middleware('throttle:invoice-payments')
    ->name('portal.feedback');
Route::post('webhooks/stripe/{key}', StripeWebhookController::class)->name('webhooks.stripe');
Route::post('webhooks/paypal/{key}', PayPalWebhookController::class)->name('webhooks.paypal');
Route::post('webhooks/paystack/{key}', PaystackWebhookController::class)->name('webhooks.paystack');
Route::post('webhooks/flutterwave/{key}', FlutterwaveWebhookController::class)->name('webhooks.flutterwave');
Route::post('webhooks/zernio/{key}', ZernioWebhookController::class)->name('webhooks.zernio');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', EnsureUserIsAdmin::class])
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::delete('users/{user}/suspend', [AdminUserController::class, 'restore'])->name('users.restore');
        Route::post('users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');

        Route::get('teams', [AdminTeamController::class, 'index'])->name('teams.index');
        Route::put('teams/{team}', [AdminTeamController::class, 'update'])->name('teams.update');

        Route::get('plans', [AdminPlanController::class, 'index'])->name('plans.index');
        Route::post('plans', [AdminPlanController::class, 'store'])->name('plans.store');
        Route::put('plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');
        Route::delete('plans/{plan}', [AdminPlanController::class, 'destroy'])->name('plans.destroy');
    });

Route::post('impersonation/stop', [ImpersonationController::class, 'stop'])->middleware('auth')->name('impersonation.stop');
Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/board', LeadBoardController::class)->name('leads.board');
        Route::get('leads/export', LeadExportController::class)->name('leads.export');
        Route::patch('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::get('leads/{lead}/edit', [LeadProfileController::class, 'edit'])->name('leads.edit');
        Route::put('leads/{lead}/profile', [LeadProfileController::class, 'update'])->name('leads.profile.update');
        Route::post('lead-searches', [LeadSearchController::class, 'store'])
            ->middleware('throttle:lead-searches')
            ->name('lead-searches.store');
        Route::post('lead-imports', [LeadImportController::class, 'store'])
            ->middleware('throttle:lead-searches')
            ->name('lead-imports.store');

        Route::post('leads/{lead}/audit', [LeadAuditController::class, 'store'])
            ->middleware('throttle:lead-audits')
            ->name('leads.audit.store');
        Route::post('leads/{lead}/outreach', [LeadOutreachController::class, 'store'])
            ->middleware('throttle:outreach')
            ->name('leads.outreach.store');
        Route::post('leads/{lead}/reviews', [LeadReviewController::class, 'store'])
            ->middleware('throttle:review-fetches')
            ->name('leads.reviews.store');
        Route::patch('leads/{lead}/reviews/{review}', [LeadReviewController::class, 'update'])->name('leads.reviews.update');
        Route::post('leads/{lead}/notes', [LeadActivityController::class, 'store'])->name('leads.notes.store');

        Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::post('email-templates', [EmailTemplateController::class, 'store'])->name('email-templates.store');
        Route::put('email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::delete('email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->name('email-templates.destroy');

        Route::get('sequences', [FollowUpSequenceController::class, 'index'])->name('sequences.index');
        Route::post('sequences', [FollowUpSequenceController::class, 'store'])->name('sequences.store');
        Route::put('sequences/{followUpSequence}', [FollowUpSequenceController::class, 'update'])->name('sequences.update');
        Route::delete('sequences/{followUpSequence}', [FollowUpSequenceController::class, 'destroy'])->name('sequences.destroy');

        Route::post('leads/{lead}/enrollments', [SequenceEnrollmentController::class, 'store'])->name('leads.enrollments.store');
        Route::delete('leads/{lead}/enrollments/{enrollment}', [SequenceEnrollmentController::class, 'destroy'])->name('leads.enrollments.destroy');
        Route::post('leads/{lead}/tasks', [LeadTaskController::class, 'store'])->name('leads.tasks.store');
        Route::patch('leads/{lead}/tasks/{task}', [LeadTaskController::class, 'update'])->name('leads.tasks.update');
        Route::delete('leads/{lead}/tasks/{task}', [LeadTaskController::class, 'destroy'])->name('leads.tasks.destroy');
        Route::post('leads/{lead}/competitor-audit', [CompetitorAuditController::class, 'store'])
            ->middleware('throttle:lead-audits')
            ->name('leads.competitor-audit.store');

        Route::get('reports', ReportController::class)->name('reports.index');
        Route::get('research', [NicheResearchController::class, 'index'])->name('research.index');
        Route::post('research', [NicheResearchController::class, 'store'])->name('research.store');
        Route::get('ideal-customer', [IdealCustomerController::class, 'edit'])->name('ideal-customer.edit');
        Route::put('ideal-customer', [IdealCustomerController::class, 'update'])->name('ideal-customer.update');

        Route::post('leads/{lead}/site', [LeadSiteController::class, 'store'])
            ->middleware('throttle:site-generation')
            ->name('leads.site.store');
        Route::post('leads/{lead}/concepts', [SiteConceptController::class, 'store'])
            ->middleware('throttle:site-generation')
            ->name('leads.concepts.store');
        Route::post('leads/{lead}/concepts/{concept}/choose', [SiteConceptController::class, 'choose'])
            ->name('leads.concepts.choose');

        Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
        Route::get('sites/{site}/edit', [SiteController::class, 'edit'])->name('sites.edit');
        Route::put('sites/{site}', [SiteController::class, 'update'])->name('sites.update');
        Route::get('sites/{site}/export', SiteExportController::class)->name('sites.export');
        Route::post('sites/{site}/posts', [SitePostController::class, 'store'])->name('sites.posts.store');
        Route::put('sites/{site}/posts/{post}', [SitePostController::class, 'update'])->name('sites.posts.update');
        Route::delete('sites/{site}/posts/{post}', [SitePostController::class, 'destroy'])->name('sites.posts.destroy');
        Route::post('sites/{site}/domains', [SiteDomainController::class, 'store'])->name('sites.domains.store');
        Route::post('sites/{site}/domains/{domain}/verify', [SiteDomainController::class, 'verify'])->name('sites.domains.verify');
        Route::delete('sites/{site}/domains/{domain}', [SiteDomainController::class, 'destroy'])->name('sites.domains.destroy');
        Route::post('sites/{site}/publish', [SitePublishController::class, 'store'])->name('sites.publish.store');
        Route::delete('sites/{site}/publish', [SitePublishController::class, 'destroy'])->name('sites.publish.destroy');
        Route::post('sites/{site}/versions/{version}/restore', [SiteVersionController::class, 'restore'])->name('sites.versions.restore');
        Route::post('sites/{site}/chat', [SiteChatController::class, 'store'])
            ->middleware('throttle:site-generation')
            ->name('sites.chat.store');
        Route::put('sites/{site}/preview-access', [SitePreviewSettingsController::class, 'update'])->name('sites.preview-access.update');
        Route::post('sites/{site}/health', [SiteHealthController::class, 'store'])->name('sites.health.store');
        Route::post('sites/{site}/transfer', [SiteTransferController::class, 'store'])->name('sites.transfer.store');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('invoices/{invoice}/send', [InvoiceActionController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/paid', [InvoiceActionController::class, 'markPaid'])->name('invoices.paid');
        Route::post('invoices/{invoice}/void', [InvoiceActionController::class, 'void'])->name('invoices.void');
        Route::post('invoices/{invoice}/refund', [InvoiceActionController::class, 'refund'])->name('invoices.refund');

        Route::get('recurring-invoices', [RecurringInvoiceController::class, 'index'])->name('recurring-invoices.index');
        Route::post('recurring-invoices', [RecurringInvoiceController::class, 'store'])->name('recurring-invoices.store');
        Route::put('recurring-invoices/{recurringInvoice}', [RecurringInvoiceController::class, 'update'])->name('recurring-invoices.update');
        Route::post('recurring-invoices/{recurringInvoice}/toggle', [RecurringInvoiceController::class, 'toggle'])->name('recurring-invoices.toggle');
        Route::delete('recurring-invoices/{recurringInvoice}', [RecurringInvoiceController::class, 'destroy'])->name('recurring-invoices.destroy');

        Route::get('payments', [PaymentAccountController::class, 'index'])->name('payments.index');
        Route::put('payments/{provider}', [PaymentAccountController::class, 'store'])->name('payments.connect');
        Route::delete('payments/{provider}', [PaymentAccountController::class, 'destroy'])->name('payments.disconnect');
        Route::get('messaging', [MessagingAccountController::class, 'index'])->name('messaging.index');
        Route::put('messaging', [MessagingAccountController::class, 'store'])->name('messaging.store');
        Route::delete('messaging', [MessagingAccountController::class, 'destroy'])->name('messaging.destroy');
        Route::post('leads/{lead}/messages', [LeadMessageController::class, 'store'])->middleware('throttle:outreach')->name('leads.messages.store');
        Route::put('leads/{lead}/messaging-consent', [LeadConsentController::class, 'store'])->name('leads.consent.store');
        Route::delete('leads/{lead}/messaging-consent', [LeadConsentController::class, 'destroy'])->name('leads.consent.destroy');

        Route::get('plan', PlanUsageController::class)->name('plan.index');
        Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
        Route::put('branding', [BrandingController::class, 'update'])->name('branding.update');

        Route::post('leads/{lead}/portal', [LeadPortalController::class, 'store'])->name('leads.portal.store');
        Route::delete('leads/{lead}/portal', [LeadPortalController::class, 'destroy'])->name('leads.portal.destroy');

        Route::get('packages', [PackageController::class, 'index'])->name('packages.index');
        Route::post('packages', [PackageController::class, 'store'])->name('packages.store');
        Route::put('packages/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::delete('packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');

        Route::get('proposals', [ProposalController::class, 'index'])->name('proposals.index');
        Route::get('proposals/create', [ProposalController::class, 'create'])->name('proposals.create');
        Route::post('proposals', [ProposalController::class, 'store'])->name('proposals.store');
        Route::get('proposals/{proposal}/edit', [ProposalController::class, 'edit'])->name('proposals.edit');
        Route::put('proposals/{proposal}', [ProposalController::class, 'update'])->name('proposals.update');
        Route::delete('proposals/{proposal}', [ProposalController::class, 'destroy'])->name('proposals.destroy');
        Route::post('proposals/{proposal}/send', [ProposalActionController::class, 'send'])->name('proposals.send');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
