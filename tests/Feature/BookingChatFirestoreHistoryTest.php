<?php

declare(strict_types=1);

use App\Modules\Communication\Infrastructure\Gateways\FirebaseFirestoreChatGateway;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\DTOs\FirestoreChatMessage;
use App\Modules\Communication\Domain\DTOs\FirestoreChatMessagePage;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Filament\Vendor\Components\RestrictedChatPanel;
use App\Modules\Identity\Domain\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('reads paginated Firestore messages in descending history order and decodes the REST document shape', function (): void {
    $email = 'chat@test-project.iam.gserviceaccount.com';
    app('cache.store')->put('firestore_chat_access_token:'.md5($email), 'test-access-token', 3600);

    $requests = [];
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'documents' => [[
                'name' => 'projects/test-project/databases/(default)/documents/threads/thread-1/messages/message-2',
                'createTime' => '2026-10-16T10:02:00Z',
                'fields' => [
                    'senderUserId' => ['stringValue' => '22'],
                    'body' => ['stringValue' => 'Latest message'],
                    'createdAt' => ['timestampValue' => '2026-10-16T10:02:00Z'],
                    'blocked' => ['booleanValue' => false],
                    'redacted' => ['booleanValue' => false],
                ],
            ]],
            'nextPageToken' => 'older-page-token',
        ])),
        new Response(200, [], json_encode([
            'documents' => [[
                'name' => 'projects/test-project/databases/(default)/documents/threads/thread-1/messages/message-1',
                'createTime' => '2026-10-16T10:01:00Z',
                'fields' => [
                    'senderUserId' => ['stringValue' => '11'],
                    'body' => ['stringValue' => 'Earlier message'],
                    'createdAt' => ['timestampValue' => '2026-10-16T10:01:00Z'],
                    'blocked' => ['booleanValue' => false],
                    'redacted' => ['booleanValue' => false],
                ],
            ]],
        ])),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($requests));
    $gateway = new FirebaseFirestoreChatGateway(
        new Client(['handler' => $stack]),
        app('cache.store'),
        'test-project',
        json_encode(['client_email' => $email, 'private_key' => 'test-private-key']),
    );

    $latest = $gateway->listMessages('thread-1');
    $older = $gateway->listMessages('thread-1', $latest->nextPageToken);

    expect($latest->messages[0]->id)->toBe('message-2')
        ->and($latest->messages[0]->body)->toBe('Latest message')
        ->and($latest->messages[0]->senderUserId)->toBe('22')
        ->and($latest->nextPageToken)->toBe('older-page-token')
        ->and($older->messages[0]->id)->toBe('message-1')
        ->and($older->messages[0]->body)->toBe('Earlier message')
        ->and((string) $requests[1]['request']->getUri())->toContain('/threads/thread-1/messages')
        ->and((string) $requests[1]['request']->getUri())->toContain('orderBy=createdAt%20desc')
        ->and($requests[1]['request']->getUri()->getQuery())->toContain('pageToken=older-page-token');
});

it('shows the Firestore history to the owning customer on the booking chat panel', function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    $vendor = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->confirmed()->create();
    BookingVendor::factory()->pending()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
    ]);
    $thread = ChatThread::factory()->create([
        'booking_id' => $booking->id,
        'customer_id' => $booking->customer_id,
        'vendor_profile_id' => $vendor->id,
    ]);
    $customer = User::query()->findOrFail($booking->customer_id);
    $this->actingAs($customer);

    $gateway = Mockery::mock(FirestoreChatGateway::class);
    $gateway->shouldReceive('listMessages')->once()->with($thread->firestore_thread_id)->andReturn(new FirestoreChatMessagePage([
        new FirestoreChatMessage('message-1', (string) $customer->id, 'Shared booking history', Carbon::parse('2026-10-16T10:00:00Z'), false, false),
    ], null));
    app()->instance(FirestoreChatGateway::class, $gateway);

    Livewire::test(RestrictedChatPanel::class, ['threadPublicId' => $thread->public_id])
        ->assertSee('Shared booking history');
});
