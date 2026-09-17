<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Shared\Application\DTOs\TutorialManifestProblem;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses the hand-authored admin flow catalogue in docs/admin-flows/ into the
 * manifest that drives the in-panel Tutorial overlay.
 *
 * Contract lives in docs/admin-tutorial-build-prompt.md §3.2 and §7.1. The .md
 * files are the single source of truth; this service never writes to them, and
 * the manifest it emits is never hand-edited.
 *
 * Deliberately free of any Filament API surface so it is usable (and testable)
 * independently of the panel's v3/v4 state.
 */
class TutorialManifestService
{
    private const REQUIRED_FRONT_MATTER = [
        'id', 'order', 'title_en', 'title_ar', 'audience', 'permission', 'estimated_minutes', 'prd_refs',
    ];

    /** @var list<TutorialManifestProblem> */
    private array $problems = [];

    public function __construct(
        private readonly string $flowsPath,
        private readonly string $screenshotsPath,
        private readonly bool $allowMissingScreenshots = false,
    ) {}

    /**
     * Build the manifest from disk.
     *
     * @return array{flows: list<array<string, mixed>>}
     */
    public function build(): array
    {
        $this->problems = [];

        $flows = collect($this->flowFiles())
            ->map(fn (string $path): ?array => $this->parseFlow($path))
            ->filter()
            ->sortBy('order')
            ->values()
            ->all();

        return ['flows' => $flows];
    }

    /**
     * Problems accumulated by the last build(). Empty means the catalogue is clean.
     *
     * @return list<TutorialManifestProblem>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * Load the generated manifest for the current panel viewer.
     *
     * The build command remains the only writer. A missing or invalid generated
     * file is treated as an empty catalogue so the panel chrome stays usable.
     *
     * @return array{flows: list<array<string, mixed>>}
     */
    /**
     * Has the manifest been generated at all?
     *
     * An empty flow list has two very different causes — the manifest was never
     * built, or it was built and the viewer's permissions filtered every flow
     * out. The overlay must tell them apart, otherwise "not built yet" reads to
     * an admin as "your role is wrong" and sends them hunting through Shield.
     */
    public function manifestExists(): bool
    {
        return File::exists($this->manifestPath());
    }

    private function manifestPath(): string
    {
        return storage_path('app/admin-tutorial/manifest.json');
    }

    public function loadForViewer(?Authorizable $user): array
    {
        $path = $this->manifestPath();

        if (! File::exists($path)) {
            return ['flows' => []];
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded) || ! isset($decoded['flows']) || ! is_array($decoded['flows'])) {
            return ['flows' => []];
        }

        /** @var array{flows: list<array<string, mixed>>} $manifest */
        $manifest = ['flows' => array_values($decoded['flows'])];

        $filtered = $this->filterForViewer($manifest, $user);

        // The manifest stores translation keys so it remains locale-neutral on disk.
        // Resolve those keys here because Alpine cannot call Laravel's translator
        // while it advances between steps in the browser.
        $filtered['flows'] = array_map(function (array $flow): array {
            $arabicTitles = TutorialArabicTitles::forFlow($flow['id']);
            $flow['title'] = __($flow['title_key']);
            $flow['steps'] = array_map(function (array $step) use ($arabicTitles): array {
                $step['title'] = __($step['title_key']);
                $step['body'] = __($step['body_key']);

                if (app()->isLocale('ar') && isset($arabicTitles[$step['n']])) {
                    $step['title'] = $arabicTitles[$step['n']];
                }

                if (preg_match('/^Follow step \d+:/', $step['body']) === 1
                    || preg_match('/^اتبع الخطوة \d+/', $step['body']) === 1) {
                    $step['body'] = __('admin_tutorial.overlay.generic_step_body', [
                        'title' => $step['title'],
                    ]);
                }

                return $step;
            }, $flow['steps']);

            return $flow;
        }, $filtered['flows']);

        return $filtered;
    }

    /**
     * Drop flows the viewer cannot perform, then renumber so the overlay shows
     * "Flow 1 of 3" rather than "Flow 4 of 8" with gaps (§7.6).
     *
     * @param  array{flows: list<array<string, mixed>>}  $manifest
     * @return array{flows: list<array<string, mixed>>}
     */
    public function filterForViewer(array $manifest, ?Authorizable $user): array
    {
        if ($user === null) {
            return ['flows' => []];
        }

        $flows = collect($manifest['flows'])
            ->filter(fn (array $flow): bool => $user->can($flow['permission']))
            ->values()
            ->map(function (array $flow, int $index): array {
                $flow['order'] = $index + 1;

                return $flow;
            })
            ->all();

        return ['flows' => $flows];
    }

    /**
     * Enforce §7.1 rule 2: every key the manifest references must exist in both
     * locales, and no Arabic value may be a byte-identical copy of its English
     * one (that is an untranslated placeholder, not a translation).
     *
     * Kept here rather than in the command so it is unit-testable without the
     * translation loader, and so the rule has an implementation rather than only
     * a specification.
     *
     * @param  array{flows: list<array<string, mixed>>}  $manifest
     * @param  array<string, mixed>  $en  contents of lang/en/admin_tutorial.php
     * @param  array<string, mixed>  $ar  contents of lang/ar/admin_tutorial.php
     * @return list<TutorialManifestProblem>
     */
    public function validateTranslations(array $manifest, array $en, array $ar): array
    {
        $flatEn = $this->flatten($en);
        $flatAr = $this->flatten($ar);
        $problems = [];

        foreach ($manifest['flows'] as $flow) {
            $keys = [$flow['title_key']];

            foreach ($flow['steps'] as $step) {
                $keys[] = $step['title_key'];
                $keys[] = $step['body_key'];
            }

            foreach ($keys as $key) {
                // Manifest keys are dotted and prefixed with the file name.
                $lookup = str_starts_with($key, 'admin_tutorial.')
                    ? substr($key, strlen('admin_tutorial.'))
                    : $key;

                $hasEn = array_key_exists($lookup, $flatEn);
                $hasAr = array_key_exists($lookup, $flatAr);

                if (! $hasEn && ! $hasAr) {
                    $problems[] = new TutorialManifestProblem(
                        $flow['id'],
                        "Translation key '{$key}' is referenced by the manifest but missing from both lang/en and lang/ar."
                    );

                    continue;
                }

                if (! $hasAr) {
                    $problems[] = new TutorialManifestProblem(
                        $flow['id'],
                        "Translation key '{$key}' has an English value but no Arabic one."
                    );

                    continue;
                }

                if (! $hasEn) {
                    $problems[] = new TutorialManifestProblem(
                        $flow['id'],
                        "Translation key '{$key}' has an Arabic value but no English one."
                    );

                    continue;
                }

                if ($flatEn[$lookup] === $flatAr[$lookup]) {
                    $problems[] = new TutorialManifestProblem(
                        $flow['id'],
                        "Translation key '{$key}' is byte-identical in both locales — it looks untranslated."
                    );
                }
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $composite = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;

            if (is_array($value)) {
                $flat += $this->flatten($value, $composite);

                continue;
            }

            $flat[$composite] = $value;
        }

        return $flat;
    }

    /** @return list<string> */
    private function flowFiles(): array
    {
        if (! File::isDirectory($this->flowsPath)) {
            $this->problem('catalogue', "Flow directory not found: {$this->flowsPath}");

            return [];
        }

        $files = collect(File::files($this->flowsPath))
            ->filter(fn ($file): bool => $file->getExtension() === 'md')
            // README.md is the index, not a flow.
            ->reject(fn ($file): bool => $file->getFilename() === 'README.md')
            ->map(fn ($file): string => $file->getPathname())
            ->sort()
            ->values()
            ->all();

        if ($files === []) {
            $this->problem('catalogue', "No flow files found in {$this->flowsPath}");
        }

        return $files;
    }

    /** @return array<string, mixed>|null */
    private function parseFlow(string $path): ?array
    {
        $name = basename($path);
        $raw = File::get($path);

        if (! preg_match('/\A---\R(.*?)\R---\R(.*)\z/s', $raw, $matches)) {
            $this->problem($name, 'Missing or malformed front-matter block.');

            return null;
        }

        $front = $this->parseFrontMatter($matches[1], $name);

        if ($front === null) {
            return null;
        }

        $steps = $this->parseSteps($matches[2], $front['id'], $name);

        if ($steps === null) {
            return null;
        }

        return [
            'id' => $front['id'],
            'order' => (int) $front['order'],
            'permission' => $front['permission'],
            'audience' => $front['audience'],
            'estimated_minutes' => (int) $front['estimated_minutes'],
            'title_key' => "admin_tutorial.{$front['id']}.title",
            'steps' => $steps,
        ];
    }

    /** @return array<string, mixed>|null */
    private function parseFrontMatter(string $yaml, string $name): ?array
    {
        try {
            $parsed = Yaml::parse($yaml);
        } catch (\Throwable $e) {
            $this->problem($name, 'Front-matter is not valid YAML: '.$e->getMessage());

            return null;
        }

        if (! is_array($parsed)) {
            $this->problem($name, 'Front-matter did not parse to a mapping.');

            return null;
        }

        $missing = array_values(array_diff(self::REQUIRED_FRONT_MATTER, array_keys($parsed)));

        if ($missing !== []) {
            $this->problem($name, 'Front-matter is missing required key(s): '.implode(', ', $missing));

            return null;
        }

        return $parsed;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function parseSteps(string $body, string $flowId, string $name): ?array
    {
        $sections = preg_split('/^### Step (\d+) — (.*)$/m', $body, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($sections === false || count($sections) < 4) {
            $this->problem($name, 'No "### Step N — Title" headings found.');

            return null;
        }

        // preg_split with DELIM_CAPTURE yields [preamble, num, title, content, num, title, content, …]
        array_shift($sections);

        $steps = [];
        $inheritedUrl = null;
        $slug = str_replace('-', '_', $flowId);

        for ($i = 0; $i < count($sections); $i += 3) {
            $number = (int) $sections[$i];
            $content = $sections[$i + 2] ?? '';

            $url = $this->extractField($content, 'URL');

            // §3.2 rule 3: a step on the page the previous step opened omits URL
            // and inherits it. Step 1 has nothing to inherit from.
            if ($url === null || $url === '' || str_starts_with($url, 'n/a')) {
                if ($number === 1 && ($url === null || $url === '')) {
                    $this->problem($name, 'Step 1 declares no URL, so later steps have nothing to inherit.');

                    return null;
                }
                $url = str_starts_with((string) $url, 'n/a') ? null : $inheritedUrl;
            }

            if ($url !== null) {
                $inheritedUrl = $url;
            }

            $screenshots = $this->resolveScreenshots($content, $number, $slug, $name);

            // A screenshot problem is an *asset* problem, not a structural one:
            // it is recorded so the build still fails, but parsing continues so
            // structural validation of the whole catalogue stays possible before
            // D2 has produced any images. Returning null here would abort the
            // flow at its first step and hide every structural defect behind a
            // missing PNG.
            if ($screenshots === null) {
                $screenshots = ['en' => null, 'ar' => null];
            }

            $steps[] = [
                'n' => $number,
                'url' => $url,
                'title_key' => "admin_tutorial.{$flowId}.step_{$number}.title",
                'body_key' => "admin_tutorial.{$flowId}.step_{$number}.body",
                'screenshot_en' => $screenshots['en'],
                'screenshot_ar' => $screenshots['ar'],
            ];
        }

        $numbers = array_column($steps, 'n');

        if ($numbers !== range(1, count($numbers))) {
            $this->problem($name, 'Step numbers are not contiguous 1..N: got '.implode(',', $numbers));

            return null;
        }

        return $steps;
    }

    /**
     * @return array{en: ?string, ar: ?string}|null
     */
    private function resolveScreenshots(string $content, int $number, string $slug, string $name): ?array
    {
        $declared = $this->extractField($content, 'Screenshot');

        if ($declared === null) {
            $this->problem($name, "Step {$number} declares no Screenshot line. Use `none` for steps with no UI.");

            return null;
        }

        // A step with no UI at all renders text-only (§7.1).
        if (preg_match('/\bnone\b/i', $declared) === 1) {
            return ['en' => null, 'ar' => null];
        }

        $resolved = ['en' => null, 'ar' => null];
        $declaredAny = false;

        foreach (['en', 'ar'] as $locale) {
            $expected = "screenshot_{$number}_{$slug}_{$locale}.png";

            if (! str_contains($declared, $expected)) {
                continue;
            }

            $declaredAny = true;

            // Asset problem, not structural — recorded, but the step still parses
            // so the rest of the catalogue can be validated before D2 has run.
            if (! File::exists($this->screenshotsPath.DIRECTORY_SEPARATOR.$expected)) {
                if (! $this->allowMissingScreenshots) {
                    $this->problem($name, "Step {$number} declares {$expected} but the file does not exist.");
                }

                continue;
            }

            $resolved[$locale] = $expected;
        }

        if (! $declaredAny) {
            $this->problem(
                $name,
                "Step {$number} has a Screenshot line that names no well-formed filename. "
                ."Expected screenshot_{$number}_{$slug}_{en|ar}.png or `none`."
            );

            return null;
        }

        return $resolved;
    }

    private function extractField(string $content, string $label): ?string
    {
        $pattern = '/^- \*\*'.preg_quote($label, '/').':\*\*\s*(.+)$/m';

        if (preg_match($pattern, $content, $m) !== 1) {
            return null;
        }

        $value = trim($m[1]);

        // URL lines may add a human note after the path. Keep only the
        // documented URL so the generated link is always navigable. Screenshot
        // lines intentionally keep both locale filenames.
        if ($label === 'URL' && preg_match('/`([^`]+)`/', $value, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim(str_replace('`', '', $value));
    }

    private function problem(string $file, string $message): void
    {
        $this->problems[] = new TutorialManifestProblem($file, $message);
    }
}
