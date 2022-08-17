<?php

declare(strict_types=1);

namespace Pint\LimitToPages\Event;

final class AfterLimitToPagesGeneratedEvent
{
    private array $configuration;

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }
}
