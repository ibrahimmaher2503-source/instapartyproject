<?php

declare(strict_types=1);

namespace App\Modules\Shared\Console\Commands;

use App\Modules\Shared\Application\DTOs\TutorialManifestProblem;
use App\Modules\Shared\Application\Services\TutorialManifestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Yaml\Yaml;

/**
 * Regenerates storage/app/admin-tutorial/manifest.json from docs/admin-flows/*.md.
 *
 * The .md catalogue is the source of truth; this command is the only writer of
 * the manifest. See docs/admin-tutorial-build-prompt.md §7.1.
 */
class BuildTutorialManifestCommand extends Command
{
    protected $signature = 'admin:build-tutorial-manifest
                            {--check : Validate only; do not write the manifest}
                            {--scaffold-lang : Add missing flow and step keys to lang/{en,ar}/admin_tutorial.php}
                            {--allow-missing-screenshots : Build text-only steps when declared screenshot files are not captured yet}
                            {--strict-screenshots : Fail when declared screenshot files are not captured yet}
                            {--skip-permission-check : Skip verifying front-matter permissions exist (use before shield:generate has run)}';

    protected $description = 'Build the admin tutorial manifest from the docs/admin-flows catalogue';

    public function handle(): int
    {
        $service = new TutorialManifestService(
            flowsPath: base_path('docs/admin-flows'),
            screenshotsPath: base_path('docs/admin-flows/screenshots'),
            allowMissingScreenshots: ! (bool) $this->option('strict-screenshots'),
        );

        $manifest = $service->build();
        $problems = $service->problems();

        if ($this->option('scaffold-lang')) {
            $this->scaffoldTranslations();
            $this->info('Added missing tutorial translation keys to lang/en and lang/ar.');
        }

        $problems = array_merge($problems, $service->validateTranslations(
            $manifest,
            $this->langFile('en'),
            $this->langFile('ar'),
        ));

        if (! $this->option('skip-permission-check')) {
            $problems = array_merge($problems, $this->unknownPermissions($manifest));
        }

        if ($problems !== []) {
            $this->error(sprintf('Tutorial manifest is invalid — %d problem(s):', count($problems)));

            foreach ($problems as $problem) {
                $this->line('  • '.$problem->toString());
            }

            return self::FAILURE;
        }

        $flowCount = count($manifest['flows']);
        $stepCount = array_sum(array_map(fn (array $f): int => count($f['steps']), $manifest['flows']));

        if ($this->option('check')) {
            $this->info("Catalogue is valid: {$flowCount} flow(s), {$stepCount} step(s). Nothing written (--check).");

            return self::SUCCESS;
        }

        $target = storage_path('app/admin-tutorial/manifest.json');
        File::ensureDirectoryExists(dirname($target));
        $this->publishScreenshots();
        File::put($target, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->info("Wrote {$flowCount} flow(s), {$stepCount} step(s) to {$target}");

        return self::SUCCESS;
    }

    private function publishScreenshots(): void
    {
        $source = base_path('docs/admin-flows/screenshots');
        $target = public_path('admin-tutorial/screenshots');

        if (File::isDirectory($source)) {
            File::copyDirectory($source, $target);
        }
    }

    private function scaffoldTranslations(): void
    {
        $english = $this->langFile('en');
        $arabic = $this->langFile('ar');

        foreach (File::files(base_path('docs/admin-flows')) as $file) {
            if ($file->getFilename() === 'README.md' || $file->getExtension() !== 'md') {
                continue;
            }

            $raw = File::get($file->getPathname());
            if (! preg_match('/\A---\R(.*?)\R---\R(.*)\z/s', $raw, $matches)) {
                continue;
            }

            $front = Yaml::parse($matches[1]);
            if (! is_array($front) || empty($front['id'])) {
                continue;
            }

            $id = (string) $front['id'];
            $english[$id]['title'] ??= (string) ($front['title_en'] ?? $id);
            $arabic[$id]['title'] ??= (string) ($front['title_ar'] ?? "شرح {$id}");

            preg_match_all('/^### Step (\d+) — (.*)$/m', $matches[2], $headings, PREG_SET_ORDER);
            foreach ($headings as $heading) {
                $number = (int) $heading[1];
                $title = trim($heading[2]);
                $english[$id]['step_'.$number]['title'] ??= $title;
                $english[$id]['step_'.$number]['body'] ??= "Follow step {$number}: {$title}.";
                $arabic[$id]['step_'.$number]['title'] ??= "الخطوة {$number}: {$title}";
                $arabic[$id]['step_'.$number]['body'] ??= "اتبع الخطوة {$number} ونفّذ الإجراء الموضح في لوحة الإدارة.";
            }
        }

        $this->writeLangFile('en', $english);
        $this->writeLangFile('ar', $arabic);
    }

    /** @param array<string, mixed> $values */
    private function writeLangFile(string $locale, array $values): void
    {
        $path = lang_path("{$locale}/admin_tutorial.php");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($values, true).";\n");
    }

    /**
     * Front-matter permissions must exist, or the flow silently vanishes from
     * every admin's tutorial with no error anywhere (§7.1 rule 4).
     *
     * @return array<string, mixed>
     */
    private function langFile(string $locale): array
    {
        $path = lang_path("{$locale}/admin_tutorial.php");

        if (! File::exists($path)) {
            return [];
        }

        $values = require $path;

        return is_array($values) ? $values : [];
    }

    /**
     * @param  array{flows: list<array<string, mixed>>}  $manifest
     * @return list<TutorialManifestProblem>
     */
    private function unknownPermissions(array $manifest): array
    {
        $declared = collect($manifest['flows'])->pluck('permission')->unique();

        if ($declared->isEmpty()) {
            return [];
        }

        $known = Permission::query()
            ->whereIn('name', $declared->all())
            ->pluck('name')
            ->all();

        return $declared
            ->reject(fn (string $permission): bool => in_array($permission, $known, true))
            ->map(fn (string $permission): TutorialManifestProblem => new TutorialManifestProblem(
                'front-matter',
                "Unknown permission '{$permission}' — not present in the permissions table. Run `php artisan shield:generate --all` and the module permission seeders, or correct the flow's front-matter."
            ))
            ->values()
            ->all();
    }
}
