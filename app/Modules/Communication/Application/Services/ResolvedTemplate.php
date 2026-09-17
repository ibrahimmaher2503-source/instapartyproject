<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Services;

use InvalidArgumentException;

readonly class ResolvedTemplate
{
    private const PLACEHOLDER_PATTERN = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/';

    public function __construct(
        public int $templateId,
        public string $body,
        public ?string $subject,
        /** @var array<string, mixed> */
        public array $variables,
    ) {}

    /** @param array<string, mixed> $context
     * @return array{body: string, subject: string|null}
     */
    public function render(array $context): array
    {
        $replace = static function (array $match) use ($context): string {
            $key = $match[1];
            if (! array_key_exists($key, $context) || ! is_scalar($context[$key])) {
                throw new InvalidArgumentException("Missing or invalid notification variable: {$key}");
            }

            return (string) $context[$key];
        };

        return [
            'body' => preg_replace_callback(self::PLACEHOLDER_PATTERN, $replace, $this->body) ?? $this->body,
            'subject' => $this->subject === null ? null : preg_replace_callback(self::PLACEHOLDER_PATTERN, $replace, $this->subject),
        ];
    }

    /** @return list<string> */
    public static function variableNames(string ...$templates): array
    {
        $names = [];

        foreach ($templates as $template) {
            preg_match_all(self::PLACEHOLDER_PATTERN, $template, $matches);
            $names = [...$names, ...$matches[1]];
        }

        return array_values(array_unique($names));
    }
}
