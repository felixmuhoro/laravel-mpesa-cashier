# Laravel M-Pesa Cashier

> Subscription billing for Laravel via Safaricom M-Pesa — monthly/yearly plans, trial periods, grace periods, automatic renewals, and invoice generation.

Modelled after [Laravel Cashier (Stripe)](https://laravel.com/docs/billing) but built specifically for the Kenyan M-Pesa ecosystem through the [felixmuhoro/laravel-mpesa](https://github.com/felixmuhoro/laravel-mpesa) package.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.1 |
| Laravel | ^10.0 \| ^11.0 \| ^12.0 \| ^13.0 |
| felixmuhoro/laravel-mpesa | ^1.2 |

---

## Installation

```bash
composer require felixmuhoro/laravel-mpesa-cashier
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=mpesa-cashier-config
php artisan vendor:publish --tag=mpesa-cashier-migrations
php artisan migrate
```

---

## Setup

### 1. Add the Billable trait to your User model

```php
use FelixMuhoro\MpesaCashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

### 2. Define your plans

In a service provider (e.g. `AppServiceProvider::boot`):

```php
use FelixMuhoro\MpesaCashier\PlanBuilder;

PlanBuilder::define('basic-monthly')
    ->name('Basic Monthly')
    ->amount(500)          // KES 500
    ->monthly()
    ->trialDays(7)
    ->graceDays(3)
    ->register();

PlanBuilder::define('pro-yearly')
    ->name('Pro Yearly')
    ->amount(4800)         // KES 4,800
    ->yearly()
    ->graceDays(7)
    ->register();
```

Or define plans in `config/mpesa-cashier.php`:

```php
'plans' => [
    'basic-monthly' => [
        'name'       => 'Basic Monthly',
        'amount'     => 500,
        'interval'   => 'monthly',
        'trial_days' => 7,
        'grace_days' => 3,
    ],
],
```

### 3. Configure M-Pesa credentials

Follow the [felixmuhoro/laravel-mpesa](https://github.com/felixmuhoro/laravel-mpesa) setup guide to set your Safaricom credentials in `.env`.

---

## Usage

### Subscribe

```php
// Using the fluent builder
$user->newSubscription('default', 'basic-monthly')
     ->withTrial(7)
     ->create('254712345678');

// Shorthand (uses $user->mpesa_phone or $user->phone)
$user->subscribe('basic-monthly');

// Skip trial
$user->newSubscription('default', 'pro-yearly')
     ->skipTrial()
     ->create('254712345678');
```

### Check subscription status

```php
$user->subscribed();                               // any active subscription
$user->subscribed('default', 'basic-monthly');     // specific name + plan
$user->onTrial();
$user->onGracePeriod();
$user->subscription('default');                    // returns Subscription model
```

### Cancel

```php
// At end of billing period (grace period applies)
$user->cancelSubscription();

// Immediately — no grace period
$user->cancelNow();
```

### Resume

```php
// Only works if still within grace period
$user->resume();
```

### Invoices

```php
// All invoices for the user
$user->invoices;

// Invoices for a specific subscription
$user->subscription('default')->invoices;
```

---

## REST API Endpoints

The package registers the following routes under the `api` middleware and `auth:sanctum`:

| Method | URI | Action |
|---|---|---|
| GET | `/mpesa-cashier/plans` | List all available plans |
| POST | `/mpesa-cashier/subscribe` | Create a subscription |
| POST | `/mpesa-cashier/subscriptions/{id}/cancel` | Cancel a subscription |
| POST | `/mpesa-cashier/subscriptions/{id}/resume` | Resume a cancelled subscription |
| GET | `/mpesa-cashier/invoices` | List all invoices for the auth user |
| GET | `/mpesa-cashier/subscriptions/{id}/invoices` | List invoices for one subscription |

**Subscribe request body:**

```json
{
  "plan_id": "basic-monthly",
  "phone": "254712345678",
  "subscription_name": "default"
}
```

---

## Automatic Renewals (Cron)

Schedule the renewal command in your scheduler:

```php
// app/Console/Kernel.php
$schedule->command('mpesa-cashier:renew')->daily();
```

Or add a cron entry directly:

```
0 8 * * * php /var/www/yourapp/artisan mpesa-cashier:renew >> /dev/null 2>&1
```

Dry run (no STK pushes):

```bash
php artisan mpesa-cashier:renew --dry-run
```

---

## Payment Callbacks

When a payment succeeds or fails, the `HandleSubscriptionPayment` listener processes the result. Wire it to your M-Pesa callback event in `EventServiceProvider`:

```php
use FelixMuhoro\Mpesa\Events\StkPushCallbackReceived;
use FelixMuhoro\MpesaCashier\Listeners\HandleSubscriptionPayment;

protected $listen = [
    StkPushCallbackReceived::class => [
        HandleSubscriptionPayment::class,
    ],
];
```

The callback payload must include `subscription_id`, `invoice_id`, `receipt`, and `result_code` (0 = success).

---

## Events

| Event | Fired when |
|---|---|
| `SubscriptionRenewed` | Payment confirmed, billing date advanced |
| `SubscriptionPaymentFailed` | STK push failed or callback result_code != 0 |
| `SubscriptionCancelled` | Subscription cancelled |

---

## Configuration Reference

```php
// config/mpesa-cashier.php
return [
    'model'                  => \App\Models\User::class,
    'currency'               => 'KES',
    'grace_days'             => 3,
    'trial_days'             => 0,
    'account_reference'      => 'Subscription',
    'transaction_description'=> 'Subscription Renewal',
    'retry_attempts'         => 3,
    'plans'                  => [],
    'invoice'                => [
        'company_name'    => env('APP_NAME'),
        'company_address' => '',
        'logo'            => '',
    ],
];
```

---

## Database Schema

### `subscriptions`

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| user_id | bigint | indexed |
| name | string | subscription slot name |
| plan_id | string | references PlanRegistry |
| status | string | active / trialing / cancelled / past_due |
| trial_ends_at | timestamp | nullable |
| ends_at | timestamp | set on cancel |
| grace_period_ends_at | timestamp | nullable |
| next_billing_date | timestamp | nullable |
| retry_count | tinyint | reset on successful renewal |

### `subscription_items`

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| subscription_id | bigint | FK → subscriptions |
| plan_id | string | |
| quantity | int | |

### `invoices`

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| subscription_id | bigint | FK → subscriptions |
| user_id | bigint | |
| invoice_number | string | unique, e.g. INV-20240101-000001 |
| amount | int | KES whole units |
| currency | string | default KES |
| status | string | pending / paid / failed |
| mpesa_receipt | string | nullable |
| phone | string | nullable |
| due_date | timestamp | |
| paid_at | timestamp | nullable |

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests use an in-memory SQLite database and a mock M-Pesa client — no real API calls are made.

---

## License

MIT — see [LICENSE](LICENSE).
