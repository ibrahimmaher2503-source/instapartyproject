<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Production Firestore gateway.
 *
 * Writes thread + message documents via the Firestore REST API, authenticated by
 * a Google service-account JWT (no extra SDK surface needed beyond Guzzle + ext-openssl).
 * Bound only when FIREBASE_CREDENTIALS is configured (see CommunicationServiceProvider);
 * local/testing keep {@see FirestoreChatGatewayStub}.
 *
 * Admin-SDK-equivalent writes (server side) bypass firestore.rules.
 */
final class FirebaseFirestoreChatGateway implements FirestoreChatGateway
{
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/datastore';

    /** @var array{client_email:string,private_key:string} */
    private array $serviceAccount;

    public function __construct(
        private readonly Client $http,
        private readonly CacheRepository $cache,
        private readonly string $projectId,
        string $credentials,
    ) {
        $this->serviceAccount = $this->loadServiceAccount($credentials);
    }

    public function createThread(
        string $firestoreThreadId,
        string $bookingPublicId,
        string $customerUserId,
        string $vendorUserId,
    ): void {
        $this->patchDocument("threads/{$firestoreThreadId}", [
            'bookingId' => $this->stringValue($bookingPublicId),
            'customerId' => $this->stringValue($customerUserId),
            'vendorUserId' => $this->stringValue($vendorUserId),
            'participantUserIds' => [
                'arrayValue' => ['values' => [
                    $this->stringValue($customerUserId),
                    $this->stringValue($vendorUserId),
                ]],
            ],
            'status' => $this->stringValue('open'),
            'frozen' => ['booleanValue' => false],
        ]);
    }

    public function freezeThread(string $firestoreThreadId): void
    {
        $this->patchDocument("threads/{$firestoreThreadId}", [
            'frozen' => ['booleanValue' => true],
            'status' => $this->stringValue('frozen'),
        ], ['frozen', 'status']);
    }

    public function unfreezeThread(string $firestoreThreadId): void
    {
        $this->patchDocument("threads/{$firestoreThreadId}", [
            'frozen' => ['booleanValue' => false],
            'status' => $this->stringValue('open'),
        ], ['frozen', 'status']);
    }

    public function sendMessage(string $firestoreThreadId, string $senderUserId, string $body): string
    {
        $messageId = Str::ulid()->toBase32();

        $this->patchDocument("threads/{$firestoreThreadId}/messages/{$messageId}", [
            'senderUserId' => $this->stringValue($senderUserId),
            'body' => $this->stringValue($body),
            'createdAt' => ['timestampValue' => now()->toRfc3339String()],
            'blocked' => ['booleanValue' => false],
            'redacted' => ['booleanValue' => false],
        ]);

        return $messageId;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<string>|null  $updateMask  field paths to update (null = full set)
     */
    private function patchDocument(string $path, array $fields, ?array $updateMask = null): void
    {
        $url = sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/%s',
            $this->projectId,
            $path,
        );

        $query = [];
        foreach ($updateMask ?? array_keys($fields) as $field) {
            $query['updateMask.fieldPaths'][] = $field;
        }

        $this->http->patch($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->accessToken(),
                'Content-Type' => 'application/json',
            ],
            'query' => $query,
            'json' => ['fields' => $fields],
        ]);
    }

    /** @return array{stringValue:string} */
    private function stringValue(string $value): array
    {
        return ['stringValue' => $value];
    }

    private function accessToken(): string
    {
        return $this->cache->remember(
            'firestore_chat_access_token:'.md5($this->serviceAccount['client_email']),
            3300, // 55 min (token lifetime is 3600s)
            fn (): string => $this->mintAccessToken(),
        );
    }

    private function mintAccessToken(): string
    {
        $now = time();
        $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->b64(json_encode([
            'iss' => $this->serviceAccount['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URI,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $signingInput = $header.'.'.$claims;
        $signature = '';
        if (! openssl_sign($signingInput, $signature, $this->serviceAccount['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign Firestore JWT.');
        }

        $assertion = $signingInput.'.'.$this->b64($signature);

        $response = $this->http->post(self::TOKEN_URI, [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ],
        ]);

        /** @var array{access_token?:string} $data */
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (empty($data['access_token'])) {
            throw new RuntimeException('Firestore token endpoint returned no access_token.');
        }

        return $data['access_token'];
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @return array{client_email:string,private_key:string}
     */
    private function loadServiceAccount(string $credentials): array
    {
        $json = is_file($credentials) ? (string) file_get_contents($credentials) : $credentials;

        /** @var array{client_email?:string,private_key?:string} $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (empty($data['client_email']) || empty($data['private_key'])) {
            throw new RuntimeException('Invalid Firebase service-account credentials.');
        }

        return ['client_email' => $data['client_email'], 'private_key' => $data['private_key']];
    }
}
