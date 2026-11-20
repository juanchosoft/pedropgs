<?php

declare(strict_types=1);

namespace Pgs\Migrations;

final class MigrationContext
{
    /** @var list<string> */
    private array $lines = [];
    private bool $hasMore = false;

    public function __construct(private ?int $userId = null, private bool $echo = false)
    {
    }

    public function out(string $line): void
    {
        $this->lines[] = $line;
        if ($this->echo) {
            echo $line . PHP_EOL;
        }
    }

    public function warn(string $line): void
    {
        $this->out('WARN ' . $line);
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function setHasMore(bool $more): void
    {
        $this->hasMore = $more;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    public function output(): string
    {
        return implode("\n", $this->lines);
    }
}
