<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

use Psr\Log\LoggerInterface;
use Vulqen\Sdk\State\StateStore;
use Vulqen\Sdk\Transport\Transport;

/**
 * Wszystko, co host podaje SDK (30). Plik konfiguracyjny frameworka tłumaczy bundle.
 */
final class Options
{
    public const DEFAULT_SDK_NAME = 'vulqen-php';
    public const DEFAULT_MAX_SPANS = 1000;
    public const OTHER_ENVIRONMENT = 'other';

    public readonly string $environment;
    public readonly ?string $release;
    public readonly ?string $serverName;

    public function __construct(
        public readonly ?string $dsn = null,
        string $environment = 'prod',
        ?string $release = null,
        ?string $serverName = null,
        public readonly string $sdkName = self::DEFAULT_SDK_NAME,
        public readonly string $sdkVersion = Version::NUMBER,
        public readonly ?string $projectRoot = null,
        public readonly ?string $stateDir = null,
        public readonly ?LoggerInterface $logger = null,
        public readonly ?Transport $transport = null,
        public readonly ?StateStore $stateStore = null,
        public readonly int $maxSpans = self::DEFAULT_MAX_SPANS,
    ) {
        $this->environment = self::environment($environment);
        $this->release = self::optional($release);
        $this->serverName = self::optional($serverName);
    }

    /**
     * Nazwa środowiska ze wzorca [a-z0-9_-]{1,32} (D-063). Zła nazwa nie może dać 400 na całe envelope.
     */
    private static function environment(string $environment): string
    {
        $normalized = substr((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower(trim($environment))), 0, 32);

        return trim($normalized, '-') === '' ? self::OTHER_ENVIRONMENT : $normalized;
    }

    private static function optional(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
