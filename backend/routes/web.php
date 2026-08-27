<?php

use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Stripe webhook — signature verified by Cashier's middleware, CSRF-exempt.
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->withoutMiddleware(ValidateCsrfToken::class);
