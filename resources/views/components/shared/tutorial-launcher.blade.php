@props([
    'flows' => [],
    'manifestExists' => false,
])

@if (filament()->getCurrentPanel()?->getId() === 'admin')
    <div
        class="relative inline-flex shrink-0"
        x-data="{
            launcherOpen: false,
            flows: @js($flows),
            flowIndex: 0,
            stepIndex: 0,
            stateKey: 'instaparty.admin_tutorial.v1',
            screenshotBaseUrl: @js(asset('admin-tutorial/screenshots')),
            progress: {
                lastFlowId: null,
                lastStep: 1,
                completedFlowIds: [],
                dismissedForever: false,
            },
            init() {
                try {
                    const saved = JSON.parse(localStorage.getItem(this.stateKey) || '{}');
                    this.progress = { ...this.progress, ...saved };
                } catch (error) {
                    this.progress = { ...this.progress };
                }

                this.resume();
            },
            resume() {
                if (!this.flows.length) return;

                const savedFlow = this.flows.findIndex(flow => flow.id === this.progress.lastFlowId);
                this.flowIndex = savedFlow >= 0 ? savedFlow : 0;
                this.stepIndex = Math.max(0, Math.min(
                    (this.progress.lastStep || 1) - 1,
                    this.flows[this.flowIndex].steps.length - 1,
                ));
            },
            currentFlow() {
                return this.flows[this.flowIndex] || null;
            },
            currentStep() {
                return this.currentFlow()?.steps?.[this.stepIndex] || null;
            },
            isFirstStep() {
                return this.flowIndex === 0 && this.stepIndex === 0;
            },
            isLastStep() {
                return this.flowIndex === this.flows.length - 1
                    && this.stepIndex === (this.currentFlow()?.steps?.length || 1) - 1;
            },
            persist() {
                const flow = this.currentFlow();
                if (!flow) return;

                this.progress.lastFlowId = flow.id;
                this.progress.lastStep = this.stepIndex + 1;
                localStorage.setItem(this.stateKey, JSON.stringify(this.progress));
            },
            next() {
                if (this.isLastStep()) {
                    this.progress.dismissedForever = true;
                    this.persist();
                    this.close();
                    return;
                }

                if (this.stepIndex < this.currentFlow().steps.length - 1) {
                    this.stepIndex++;
                } else {
                    this.flowIndex++;
                    this.stepIndex = 0;
                }

                this.persist();
            },
            previous() {
                if (this.isFirstStep()) return;

                if (this.stepIndex > 0) {
                    this.stepIndex--;
                } else {
                    this.flowIndex--;
                    this.stepIndex = this.currentFlow().steps.length - 1;
                }

                this.persist();
            },
            startOver() {
                this.flowIndex = 0;
                this.stepIndex = 0;
                this.progress.dismissedForever = false;
                this.persist();
            },
            goToFlow(index) {
                if (!this.flows[index]) return;

                this.flowIndex = index;
                this.stepIndex = 0;
                this.progress.dismissedForever = false;
                this.persist();
            },
            skip() {
                this.progress.dismissedForever = true;
                this.persist();
                this.close();
            },
            close() {
                $dispatch('close-modal', { id: 'admin-tutorial' });
            },
            screenshot() {
                const step = this.currentStep();
                if (!step) return null;

                const file = document.documentElement.lang === 'ar'
                    ? (step.screenshot_ar || step.screenshot_en)
                    : (step.screenshot_en || step.screenshot_ar);

                return file ? `${this.screenshotBaseUrl}/${file}` : null;
            },
            screenUrl() {
                const url = this.currentStep()?.url || null;

                return url && url.startsWith('/') ? url : null;
            },
            openScreen() {
                const url = this.screenUrl();
                if (!url) return;

                this.close();
                window.location.assign(url);
            },
        }"
        x-on:open-modal.window="if ($event.detail.id === 'admin-tutorial') { launcherOpen = true; $nextTick(() => document.getElementById('admin-tutorial-heading')?.focus()) }"
        x-on:close-modal.window="if ($event.detail.id === 'admin-tutorial') { launcherOpen = false }"
    >
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-o-academic-cap"
            type="button"
            title="{{ __('admin_tutorial.launcher.tooltip') }}"
            aria-label="{{ __('admin_tutorial.launcher.aria_label') }}"
            aria-haspopup="dialog"
            x-bind:aria-expanded="launcherOpen.toString()"
            x-on:click="$dispatch('open-modal', { id: 'admin-tutorial' })"
            class="shrink-0"
        >
            <span class="hidden md:inline">{{ __('admin_tutorial.launcher.label') }}</span>
        </x-filament::button>

        <span
            x-show="!progress.dismissedForever"
            x-cloak
            class="pointer-events-none absolute -end-0.5 -top-0.5 h-2 w-2 rounded-full bg-primary-500 ring-2 ring-white dark:ring-gray-900"
            aria-hidden="true"
        ></span>

        <x-filament::modal
            id="admin-tutorial"
            width="3xl"
            :close-by-clicking-away="true"
            :close-by-escaping="true"
            sticky-header
            sticky-footer
        >
            <x-slot name="heading">
                <span
                    id="admin-tutorial-heading"
                    tabindex="-1"
                    class="outline-none"
                >{{ __('admin_tutorial.overlay.heading') }}</span>
            </x-slot>

            <div class="space-y-5">
                {{-- Two different empty states. Reporting the permission one when
                     the manifest was simply never generated sends admins hunting
                     through Shield for a problem that does not exist. --}}
                <template x-if="!flows.length">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        @if ($manifestExists)
                            {{ __('admin_tutorial.overlay.no_flows') }}
                        @else
                            {{ __('admin_tutorial.overlay.not_built') }}
                        @endif
                    </p>
                </template>

                <template x-if="flows.length && currentFlow() && currentStep()">
                    <div class="space-y-5">
                        <div class="flex items-center justify-between gap-4 text-sm text-gray-600 dark:text-gray-400">
                            <span x-text="@js(__('admin_tutorial.overlay.flow_progress', ['current' => '__CURRENT__', 'total' => '__TOTAL__'])).replace('__CURRENT__', flowIndex + 1).replace('__TOTAL__', flows.length)"></span>
                            <span x-text="@js(__('admin_tutorial.overlay.step_progress', ['current' => '__CURRENT__', 'total' => '__TOTAL__'])).replace('__CURRENT__', stepIndex + 1).replace('__TOTAL__', currentFlow().steps.length)"></span>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/60">
                            <p class="text-xs font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400">
                                {{ __('admin_tutorial.overlay.current_flow') }}
                            </p>
                            <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white" x-text="currentFlow().title || ''"></h2>
                        </div>

                        <nav aria-label="{{ __('admin_tutorial.overlay.flow_index') }}">
                            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ __('admin_tutorial.overlay.flow_index') }}
                            </p>
                            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                                <template x-for="(flow, index) in flows" :key="`flow-index-${flow.id}`">
                                    <button
                                        type="button"
                                        class="flex min-h-10 items-center gap-2 rounded-md border px-2.5 py-2 text-start text-xs transition hover:border-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/30"
                                        :class="index === flowIndex ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300'"
                                        :aria-current="index === flowIndex ? 'step' : null"
                                        x-on:click="goToFlow(index)"
                                    >
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 font-semibold dark:bg-gray-700" x-text="index + 1"></span>
                                        <span class="line-clamp-2" x-text="flow.title || ''"></span>
                                    </button>
                                </template>
                            </div>
                        </nav>

                        <div class="flex gap-1" role="tablist" aria-label="{{ __('admin_tutorial.overlay.step_progress', ['current' => 1, 'total' => 1]) }}">
                            <template x-for="(step, index) in currentFlow().steps" :key="`${currentFlow().id}-${index}`">
                                <button
                                    type="button"
                                    class="h-2 flex-1 rounded-full bg-gray-200 transition dark:bg-gray-700"
                                    :class="index <= stepIndex ? 'bg-primary-500' : ''"
                                    :aria-label="`{{ __('admin_tutorial.overlay.step_progress', ['current' => '__CURRENT__', 'total' => '__TOTAL__']) }}`.replace('__CURRENT__', index + 1).replace('__TOTAL__', currentFlow().steps.length)"
                                    :aria-selected="index === stepIndex"
                                    x-on:click="stepIndex = index; persist()"
                                ></button>
                            </template>
                        </div>

                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <template x-if="screenshot()">
                                <img
                                    :src="screenshot()"
                                    :alt="@js(__('admin_tutorial.overlay.screenshot_alt', ['flow' => '__FLOW__', 'step' => '__STEP__'])).replace('__FLOW__', currentFlow().id).replace('__STEP__', currentStep().n)"
                                    class="h-auto max-w-full"
                                >
                            </template>
                            <div x-show="!screenshot()" class="space-y-2 bg-gray-50 p-5 dark:bg-gray-800/60">
                                <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                                    <span x-text="screenUrl() ? @js(__('admin_tutorial.overlay.live_screen')) : @js(__('admin_tutorial.overlay.text_only_step'))"></span>
                                </p>
                                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300" x-text="screenUrl() ? @js(__('admin_tutorial.overlay.screenshot_pending')) : @js(__('admin_tutorial.overlay.text_only_step'))"></p>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white" x-text="currentStep().title || ''"></h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-gray-300" x-text="currentStep().body || ''"></p>
                        </div>

                        <template x-if="screenUrl()">
                            <button
                                type="button"
                                x-on:click="openScreen()"
                                class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 underline underline-offset-4 hover:text-primary-500"
                            >
                                <span>{{ __('admin_tutorial.overlay.goto_screen') }}</span>
                                <span aria-hidden="true">↗</span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            <x-slot name="footer">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <x-filament::button color="gray" size="sm" type="button" x-on:click="startOver()">
                            {{ __('admin_tutorial.overlay.start_over') }}
                        </x-filament::button>
                        <x-filament::button color="gray" size="sm" type="button" x-on:click="skip()">
                            {{ __('admin_tutorial.overlay.skip') }}
                        </x-filament::button>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-filament::button color="gray" size="sm" type="button" x-on:click="previous()" x-bind:disabled="!flows.length || isFirstStep()">
                            {{ __('admin_tutorial.overlay.previous') }}
                        </x-filament::button>
                        <x-filament::button size="sm" type="button" x-on:click="next()" x-bind:disabled="!flows.length">
                            <span x-text="isLastStep() ? @js(__('admin_tutorial.overlay.finish')) : @js(__('admin_tutorial.overlay.next'))"></span>
                        </x-filament::button>
                    </div>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
@endif
