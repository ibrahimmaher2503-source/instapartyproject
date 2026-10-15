<x-filament-panels::page>
    @php($inquiry = $this->getInquiry())

    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm text-gray-500">{{ __('communication::communication.vendor_inquiries.service') }}</p>
            <h2 class="mt-1 text-lg font-semibold">{{ $inquiry->serviceNameFor(app()->getLocale()) }}</h2>
            <p class="mt-3 text-sm text-gray-500">{{ __('communication::communication.vendor_inquiries.customer') }}</p>
            <p class="mt-1 font-medium">{{ $inquiry->customer_name }}</p>
        </section>

        <section class="space-y-4" aria-label="{{ __('communication::communication.vendor_inquiries.messages') }}">
            @foreach ($inquiry->messages as $message)
                <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-4 text-sm text-gray-500">
                        <span>{{ __('communication::communication.vendor_inquiries.author.'.($message->author_role === 'vendor' ? 'you' : 'customer')) }}</span>
                        <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->format('Y-m-d H:i') }}</time>
                    </div>
                    <p class="mt-3 whitespace-pre-wrap break-words text-gray-900 dark:text-gray-100">{{ $message->body }}</p>
                </article>
            @endforeach
        </section>

        <form wire:submit="sendReply" class="space-y-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <label for="vendor-inquiry-reply" class="block text-sm font-medium">{{ __('communication::communication.vendor_inquiries.reply_label') }}</label>
            <textarea id="vendor-inquiry-reply" wire:model="body" rows="5" maxlength="5000" required class="fi-input block w-full rounded-lg border-gray-300"></textarea>
            @error('body') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            <p class="text-sm text-gray-500">{{ __('communication::communication.vendor_inquiries.message_help') }}</p>
            <x-filament::button type="submit">{{ __('communication::communication.vendor_inquiries.send_reply') }}</x-filament::button>
        </form>
    </div>
</x-filament-panels::page>
