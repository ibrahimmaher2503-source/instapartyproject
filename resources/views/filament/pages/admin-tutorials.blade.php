<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                {{ __('admin_tutorial.page.eyebrow') }}
            </p>
            <h2 class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                {{ __('admin_tutorial.page.heading') }}
            </h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ __('admin_tutorial.page.description') }}
            </p>
        </div>

        @if ($flows === [])
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('admin_tutorial.overlay.no_flows') }}
                </p>
            </x-filament::section>
        @else
            <div class="grid gap-6 xl:grid-cols-[18rem_minmax(0,1fr)]">
                <aside class="h-fit xl:sticky xl:top-6">
                    <x-filament::section :heading="__('admin_tutorial.page.index')">
                        <nav aria-label="{{ __('admin_tutorial.page.index') }}" class="space-y-1">
                            @foreach ($flows as $flowIndex => $flow)
                                <a
                                    href="#tutorial-flow-{{ $flow['id'] }}"
                                    class="flex items-start gap-3 rounded-lg px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 hover:text-primary-700 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-primary-300"
                                >
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold dark:bg-gray-700">
                                        {{ $flowIndex + 1 }}
                                    </span>
                                    <span class="leading-6">{{ $flow['title'] }}</span>
                                </a>
                            @endforeach
                        </nav>
                    </x-filament::section>
                </aside>

                <main class="space-y-5">
                    @foreach ($flows as $flowIndex => $flow)
                        <section id="tutorial-flow-{{ $flow['id'] }}" class="scroll-mt-6">
                            <x-filament::section>
                                <x-slot name="heading">
                                    <span class="me-2 text-sm font-normal text-gray-500">{{ $flowIndex + 1 }}.</span>
                                    {{ $flow['title'] }}
                                </x-slot>

                                <x-slot name="description">
                                    {{ trans_choice('admin_tutorial.page.steps_count', count($flow['steps']), ['count' => count($flow['steps'])]) }}
                                </x-slot>

                                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($flow['steps'] as $step)
                                        <article class="py-6 first:pt-0 last:pb-0">
                                            <div class="flex items-start gap-3">
                                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-semibold text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">
                                                    {{ $step['n'] }}
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                                        {{ $step['title'] }}
                                                    </h3>
                                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-gray-300">
                                                        {{ $step['body'] }}
                                                    </p>

                                                    @if ($step['url'] !== null && str_starts_with($step['url'], '/'))
                                                        <a href="{{ $step['url'] }}" class="mt-3 inline-flex text-sm font-medium text-primary-600 underline underline-offset-4 hover:text-primary-500">
                                                            {{ __('admin_tutorial.overlay.goto_screen') }}
                                                        </a>
                                                    @endif

                                                    @php
                                                        $screenshot = app()->isLocale('ar')
                                                            ? ($step['screenshot_ar'] ?? $step['screenshot_en'])
                                                            : ($step['screenshot_en'] ?? $step['screenshot_ar']);
                                                    @endphp

                                                    @if ($screenshot)
                                                        <figure class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                                            <img src="{{ $screenshot }}" alt="{{ __('admin_tutorial.overlay.screenshot_alt', ['flow' => $flow['id'], 'step' => $step['n']]) }}" class="h-auto w-full" loading="lazy">
                                                        </figure>
                                                    @endif
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </x-filament::section>
                        </section>
                    @endforeach
                </main>
            </div>
        @endif
    </div>
</x-filament-panels::page>
