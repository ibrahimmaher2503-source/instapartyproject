<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests\Internal;

use App\Modules\Communication\Application\DTOs\MirrorFirestoreMessageDTO;
use Illuminate\Foundation\Http\FormRequest;

class MirrorChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is the shared-secret middleware; no user session here.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** @bodyParam firestore_thread_id string required Firestore thread document id. Example: 01J9Z8K3QF */
            'firestore_thread_id' => ['required', 'string', 'max:64'],
            /** @bodyParam firestore_message_id string required Firestore message document id (idempotency key). Example: 01J9Z8K9YT */
            'firestore_message_id' => ['required', 'string', 'max:64'],
            /** @bodyParam sender_user_id integer required users.id of the sender. Example: 42 */
            'sender_user_id' => ['required'],
            /** @bodyParam body string Final body after redaction (empty when blocked). Example: When do you arrive? */
            'body' => ['present', 'string', 'max:2000'],
            /** @bodyParam blocked boolean required Whether the message was blocked. Example: false */
            'blocked' => ['required', 'boolean'],
            /** @bodyParam flag_reason string Most-severe reason when blocked. Example: phone */
            'flag_reason' => ['nullable', 'string', 'in:phone,email,external_link'],
            'matched_patterns' => ['array'],
            'matched_patterns.*.flag_type' => ['required_with:matched_patterns', 'string', 'in:phone,email,external_link'],
            'matched_patterns.*.matched_pattern' => ['required_with:matched_patterns', 'string', 'max:255'],
        ];
    }

    public function toDTO(): MirrorFirestoreMessageDTO
    {
        /** @var array<int, array{flag_type:string,matched_pattern:string}> $patterns */
        $patterns = $this->input('matched_patterns', []);

        return new MirrorFirestoreMessageDTO(
            firestoreThreadId: (string) $this->input('firestore_thread_id'),
            firestoreMessageId: (string) $this->input('firestore_message_id'),
            senderUserId: (string) $this->input('sender_user_id'),
            body: (string) $this->input('body', ''),
            blocked: $this->boolean('blocked'),
            flagReason: $this->input('flag_reason'),
            matchedPatterns: array_values($patterns),
        );
    }
}
