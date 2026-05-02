<?php

namespace Modules\SaaS\Services;

use App\Models\User;
use App\Models\Subscription;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class BillingService
{
    public function createCheckoutSession(User $user, string $plan, string $paymentMethod, string $billingPeriod)
    {
        $priceKey = match ($billingPeriod) {
            'yearly' => "stripe.price_{$plan}_yearly",
            default => "stripe.price_{$plan}_monthly",
        };

        $priceId = config($priceKey);

        return Session::create([
            'customer' => $user->subscriptions()->latest()->first()?->stripe_customer_id,
            'customer_email' => $user->email,
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => config('app.url') . '/dashboard?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.url') . '/billing/plans',
            'metadata' => [
                'user_id' => $user->id,
                'plan' => $plan,
            ],
        ]);
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        \Stripe\Subscription::update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        $subscription->update([
            'status' => 'cancelled',
            'ends_at' => $subscription->current_period_end,
        ]);
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        \Stripe\Subscription::update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => false,
        ]);

        $subscription->update([
            'status' => 'active',
            'ends_at' => null,
        ]);
    }

    public function upgradeSubscription(Subscription $current, string $newPlan): void
    {
        \Stripe\Subscription::update($current->stripe_subscription_id, [
            'items' => [[
                'id' => $current->stripe_subscription_id,
                'price' => config("services.stripe.price_{$newPlan}_monthly"),
            ]],
            'proration_behavior' => 'create_prorations',
        ]);

        $current->update([
            'plan' => $newPlan,
        ]);
    }

    public function downgradeSubscription(Subscription $current, string $newPlan): void
    {
        \Stripe\Subscription::update($current->stripe_subscription_id, [
            'items' => [[
                'id' => $current->stripe_subscription_id,
                'price' => config("services.stripe.price_{$newPlan}_monthly"),
            ]],
            'proration_behavior' => 'none',
        ]);

        $current->update([
            'plan' => $newPlan,
        ]);
    }

    public function handleWebhook(array $payload): void
    {
        $event = \Stripe\Webhook::constructEvent(
            json_encode($payload),
            $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '',
            config('services.stripe.webhook_secret')
        );

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'invoice.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            default => null,
        };
    }

    protected function handleCheckoutCompleted($session): void
    {
        $subscription = \Stripe\Subscription::retrieve($session->subscription);
        $userId = $session->metadata->user_id;

        Subscription::create([
            'user_id' => $userId,
            'stripe_subscription_id' => $subscription->id,
            'stripe_customer_id' => $subscription->customer,
            'plan' => $session->metadata->plan,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'amount' => $subscription->items->data[0]->price->unit_amount / 100,
            'currency' => $subscription->currency,
        ]);

        User::find($userId)->update([
            'subscription_status' => 'active',
            'subscription_plan' => $session->metadata->plan,
        ]);
    }

    protected function handlePaymentSucceeded($invoice): void
    {
        Subscription::where('stripe_subscription_id', $invoice->subscription)
            ->update(['status' => 'active']);
    }

    protected function handlePaymentFailed($invoice): void
    {
        Subscription::where('stripe_subscription_id', $invoice->subscription)
            ->update(['status' => 'past_due']);
    }

    protected function handleSubscriptionDeleted($subscription): void
    {
        Subscription::where('stripe_subscription_id', $subscription->id)
            ->update(['status' => 'cancelled', 'ends_at' => now()]);
    }

    protected function handleSubscriptionUpdated($subscription): void
    {
        $sub = Subscription::where('stripe_subscription_id', $subscription->id)->first();
        if ($sub) {
            $sub->update([
                'current_period_end' => now()->createFromTimestamp($subscription->current_period_end),
                'status' => match ($subscription->status) {
                    'active' => 'active',
                    'past_due' => 'past_due',
                    'canceled' => 'cancelled',
                    default => $subscription->status,
                },
            ]);
        }
    }
}
