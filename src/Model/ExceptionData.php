<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Model;

use Vulqen\Sdk\Scrubber;

final class ExceptionData
{
    public const MAX_PREVIOUS = 5;
    public const MAX_FRAMES = 50;
    public const MAX_MESSAGE = 2048;

    /**
     * @param list<Frame> $frames
     */
    public function __construct(
        public readonly string $class,
        public readonly string $message,
        public readonly array $frames,
        public readonly ?self $previous,
    ) {
    }

    /**
     * Klatki od miejsca rzucenia w górę stosu. Ścieżki względne wobec $projectRoot, previous do głębokości 5.
     */
    public static function fromThrowable(\Throwable $throwable, ?string $projectRoot, int $depth = 0): self
    {
        $previous = $throwable->getPrevious();

        return new self(
            $throwable::class,
            mb_substr(Scrubber::scrub($throwable->getMessage()), 0, self::MAX_MESSAGE),
            self::frames($throwable, $projectRoot),
            $previous !== null && $depth < self::MAX_PREVIOUS ? self::fromThrowable($previous, $projectRoot, $depth + 1) : null,
        );
    }

    /**
     * @return list<Frame>
     */
    private static function frames(\Throwable $throwable, ?string $projectRoot): array
    {
        $trace = $throwable->getTrace();
        $frames = [Frame::at($throwable->getFile(), $throwable->getLine(), $trace[0] ?? null, $projectRoot)];

        foreach ($trace as $index => $call) {
            if (count($frames) >= self::MAX_FRAMES) {
                break;
            }
            $frames[] = Frame::at($call['file'] ?? null, $call['line'] ?? 0, $trace[$index + 1] ?? null, $projectRoot);
        }

        return $frames;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'message' => $this->message,
            'frames' => array_map(static fn (Frame $frame): array => $frame->toArray(), $this->frames),
            'previous' => $this->previous?->toArray(),
        ];
    }
}
