<?php

use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\AppController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OperationsController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\VoiceController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/voice')->middleware(['auth:sanctum', 'tenant', 'voice.ability', 'throttle:120,1'])->group(function () {
    Route::post('customers/register', [VoiceController::class, 'register'])->middleware('idempotent');
    Route::post('orders', [VoiceController::class, 'store'])->middleware(['idempotent', 'plan.capacity:orders_per_month']);
    Route::post('orders/{order}/measurements', [VoiceController::class, 'measurement'])->middleware('idempotent');
    Route::post('orders/{order}/assign', [VoiceController::class, 'assign'])->middleware('idempotent');
    Route::patch('orders/{order}/status', [VoiceController::class, 'status'])->middleware('idempotent');
    Route::get('search', [VoiceController::class, 'search']);
});

Route::get('v1/health', [HealthController::class, 'health'])->middleware('throttle:60,1');
Route::get('v1/metrics', [HealthController::class, 'metrics'])->middleware('throttle:30,1');
Route::post('v1/client-errors', [HealthController::class, 'clientError'])->middleware('throttle:20,1');
Route::get('v1/auth/verify-email/{user}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:20,1'])->name('verification.verify');
Route::post('v1/auth/email/resend', [AuthController::class, 'resendVerification'])->middleware(['auth:sanctum', 'throttle:3,1']);
Route::post('v1/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('v1/auth/logout', [AuthController::class, 'logout']);
Route::post('v1/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('v1/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::post('v1/onboarding', [PlatformController::class, 'register'])->middleware('throttle:5,1');
Route::get('v1/plans', [PlatformController::class, 'plans']);
Route::middleware(['auth:sanctum', 'tenant'])->prefix('v1')->group(function () {
    $can = fn (string ...$p) => 'permission:'.implode(',', $p);

    Route::get('auth/me', [AuthController::class, 'me']);
    Route::get('auth/two-factor', [AuthController::class, 'twoFactorStatus']);
    Route::post('auth/two-factor/setup', [AuthController::class, 'setupTwoFactor']);
    Route::post('auth/two-factor/confirm', [AuthController::class, 'confirmTwoFactor']);
    Route::delete('auth/two-factor', [AuthController::class, 'disableTwoFactor']);

    Route::get('dashboard', [AppController::class, 'dashboard'])->middleware($can('dashboard.view'));

    Route::get('customers', [AppController::class, 'customers'])->middleware($can('customers.view', 'orders.create'));
    Route::post('customers', [AppController::class, 'saveCustomer'])->middleware($can('customers.manage', 'orders.create'));
    Route::patch('customers/{customer}', [AppController::class, 'updateCustomer'])->middleware($can('customers.manage'));
    Route::delete('customers/{customer}', [AppController::class, 'archiveCustomer'])->middleware($can('customers.delete'));
    Route::post('customers/{customer}/restore', [AppController::class, 'restoreCustomer'])->middleware($can('customers.delete'));
    Route::get('customers/{customer}/orders', [AppController::class, 'customerOrders'])->middleware([$can('customers.view'), $can('orders.view')]);
    Route::get('customers/{customer}/points', [AppController::class, 'loyaltyPoints'])->middleware($can('customers.view'));
    Route::post('customers/{customer}/redeem', [AppController::class, 'redeemLoyalty'])->middleware($can('loyalty.manage'));

    Route::get('orders', [AppController::class, 'orders'])->middleware($can('orders.view'));
    Route::post('orders', [AppController::class, 'saveOrder'])->middleware([$can('orders.create'), 'plan.capacity:orders_per_month']);
    Route::get('orders/{order}', [AppController::class, 'showOrder'])->middleware($can('orders.view'));
    Route::post('orders/{order}/measurements', [AppController::class, 'saveOrderMeasurement'])->middleware($can('orders.measure'));
    Route::post('orders/{order}/assign', [AppController::class, 'assignOrder'])->middleware($can('orders.assign'));
    Route::patch('orders/{order}/status', [AppController::class, 'updateOrderStatus'])->middleware($can('orders.status'));
    Route::post('orders/{order}/payments', [AppController::class, 'payOrder'])->middleware($can('orders.payments'));
    Route::delete('orders/{order}', [AppController::class, 'archiveOrder'])->middleware($can('orders.delete'));
    Route::post('orders/{order}/restore', [AppController::class, 'restoreOrder'])->middleware($can('orders.delete'));
    Route::post('orders/{order}/reminder', [NotificationController::class, 'scheduleDeliveryReminder'])->middleware($can('orders.edit'));
    Route::get('barcode/{order}', [AppController::class, 'barcode'])->middleware($can('orders.view'));

    Route::get('garments', [AppController::class, 'garments'])->middleware($can('garments.view', 'garments.manage', 'orders.create'));
    Route::middleware($can('garments.manage'))->group(function () {
        Route::post('garments', [AppController::class, 'saveGarment']);
        Route::post('garments/reorder', [AppController::class, 'reorderGarments']);
        Route::post('garments/{garment}/clone', [AppController::class, 'cloneGarment']);
        Route::post('garments/{garment}/copy-design', [AppController::class, 'copyGarmentDesign']);
        Route::patch('garments/{garment}', [AppController::class, 'updateGarment']);
        Route::delete('garments/{garment}', [AppController::class, 'deleteGarment']);
        Route::post('garments/{garment}/parts', [AppController::class, 'saveGarmentPart']);
        Route::patch('garments/{garment}/parts/{part}', [AppController::class, 'updateGarmentPart']);
        Route::post('garments/{garment}/parts/reorder', [AppController::class, 'reorderGarmentParts']);
        Route::delete('garments/{garment}/parts/{part}', [AppController::class, 'deleteGarmentPart']);
        Route::post('garments/{garment}/design-options', [AppController::class, 'saveGarmentDesignOption']);
        Route::patch('garments/{garment}/design-options/{option}', [AppController::class, 'updateGarmentDesignOption']);
        Route::delete('garments/{garment}/design-options/{option}', [AppController::class, 'deleteGarmentDesignOption']);
    });

    Route::get('employees', [AppController::class, 'employees'])->middleware($can('employees.view', 'orders.assign', 'employees.manage'));
    Route::post('employees', [AppController::class, 'saveEmployee'])->middleware($can('employees.manage'));
    Route::patch('employees/{employee}', [AppController::class, 'updateEmployee'])->middleware($can('employees.manage'));
    Route::get('workforce', [OperationsController::class, 'workforce'])->middleware($can('employees.view', 'employees.manage'));
    Route::post('attendance', [OperationsController::class, 'attendance'])->middleware($can('attendance.manage'));
    Route::post('work-entries', [OperationsController::class, 'work'])->middleware($can('work.manage'));
    Route::get('payouts', [OperationsController::class, 'payouts'])->middleware($can('payouts.view', 'payouts.manage'));
    Route::post('payouts', [OperationsController::class, 'payoutBatch'])->middleware($can('payouts.manage'));

    Route::get('inventory', [OperationsController::class, 'inventory'])->middleware(['plan.feature:inventory', $can('inventory.view', 'inventory.manage', 'sales.manage')]);
    Route::post('inventory', [OperationsController::class, 'saveInventory'])->middleware(['plan.feature:inventory', $can('inventory.manage')]);
    Route::post('inventory/{item}/adjust', [OperationsController::class, 'adjust'])->middleware(['plan.feature:inventory', $can('inventory.manage')]);
    Route::post('purchases', [OperationsController::class, 'purchase'])->middleware(['plan.feature:inventory', $can('purchases.manage')]);
    Route::get('purchases', [OperationsController::class, 'purchases'])->middleware(['plan.feature:inventory', $can('inventory.view', 'purchases.manage')]);
    Route::get('suppliers', [OperationsController::class, 'suppliers'])->middleware(['plan.feature:inventory', $can('inventory.view', 'purchases.manage')]);
    Route::post('suppliers', [OperationsController::class, 'saveSupplier'])->middleware(['plan.feature:inventory', $can('purchases.manage')]);
    Route::post('sales', [OperationsController::class, 'sale'])->middleware(['plan.feature:inventory', $can('sales.manage')]);
    Route::get('sales', [OperationsController::class, 'sales'])->middleware(['plan.feature:inventory', $can('sales.manage', 'inventory.view')]);
    Route::post('rentals', [OperationsController::class, 'rental'])->middleware(['plan.feature:rentals', $can('rentals.manage')]);
    Route::get('rentals', [OperationsController::class, 'rentals'])->middleware(['plan.feature:rentals', $can('rentals.manage')]);
    Route::post('rentals/{rental}/return', [OperationsController::class, 'returnRental'])->middleware(['plan.feature:rentals', $can('rentals.manage')]);

    Route::get('expenses', [OperationsController::class, 'expenses'])->middleware($can('expenses.view', 'expenses.manage'));
    Route::post('expenses', [OperationsController::class, 'saveExpense'])->middleware($can('expenses.manage'));
    Route::post('expenses/{expense}/attachments', [OperationsController::class, 'uploadExpenseAttachment'])->middleware($can('expenses.manage'));
    Route::get('expense-attachments/{attachment}', [OperationsController::class, 'downloadExpenseAttachment'])->middleware($can('expenses.view', 'expenses.manage'));
    Route::post('expenses/{expense}/approve', [OperationsController::class, 'approveExpense'])->middleware($can('expenses.approve'));

    Route::get('reports/summary', [ReportController::class, 'summary'])->middleware($can('reports.view'));
    Route::get('accounting/trial-balance', [AccountingController::class, 'trialBalance'])->middleware($can('accounting.view'));
    Route::get('customers/{customer}/statement', [AccountingController::class, 'statement'])->middleware($can('accounting.view'));
    Route::post('customers/{customer}/adjustments', [AccountingController::class, 'adjustment'])->middleware($can('accounting.manage'));
    Route::get('exports', [ExportController::class, 'index'])->middleware($can('exports.manage'));
    Route::post('exports', [ExportController::class, 'store'])->middleware($can('exports.manage'));
    Route::get('exports/{export}/download', [ExportController::class, 'download'])->middleware($can('exports.manage'));

    Route::get('notifications', [NotificationController::class, 'index'])->middleware($can('notifications.view', 'notifications.manage'));
    Route::post('notification-templates', [NotificationController::class, 'saveTemplate'])->middleware($can('notifications.manage'));
    Route::post('notification-campaigns', [NotificationController::class, 'campaign'])->middleware(['plan.feature:bulk_notifications', $can('notifications.manage')]);
    Route::post('notification-campaigns/occasion', [NotificationController::class, 'occasionCampaign'])->middleware(['plan.feature:bulk_notifications', $can('notifications.manage')]);

    Route::get('billing', [PlatformController::class, 'billing'])->middleware($can('billing.view', 'billing.manage'));
    Route::post('billing/portal', [PlatformController::class, 'portal'])->middleware($can('billing.manage'));
    Route::post('billing/checkout', [PlatformController::class, 'checkout'])->middleware($can('billing.manage'));

    Route::get('settings', [PlatformController::class, 'showSettings'])->middleware($can('settings.view', 'settings.manage'));
    Route::patch('settings', [PlatformController::class, 'settings'])->middleware($can('settings.manage'));
    Route::get('members', [PlatformController::class, 'members'])->middleware($can('members.manage'));
    Route::post('members', [PlatformController::class, 'saveMember'])->middleware([$can('members.manage'), 'plan.capacity:staff_seats']);
    Route::patch('members/{user}', [PlatformController::class, 'updateMember'])->middleware($can('members.manage'));
    Route::delete('members/{user}', [PlatformController::class, 'deleteMember'])->middleware($can('members.manage'));
    Route::get('roles', [RoleController::class, 'index'])->middleware($can('roles.manage', 'members.manage'));
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware($can('roles.manage'));
    Route::delete('roles/{role}', [RoleController::class, 'reset'])->middleware($can('roles.manage'));
    Route::post('voice-tokens', [PlatformController::class, 'createVoiceToken'])->middleware($can('voice.manage'));

    Route::get('tenant-deletion', [ExportController::class, 'deletionStatus'])->middleware($can('tenant.delete'));
    Route::post('tenant-deletion', [ExportController::class, 'scheduleDeletion'])->middleware($can('tenant.delete'));
    Route::delete('tenant-deletion/{deletion}', [ExportController::class, 'cancelDeletion'])->middleware($can('tenant.delete'));

    Route::get('webhooks', [WebhookController::class, 'index'])->middleware($can('webhooks.manage'));
    Route::post('webhooks', [WebhookController::class, 'store'])->middleware($can('webhooks.manage'));
    Route::patch('webhooks/{endpoint}', [WebhookController::class, 'update'])->middleware($can('webhooks.manage'));
    Route::delete('webhooks/{endpoint}', [WebhookController::class, 'destroy'])->middleware($can('webhooks.manage'));
    Route::post('webhooks/{endpoint}/rotate-secret', [WebhookController::class, 'rotateSecret'])->middleware($can('webhooks.manage'));
});
Route::middleware(['auth:sanctum', 'super.admin'])->prefix('v1/super-admin')->group(function () {
    Route::get('overview', [SuperAdminController::class, 'overview']);
    Route::get('tenants', [SuperAdminController::class, 'tenants']);
    Route::get('tenants/{tenant}', [SuperAdminController::class, 'showTenant']);
    Route::patch('tenants/{tenant}/status', [SuperAdminController::class, 'setStatus']);
    Route::post('tenants/{tenant}/subscription', [SuperAdminController::class, 'assignSubscription']);
    Route::post('tenants/{tenant}/payments', [SuperAdminController::class, 'recordPayment']);
    Route::post('tenants/{tenant}/impersonate', [SuperAdminController::class, 'impersonate']);
    Route::get('payments', [SuperAdminController::class, 'payments']);
    Route::get('plans', [SuperAdminController::class, 'plans']);
    Route::post('plans', [SuperAdminController::class, 'savePlan']);
    Route::patch('plans/{plan:code}', [SuperAdminController::class, 'savePlan']);
    Route::get('audits', [SuperAdminController::class, 'audits']);
    Route::get('reports/export', [SuperAdminController::class, 'export']);
});
