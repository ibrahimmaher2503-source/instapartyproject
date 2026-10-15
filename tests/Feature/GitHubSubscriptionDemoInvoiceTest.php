<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Database\Seeders\SubscriptionDevelopmentSeeder;
use App\Modules\Subscriptions\Domain\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPayment;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Support\Facades\DB;

it('creates plan-priced demo invoices and matching payments once for newly seeded subscriptions', function (string $type): void {
    $silver = SubscriptionPlan::factory()->create(['plan_code' => 'silver', 'monthly_price_minor' => 19_900, 'monthly_price_currency' => 'USD']);
    $premium = SubscriptionPlan::factory()->create(['plan_code' => 'premium', 'monthly_price_minor' => 99_900]);
    $ordinary = VendorProfile::factory()->create();
    $typeVendor = VendorProfile::factory()->create(['user_id' => User::factory()->asVendor()->create(['email' => 'vendor.'.$type.'@instaparty.local'])->id]);

    $this->seed(SubscriptionDevelopmentSeeder::class);

    foreach ([[$ordinary, $silver], [$typeVendor, $premium]] as [$vendor, $plan]) {
        $subscription = VendorSubscription::query()->where('vendor_profile_id', $vendor->id)->sole();
        $invoice = $subscription->invoices()->sole();
        $payment = $invoice->payments()->sole();
        expect($subscription->subscription_plan_id)->toBe($plan->id)
            ->and($invoice->amount->isEqualTo($plan->monthly_price))->toBeTrue()
            ->and($payment->gateway)->toBe('seed')
            ->and($payment->status)->toBe('captured')
            ->and($payment->gateway_ref)->toBe($invoice->gateway_ref);
    }

    $subscriptions = DB::table('vendor_subscriptions')->orderBy('id')->get()->toArray();
    $invoices = DB::table('subscription_invoices')->orderBy('id')->get()->toArray();
    $payments = DB::table('subscription_payments')->orderBy('id')->get()->toArray();
    $audit = DB::table('subscription_audit')->orderBy('id')->get()->toArray();
    $silver->update(['monthly_price_minor' => 29_900]);
    $this->seed(SubscriptionDevelopmentSeeder::class);

    expect(DB::table('vendor_subscriptions')->orderBy('id')->get()->toArray())->toEqual($subscriptions)
        ->and(DB::table('subscription_invoices')->orderBy('id')->get()->toArray())->toEqual($invoices)
        ->and(DB::table('subscription_payments')->orderBy('id')->get()->toArray())->toEqual($payments)
        ->and(DB::table('subscription_audit')->orderBy('id')->get()->toArray())->toEqual($audit);
})->with(['rental', 'sale', 'digital']);

it('preserves historical invoices and does not fabricate missing invoices or captured payments', function (): void {
    $plan = SubscriptionPlan::factory()->create(['plan_code' => 'silver', 'monthly_price_minor' => 19_900]);
    SubscriptionPlan::factory()->create(['plan_code' => 'premium']);
    $vendor = VendorProfile::factory()->create(['user_id' => User::factory()->asVendor()->create(['email' => 'vendor.digital@instaparty.local'])->id]);
    $subscription = VendorSubscription::factory()->create(['vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $plan->id]);
    $invoice = SubscriptionInvoice::factory()->paid()->create(['vendor_subscription_id' => $subscription->id, 'amount_minor' => 9_900, 'gateway_ref' => 'historical-unverified']);
    $otherVendor = VendorProfile::factory()->create();
    VendorSubscription::factory()->create(['vendor_profile_id' => $otherVendor->id, 'subscription_plan_id' => $plan->id]);
    $before = $invoice->getRawOriginal();
    $subscriptionBefore = $subscription->getRawOriginal();

    $this->seed(SubscriptionDevelopmentSeeder::class);

    expect(array_intersect_key($invoice->fresh()->getRawOriginal(), $before))->toEqual($before)
        ->and(array_intersect_key($subscription->fresh()->getRawOriginal(), $subscriptionBefore))->toEqual($subscriptionBefore)
        ->and(SubscriptionInvoice::query()->count())->toBe(1)
        ->and(SubscriptionPayment::query()->count())->toBe(0);
});

it('refuses to create demo financial records outside local and testing environments', function (): void {
    SubscriptionPlan::factory()->create(['plan_code' => 'silver']);
    VendorProfile::factory()->create();
    $environment = app()->environment();
    app()->instance('env', 'production');
    try {
        app(SubscriptionDevelopmentSeeder::class)->run();
    } finally {
        app()->instance('env', $environment);
    }

    expect(VendorSubscription::query()->count())->toBe(0)
        ->and(SubscriptionInvoice::query()->count())->toBe(0)
        ->and(SubscriptionPayment::query()->count())->toBe(0);
});
