<?php
declare(strict_types=1);

final class SchemaCompatibilityReproTest extends \Illuminate\Foundation\Testing\TestCase
{
    public function createApplication()
    {
        if (getenv('APP_ENV') !== 'testing' || ! str_starts_with((string) getenv('DB_DATABASE'), 'instaparty_qa_')
            || ! str_starts_with((string) getenv('DB_SOCKET'), '/tmp/iqa-mysql-')) throw new RuntimeException('Disposable schema reproduction only');
        return parent::createApplication();
    }

    public function test_independent_repro_RQA_003_current_migrations_must_bootstrap_empty_MySQL(): void
    {
        $engine = \Illuminate\Support\Facades\DB::selectOne('SELECT VERSION() AS value')->value;
        self::assertFalse(str_contains($engine, 'MariaDB'), 'This repro requires actual MySQL');
        $exception = null;
        try { $exit = \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]); }
        catch (Throwable $error) { $exit = 1; $exception = $error->getMessage(); }
        $output = \Illuminate\Support\Facades\Artisan::output();
        file_put_contents(getenv('QA_OUTPUT').'/RQA-003.json', json_encode(['engine' => $engine, 'database' => getenv('DB_DATABASE'), 'command' => 'migrate:fresh --force --no-interaction', 'expected_exit' => 0, 'actual_exit' => $exit, 'exception' => $exception, 'output' => $output], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        self::assertSame(0, $exit, 'Current migrations must create an empty MySQL testing schema');
    }
}
