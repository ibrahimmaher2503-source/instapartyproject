<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

it('requires vendor authentication for direct document page access', function (): void {
    $url = VendorDocumentsPage::getUrl();
    $this->get($url)->assertRedirect('/vendor-portal/login');
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $this->actingAs($customer, 'vendor')->get($url)->assertForbidden();
});

it('opens the generated documents URL without reading files or showing another vendor records', function (string $locale): void {
    app()->setLocale($locale);
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $profile = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $owned = VendorDocument::factory()->create(['vendor_profile_id' => $profile->id, 'file_name' => 'owned-missing.pdf']);
    $foreign = VendorDocument::factory()->create(['file_name' => 'foreign-private.pdf']);
    $this->actingAs($user, 'vendor');
    $url = VendorDocumentsPage::getUrl(['locale' => $locale, 'lang' => $locale]);
    expect(parse_url($url, PHP_URL_PATH))->toBe('/vendor-portal/vendor-documents-page');
    $this->get($url)->assertOk()->assertSee('owned-missing.pdf')->assertDontSee('foreign-private.pdf');
    Livewire::test(VendorDocumentsPage::class)->assertCanSeeTableRecords([$owned])->assertCanNotSeeTableRecords([$foreign]);
    expect(DB::table('audit_logs')->where('action', 'signed_url_generated')->count())->toBe(0);
})->with(['en', 'ar']);

it('reports an unavailable file only when opening it and keeps the document page usable', function (string $locale): void {
    app()->setLocale($locale);
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $profile = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $document = VendorDocument::factory()->create(['vendor_profile_id' => $profile->id]);
    $this->actingAs($user, 'vendor');
    Livewire::test(VendorDocumentsPage::class)->call('mountTableAction', 'view', (string) $document->id)
        ->assertNotified(__('vendor-portal.documents.file_unavailable'));
    expect(DB::table('audit_logs')->where('action', 'signed_url_generated')->count())->toBe(0);
    $this->get(VendorDocumentsPage::getUrl())->assertOk();
})->with(['en', 'ar']);

it('opens an existing owned file on demand and audits only that explicit request', function (): void {
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $profile = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $document = VendorDocument::factory()->create(['vendor_profile_id' => $profile->id]);
    Storage::disk('local')->put($document->file_path, '%PDF-1.7');
    $this->actingAs($user, 'vendor');
    $this->get(VendorDocumentsPage::getUrl())->assertOk();
    expect(DB::table('audit_logs')->where('action', 'signed_url_generated')->count())->toBe(0);
    Livewire::test(VendorDocumentsPage::class)->call('mountTableAction', 'view', (string) $document->id)->assertRedirect();
    expect(DB::table('audit_logs')->where('action', 'signed_url_generated')->where('auditable_id', $document->id)->count())->toBe(1);
});
