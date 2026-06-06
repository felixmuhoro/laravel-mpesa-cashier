<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier\Http\Controllers;

use FelixMuhoro\MpesaCashier\Exceptions\AlreadySubscribedException;
use FelixMuhoro\MpesaCashier\Exceptions\InvalidPlanException;
use FelixMuhoro\MpesaCashier\Exceptions\SubscriptionException;
use FelixMuhoro\MpesaCashier\PlanRegistry;
use FelixMuhoro\MpesaCashier\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SubscriptionController extends Controller
{
    // -------------------------------------------------------------------------
    // POST /mpesa-cashier/subscribe
    // -------------------------------------------------------------------------

    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id'           => ['required', 'string'],
            'phone'             => ['required', 'string', 'regex:/^254[0-9]{9}$/'],
            'subscription_name' => ['sometimes', 'string', 'max:50'],
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $plan = PlanRegistry::get($request->plan_id);
        } catch (InvalidPlanException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        try {
            $subscription = $user->newSubscription(
                $request->input('subscription_name', 'default'),
                $plan->id,
            )->create($request->phone);
        } catch (AlreadySubscribedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (SubscriptionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'      => 'Subscription created. Check your phone for the M-Pesa prompt.',
            'subscription' => $this->formatSubscription($subscription),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // POST /mpesa-cashier/subscriptions/{subscription}/cancel
    // -------------------------------------------------------------------------

    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorizeSubscription($request, $subscription);

        $immediately = $request->boolean('immediately', false);

        $immediately ? $subscription->cancelNow() : $subscription->cancel();

        return response()->json([
            'message'      => $immediately
                ? 'Subscription cancelled immediately.'
                : 'Subscription will be cancelled at end of billing period.',
            'subscription' => $this->formatSubscription($subscription->fresh()),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /mpesa-cashier/subscriptions/{subscription}/resume
    // -------------------------------------------------------------------------

    public function resume(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorizeSubscription($request, $subscription);

        if (! $subscription->onGracePeriod()) {
            return response()->json([
                'message' => 'Subscription cannot be resumed; grace period has ended.',
            ], 422);
        }

        $subscription->resume();

        return response()->json([
            'message'      => 'Subscription resumed successfully.',
            'subscription' => $this->formatSubscription($subscription->fresh()),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /mpesa-cashier/invoices
    // -------------------------------------------------------------------------

    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $invoices = $user->invoices()
            ->with('subscription')
            ->paginate($request->integer('per_page', 15));

        return response()->json($invoices);
    }

    // -------------------------------------------------------------------------
    // GET /mpesa-cashier/subscriptions/{subscription}/invoices
    // -------------------------------------------------------------------------

    public function subscriptionInvoices(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorizeSubscription($request, $subscription);

        $invoices = $subscription->invoices()
            ->paginate($request->integer('per_page', 15));

        return response()->json($invoices);
    }

    // -------------------------------------------------------------------------
    // GET /mpesa-cashier/plans
    // -------------------------------------------------------------------------

    public function plans(): JsonResponse
    {
        $plans = array_map(fn ($plan) => $plan->toArray(), PlanRegistry::all());

        return response()->json(['plans' => $plans]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function authorizeSubscription(Request $request, Subscription $subscription): void
    {
        $user = $request->user();

        if (! $user || $subscription->user_id !== $user->getKey()) {
            abort(403, 'This subscription does not belong to you.');
        }
    }

    private function formatSubscription(Subscription $subscription): array
    {
        return [
            'id'                    => $subscription->id,
            'name'                  => $subscription->name,
            'plan_id'               => $subscription->plan_id,
            'status'                => $subscription->status,
            'trial_ends_at'         => $subscription->trial_ends_at?->toIso8601String(),
            'ends_at'               => $subscription->ends_at?->toIso8601String(),
            'grace_period_ends_at'  => $subscription->grace_period_ends_at?->toIso8601String(),
            'next_billing_date'     => $subscription->next_billing_date?->toIso8601String(),
            'created_at'            => $subscription->created_at->toIso8601String(),
        ];
    }
}
