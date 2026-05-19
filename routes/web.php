<?php

use App\Http\Controllers\PlanOrderInvoiceController;
use App\Http\Controllers\PricingCheckoutController;
use App\Http\Controllers\SslCommerzController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Client guest routes
|--------------------------------------------------------------------------
*/

Route::livewire('/', 'pages::client.home')->name('home');

// Services
Route::livewire('/services', 'pages::client.services.index')->name('client.services');
Route::livewire('/services/{slug}', 'pages::client.services.details')->name('client.services.details');

// projects
Route::livewire('/projects', 'pages::client.projects.index')->name('client.projects');
Route::livewire('/projects/{slug}', 'pages::client.projects.details')->name('client.projects.details');

// Tools
Route::livewire('/tools', 'pages::client.tools.index')->name('client.tools.index');

// Blogs
Route::livewire('/blogs', 'pages::client.blogs.index')->name('client.blogs');
Route::livewire('/blogs/{slug}', 'pages::client.blogs.details')->name('client.blogs.details');

// About
Route::livewire('/about', 'pages::client.about')->name('client.about');

// Contact
Route::livewire('/contact', 'pages::client.contact')->name('client.contact');


// Pricing and orders
Route::livewire('/checkout/pricing/{pricingPlan}', 'pages::client.checkout.pricing-checkout')->name('client.checkout.pricing');

// Legal Pages
Route::livewire('/terms-and-conditions', 'pages::client.legal-pages.terms-conditions')->name('client.terms-conditions');
Route::livewire('/privacy-policy', 'pages::client.legal-pages.privacy-policy')->name('client.privacy-policy');



/*
|--------------------------------------------------------------------------
| Email verification
|--------------------------------------------------------------------------
*/

Route::livewire('/email/verify', 'pages::client.auth.verify-email')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (Request $request, string $id, string $hash) {
    $user = User::findOrFail($id);

    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        abort(403);
    }

    if (! $request->hasValidSignature()) {
        abort(403);
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }

    return redirect()->route('verified.success');
})->middleware('signed')->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    if (! auth()->check()) {
        return redirect()->route('home')->with('auth_error', 'Please login first to resend the verification email.');
    }

    if ($request->user()->hasVerifiedEmail()) {
        return redirect()->route('verified.success');
    }

    $request->user()->sendEmailVerificationNotification();

    return back()->with('auth_success', 'A fresh verification link has been sent to your email address.');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::livewire('/verified-success', 'pages::client.auth.verified-success')->name('verified.success');

/*
|--------------------------------------------------------------------------
| Reset Password
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {

    // Client password reset
    Route::livewire('/reset-password/{token}', 'pages::client.auth.reset-password')
        ->name('password.reset');

    // Admin password reset
    Route::livewire('/admin/reset-password/{token}', 'pages::admin.auth.reset-password')
        ->name('admin.password.reset');
});

/*
|--------------------------------------------------------------------------
| Client protected routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:client,admin'])->group(function () {
    Route::livewire('account/dashboard', 'pages::client.account.dashboard')->name('account.dashboard');

    Route::livewire('account/services', 'pages::client.account.services')->name('account.services');
    Route::livewire('account/profile', 'pages::client.account.profile')->name('account.profile');
    Route::livewire('account/change-password', 'pages::client.account.change-password')->name('account.change-password');

    // Checkout function for monthly
    Route::post('/checkout/pricing/{pricingPlan}/pay', [SslCommerzController::class, 'pay'])->name('client.checkout.pricing.pay');

    // Booking for yearly
    Route::post('/checkout/pricing/{pricingPlan}/booking', [PricingCheckoutController::class, 'booking'])
        ->name('client.checkout.pricing.booking');

    // Order success page
    Route::livewire('/checkout/success/{order}', 'pages::client.checkout.checkout-success')->name('client.checkout.success');

    // Invoice download
    Route::get('/success/{order}/invoice/download', [PlanOrderInvoiceController::class, 'download'])->name('success.invoice.download');

    // Support tickets
    Route::livewire('/support/tickets', 'pages::client.tickets.index')->name('client.tickets.index');
    Route::livewire('/support/tickets/{ticket}/show', 'pages::client.tickets.show')->name('client.tickets.show');

    // Proposal
    Route::livewire('/account/proposals', 'pages::client.proposals.index')->name('client.proposals.index');
    Route::livewire('/account/proposals/{proposal}', 'pages::client.proposals.show')->name('client.proposals.show');
});

Route::match(['get', 'post'], '/sslcommerz/success', [SslCommerzController::class, 'success'])
    ->name('sslcommerz.success');

Route::match(['get', 'post'], '/sslcommerz/fail', [SslCommerzController::class, 'fail'])
    ->name('sslcommerz.fail');

Route::match(['get', 'post'], '/sslcommerz/cancel', [SslCommerzController::class, 'cancel'])
    ->name('sslcommerz.cancel');

Route::match(['get', 'post'], '/sslcommerz/ipn', [SslCommerzController::class, 'ipn'])
    ->name('sslcommerz.ipn');

/*
|--------------------------------------------------------------------------
| Admin guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {

    Route::livewire('/admin/login', 'pages::admin.auth.login')->name('admin.login');
    Route::livewire('/admin/forgot-password', 'pages::admin.auth.forgot-password')
        ->name('admin.password.request');

    Route::livewire('/admin/reset-password/{token}', 'pages::admin.auth.reset-password')
        ->name('admin.password.reset');
});

/*
|--------------------------------------------------------------------------
| Admin protected routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,manager,staff,admin_manager'])->group(function () {

    Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');

    // User management
    Route::livewire('/users', 'pages::admin.users.index')->name('users.index');
    Route::livewire('/users/create', 'pages::admin.users.create')->name('users.create');
    Route::livewire('/users/{user}/edit', 'pages::admin.users.edit')->name('users.edit');

    // Department management
    Route::livewire('/departments', 'pages::admin.departments.index')->name('departments.index');
    Route::livewire('/departments/create', 'pages::admin.departments.create')->name('departments.create');
    Route::livewire('/departments/{department}/edit', 'pages::admin.departments.edit')->name('departments.edit');

    // Service management
    Route::livewire('/services', 'pages::admin.services.index')->name('services.index');
    Route::livewire('/services/create', 'pages::admin.services.create')->name('services.create');
    Route::livewire('/services/{service}/edit', 'pages::admin.services.edit')->name('services.edit');

    // Pricing management
    Route::livewire('/pricing', 'pages::admin.pricing.index')->name('pricing.index');

    // Company Logo management
    Route::livewire('/company-logos', 'pages::admin.company-logos.index')->name('company-logos.index');
    Route::livewire('/company-logos/create', 'pages::admin.company-logos.create')->name('company-logos.create');
    Route::livewire('/company-logos/{companyLogo}/edit', 'pages::admin.company-logos.edit')->name('company-logos.edit');

    // Category management
    Route::livewire('/categories', 'pages::admin.categories.index')->name('categories.index');
    Route::livewire('/categories/create', 'pages::admin.categories.create')->name('categories.create');
    Route::livewire('/categories/{category}/edit', 'pages::admin.categories.edit')->name('categories.edit');

    // Project management
    Route::livewire('/projects', 'pages::admin.projects.index')->name('projects.index');
    Route::livewire('/projects/create', 'pages::admin.projects.create')->name('projects.create');
    Route::livewire('/projects/{project}/edit', 'pages::admin.projects.edit')->name('projects.edit');

    // Blog management
    Route::livewire('/blogs', 'pages::admin.blogs.index')->name('blogs.index');
    Route::livewire('/blogs/create', 'pages::admin.blogs.create')->name('blogs.create');
    Route::livewire('/blogs/{blog}/edit', 'pages::admin.blogs.edit')->name('blogs.edit');

    // Service plan management
    Route::livewire('/service-plans', 'pages::admin.service-plans.index')->name('service-plans.index');
    Route::livewire('/service-plans/create', 'pages::admin.service-plans.create')->name('service-plans.create');
    Route::livewire('/service-plans/{servicePlan}/edit', 'pages::admin.service-plans.edit')->name('service-plans.edit');

    // Proposal management
    Route::livewire('/proposals', 'pages::admin.proposals.index')->name('proposals.index');
    Route::livewire('/proposals/create', 'pages::admin.proposals.create')->name('proposals.create');
    Route::livewire('/proposals/{proposal}/edit', 'pages::admin.proposals.edit')->name('proposals.edit');

    // Settings
    Route::livewire('/site-settings', 'pages::admin.settings.site-setting')->name('settings.site-setting');
    Route::livewire('/invoice-templates', 'pages::admin.settings.invoice-template')->name('settings.invoice-templates');
    Route::livewire('/proposal-templates', 'pages::admin.settings.proposal-template')->name('settings.proposal-templates');

    // Order management
    Route::livewire('/orders', 'pages::admin.orders.index')->name('orders.index');
    Route::livewire('/orders/{order}/edit', 'pages::admin.orders.edit')->name('orders.edit');

    //Invoice management
    Route::get('/orders/{order}/invoice/download', [PlanOrderInvoiceController::class, 'download'])->name('orders.invoice.download');

    // Ticket management
    Route::livewire('/tickets', 'pages::admin.tickets.index')->name('tickets.index');
    Route::livewire('/tickets/{ticket}/show', 'pages::admin.tickets.show')->name('tickets.show');

    // Contact messages
    Route::livewire('/contact-messages', 'pages::admin.contact-messages.index')->name('contact-messages.index');

    // Services Management
    Route::livewire('/assigned-services', 'pages::admin.assigned-services.index')->name('assigned-services.index');
    Route::livewire('/assigned-services/create', 'pages::admin.assigned-services.create')->name('assigned-services.create');
    Route::livewire('/assigned-services/{userService}/edit', 'pages::admin.assigned-services.edit')->name('assigned-services.edit');

    // Bookings
    Route::livewire('/bookings', 'pages::admin.bookings.index')->name('bookings.index');
    Route::livewire('/bookings/{booking}/quote', 'pages::admin.bookings.quote')->name('bookings.quote');


    // Icons
    Route::livewire('/icons', 'pages::admin.icons.material-icons')->name('icons.material-icons');
});
