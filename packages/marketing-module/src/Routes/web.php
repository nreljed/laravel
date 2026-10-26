<?php

use Illuminate\Support\Facades\Route;
use Nreljed\MarketingModule\Http\Controllers\AnalyticsController;
use Nreljed\MarketingModule\Http\Controllers\CampaignsController;
use Nreljed\MarketingModule\Http\Controllers\ContactsController;
use Nreljed\MarketingModule\Http\Controllers\MarketingBriefsController;
use Nreljed\MarketingModule\Http\Controllers\MarketingBroadcastsController;
use Nreljed\MarketingModule\Http\Controllers\MarketingCalendarController;
use Nreljed\MarketingModule\Http\Controllers\MarketingDashboardController;
use Nreljed\MarketingModule\Http\Controllers\MarketingExperimentsController;
use Nreljed\MarketingModule\Http\Controllers\MarketingInsightsController;
use Nreljed\MarketingModule\Http\Controllers\MarketingPlansController;
use Nreljed\MarketingModule\Http\Controllers\PostMediaController;
use Nreljed\MarketingModule\Http\Controllers\PostsController;
use Nreljed\MarketingModule\Http\Controllers\SegmentsController;
use Nreljed\MarketingModule\Http\Controllers\SocialAccountsController;
use Nreljed\MarketingModule\Http\Controllers\SocialAuthController;
use Nreljed\MarketingModule\Http\Controllers\SyncHealthController;
use Nreljed\MarketingModule\Http\Controllers\TagsController;
use Nreljed\MarketingModule\Http\Controllers\WorkflowsController;

// Dashboard
Route::get('/', [MarketingDashboardController::class, 'index'])->name('dashboard');

// Analytics
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');

// Sync health (operational view of metric collection and its dead letters)
Route::get('/sync-health', [SyncHealthController::class, 'index'])->name('sync-health.index');
Route::post('/sync-health/dead-letters/replay', [SyncHealthController::class, 'replay'])->name('sync-health.replay');
Route::post('/sync-health/dead-letters/{target}/replay', [SyncHealthController::class, 'replayTarget'])->name('sync-health.dead-letters.replay');

// Insights & strategy
Route::get('/insights', [MarketingInsightsController::class, 'index'])->name('insights.index');
Route::post('/insights/refresh', [MarketingInsightsController::class, 'refresh'])->name('insights.refresh');
Route::post('/insights/analyze', [MarketingInsightsController::class, 'analyze'])->name('insights.analyze');
Route::post('/insights/{insight}/apply', [MarketingInsightsController::class, 'apply'])->name('insights.apply');

// Plans
Route::get('/plans', [MarketingPlansController::class, 'index'])->name('plans.index');
Route::get('/plans/create', [MarketingPlansController::class, 'create'])->name('plans.create');
Route::post('/plans', [MarketingPlansController::class, 'store'])->name('plans.store');
Route::post('/plans/generate', [MarketingPlansController::class, 'generate'])->name('plans.generate');
Route::get('/plans/{record}/edit', [MarketingPlansController::class, 'edit'])->name('plans.edit');
Route::put('/plans/{record}', [MarketingPlansController::class, 'update'])->name('plans.update');
Route::delete('/plans/{record}', [MarketingPlansController::class, 'destroy'])->name('plans.destroy');

// Briefs
Route::get('/briefs', [MarketingBriefsController::class, 'index'])->name('briefs.index');
Route::get('/briefs/create', [MarketingBriefsController::class, 'create'])->name('briefs.create');
Route::post('/briefs', [MarketingBriefsController::class, 'store'])->name('briefs.store');
Route::get('/briefs/{record}/edit', [MarketingBriefsController::class, 'edit'])->name('briefs.edit');
Route::put('/briefs/{record}', [MarketingBriefsController::class, 'update'])->name('briefs.update');
Route::delete('/briefs/{record}', [MarketingBriefsController::class, 'destroy'])->name('briefs.destroy');

// Broadcasts
Route::get('/broadcasts', [MarketingBroadcastsController::class, 'index'])->name('broadcasts.index');
Route::get('/broadcasts/create', [MarketingBroadcastsController::class, 'create'])->name('broadcasts.create');
Route::post('/broadcasts', [MarketingBroadcastsController::class, 'store'])->name('broadcasts.store');
Route::get('/broadcasts/{record}/edit', [MarketingBroadcastsController::class, 'edit'])->name('broadcasts.edit');
Route::put('/broadcasts/{record}', [MarketingBroadcastsController::class, 'update'])->name('broadcasts.update');
Route::delete('/broadcasts/{record}', [MarketingBroadcastsController::class, 'destroy'])->name('broadcasts.destroy');
Route::post('/broadcasts/{record}/send', [MarketingBroadcastsController::class, 'send'])->name('broadcasts.send');

// Experiments
Route::get('/experiments', [MarketingExperimentsController::class, 'index'])->name('experiments.index');
Route::get('/experiments/create', [MarketingExperimentsController::class, 'create'])->name('experiments.create');
Route::post('/experiments', [MarketingExperimentsController::class, 'store'])->name('experiments.store');
Route::get('/experiments/{record}/edit', [MarketingExperimentsController::class, 'edit'])->name('experiments.edit');
Route::put('/experiments/{record}', [MarketingExperimentsController::class, 'update'])->name('experiments.update');
Route::delete('/experiments/{record}', [MarketingExperimentsController::class, 'destroy'])->name('experiments.destroy');

// Calendar
Route::get('/calendar', [MarketingCalendarController::class, 'index'])->name('calendar.index');

// Social accounts.
//
// The literal `connect` and `callback` segments are declared before the
// record-scoped routes so a platform key is never mistaken for a record id.
Route::get('/accounts', [SocialAccountsController::class, 'index'])->name('accounts.index');
Route::get('/accounts/connect/{platform}', [SocialAuthController::class, 'connect'])->name('accounts.connect');
Route::get('/accounts/callback/{platform}', [SocialAuthController::class, 'callback'])->name('accounts.callback');
Route::get('/accounts/{record}/edit', [SocialAccountsController::class, 'edit'])->name('accounts.edit');
Route::put('/accounts/{record}', [SocialAccountsController::class, 'update'])->name('accounts.update');
Route::delete('/accounts/{record}', [SocialAccountsController::class, 'destroy'])->name('accounts.destroy');

// Posts
//
// `account-options` feeds the composer's account picker, so it is declared
// before the record-scoped routes for the same reason `connect` is above.
Route::get('/posts', [PostsController::class, 'index'])->name('posts.index');
Route::get('/posts/create', [PostsController::class, 'create'])->name('posts.create');
Route::get('/posts/account-options', [PostsController::class, 'accountOptions'])->name('posts.account-options');
Route::post('/posts/media', [PostMediaController::class, 'upload'])->name('posts.media.upload');
Route::delete('/posts/media', [PostMediaController::class, 'destroy'])->name('posts.media.destroy');
Route::post('/posts', [PostsController::class, 'store'])->name('posts.store');
Route::get('/posts/{record}/edit', [PostsController::class, 'edit'])->name('posts.edit');
Route::put('/posts/{record}', [PostsController::class, 'update'])->name('posts.update');
Route::delete('/posts/{record}', [PostsController::class, 'destroy'])->name('posts.destroy');
Route::post('/posts/{record}/publish', [PostsController::class, 'publish'])->name('posts.publish');
Route::post('/posts/{record}/approve', [PostsController::class, 'approve'])->name('posts.approve');

// Contacts
Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts.index');
Route::get('/contacts/create', [ContactsController::class, 'create'])->name('contacts.create');
Route::post('/contacts', [ContactsController::class, 'store'])->name('contacts.store');
Route::get('/contacts/{record}/edit', [ContactsController::class, 'edit'])->name('contacts.edit');
Route::put('/contacts/{record}', [ContactsController::class, 'update'])->name('contacts.update');
Route::delete('/contacts/{record}', [ContactsController::class, 'destroy'])->name('contacts.destroy');

// Tags
Route::get('/tags', [TagsController::class, 'index'])->name('tags.index');
Route::get('/tags/create', [TagsController::class, 'create'])->name('tags.create');
Route::post('/tags', [TagsController::class, 'store'])->name('tags.store');
Route::get('/tags/{record}/edit', [TagsController::class, 'edit'])->name('tags.edit');
Route::put('/tags/{record}', [TagsController::class, 'update'])->name('tags.update');
Route::delete('/tags/{record}', [TagsController::class, 'destroy'])->name('tags.destroy');

// Segments
Route::get('/segments', [SegmentsController::class, 'index'])->name('segments.index');
Route::get('/segments/create', [SegmentsController::class, 'create'])->name('segments.create');
Route::post('/segments', [SegmentsController::class, 'store'])->name('segments.store');
Route::get('/segments/{record}/edit', [SegmentsController::class, 'edit'])->name('segments.edit');
Route::put('/segments/{record}', [SegmentsController::class, 'update'])->name('segments.update');
Route::delete('/segments/{record}', [SegmentsController::class, 'destroy'])->name('segments.destroy');

// Campaigns
//
// `options` feeds the composer's campaign picker, so it stays ahead of the
// record-scoped routes.
Route::get('/campaigns/options', [CampaignsController::class, 'options'])->name('campaigns.options');
Route::get('/campaigns', [CampaignsController::class, 'index'])->name('campaigns.index');
Route::get('/campaigns/create', [CampaignsController::class, 'create'])->name('campaigns.create');
Route::post('/campaigns', [CampaignsController::class, 'store'])->name('campaigns.store');
Route::get('/campaigns/{record}/edit', [CampaignsController::class, 'edit'])->name('campaigns.edit');
Route::put('/campaigns/{record}', [CampaignsController::class, 'update'])->name('campaigns.update');
Route::delete('/campaigns/{record}', [CampaignsController::class, 'destroy'])->name('campaigns.destroy');

// Workflows
Route::get('/workflows/tag-options', [WorkflowsController::class, 'tagOptions'])->name('workflows.tag-options');
Route::get('/workflows', [WorkflowsController::class, 'index'])->name('workflows.index');
Route::get('/workflows/create', [WorkflowsController::class, 'create'])->name('workflows.create');
Route::post('/workflows', [WorkflowsController::class, 'store'])->name('workflows.store');
Route::get('/workflows/{record}/builder', [WorkflowsController::class, 'builder'])->name('workflows.builder');
Route::get('/workflows/{record}/edit', [WorkflowsController::class, 'edit'])->name('workflows.edit');
Route::put('/workflows/{record}', [WorkflowsController::class, 'update'])->name('workflows.update');
Route::delete('/workflows/{record}', [WorkflowsController::class, 'destroy'])->name('workflows.destroy');
