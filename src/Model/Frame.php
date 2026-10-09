<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Model;

final class Frame
{
    public const INTERNAL = '[internal]';

    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly ?string $class,
        public readonly ?string $function,
        public readonly bool $inApp,
    ) {
    }

    /**
     * Linia $file należy do funkcji z następnego wpisu getTrace(), stąd osobny $caller.
     *
     * @param array{class?: class-string|string, function?: string}|null $caller
     */
    public static function at(?string $file, int $line, ?array $caller, ?string $projectRoot): self
    {
        if ($file === null || $file === '') {
            return new self(self::INTERNAL, 0, $caller['class'] ?? null, $caller['function'] ?? null, false);
        }

        return new self(
            self::relative($file, $projectRoot),
            max(0, $line),
            $caller['class'] ?? null,
            $caller['function'] ?? null,
            !str_contains($file, '/vendor/') && !str_contains($file, '\\vendor\\'),
        );
    }

    private static function relative(string $file, ?string $projectRoot): string
    {
        $file = str_replace('\\', '/', $file);
        if ($projectRoot === null || $projectRoot === '') {
            return $file;
        }
        $root = rtrim(str_replace('\\', '/', $projectRoot), '/').'/';

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $frame = ['file' => $this->file, 'line' => $this->line];
        if ($this->class !== null) {
            $frame['class'] = $this->class;
        }
        if ($this->function !== null) {
            $frame['function'] = $this->function;
        }
        $frame['in_app'] = $this->inApp;

        return $frame;
    }
}
