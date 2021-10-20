<?php

declare(strict_types=1);

namespace BuzzingPixel\BpCache\Entities;

use ArrayAccess;
use Countable;
use Iterator;
use Psr\Cache\CacheItemInterface;

use function array_map;
use function assert;
use function count;

/**
 * @implements ArrayAccess<int, CacheItem>
 * @implements Iterator<int, CacheItem>
 * @codeCoverageIgnore
 */
class CacheItemCollection implements
    ArrayAccess,
    Countable,
    Iterator
{
    private int $position = 0;

    /** @var CacheItem[] */
    private array $cacheItems = [];

    /**
     * @param CacheItemInterface[] $cacheItems
     */
    public function __construct(
        array $cacheItems = [],
    ) {
        array_map(
            [$this, 'addItem'],
            $cacheItems,
        );
    }

    public function addItem(CacheItemInterface $cacheItem): static
    {
        $this->cacheItems[] = CacheItem::fromInterface($cacheItem);

        return $this;
    }

    /**
     * @param int $offset
     *
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return isset($this->cacheItems[$offset]);
    }

    /**
     * @param int $offset
     *
     * @inheritDoc
     */
    public function offsetGet($offset): CacheItem
    {
        return $this->cacheItems[$offset];
    }

    /**
     * @param int $offset
     *
     * @inheritDoc
     *
     * @phpstan-ignore-next-line
     * @psalm-suppress MissingParamType
     */
    public function offsetSet($offset, $value): void
    {
        assert($value instanceof CacheItem);

        $this->cacheItems[$offset] = $value;
    }

    /**
     * @param int $offset
     *
     * @inheritDoc
     */
    public function offsetUnset($offset): void
    {
        unset($this->cacheItems[$offset]);
    }

    public function count(): int
    {
        return count($this->cacheItems);
    }

    public function current(): CacheItem
    {
        return $this->cacheItems[$this->position];
    }

    public function next(): void
    {
        $this->position += 1;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return isset($this->cacheItems[$this->position]);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }
}
