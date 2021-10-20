<?php

declare(strict_types=1);

namespace BuzzingPixel\BpCache\Drivers;

use BuzzingPixel\BpCache\Entities\CacheItem;
use BuzzingPixel\BpCache\Entities\CacheItemCollection;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Redis;

use function array_map;
use function method_exists;
use function serialize;
use function unserialize;

class RedisCacheItemPool implements CacheItemPoolInterface
{
    /** @var CacheItemInterface[] */
    private array $deferred = [];

    public function __construct(
        private Redis $redis,
        private string $redisKeyPrefix = 'BpCache:',
    ) {
    }

    public function redisKeyPrefix(): string
    {
        return $this->redisKeyPrefix;
    }

    public function prefixKeyForRedis(string $key): string
    {
        return $this->redisKeyPrefix() . $key;
    }

    /**
     * @throws Exception
     */
    public function getItem(string $key): CacheItemInterface
    {
        $redisKey = $this->prefixKeyForRedis(key: $key);

        $redisItem = $this->redis->get($this->prefixKeyForRedis(key: $key));

        if ($redisItem === false) {
            return new CacheItem(key: $key);
        }

        $redisTtl = $this->redis->ttl($redisKey);

        return new CacheItem(
            key: $key,
            value: unserialize($redisItem),
            expiresAt: $redisTtl > 0 ? (new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            ))->add(new DateInterval(
                'PT' . $redisTtl . 'S',
            )) : null,
            isHit: true,
        );
    }

    /**
     * @return iterable<int, CacheItemInterface>
     *
     * @throws Exception
     *
     * @inheritDoc
     */
    public function getItems(array $keys = []): iterable
    {
        return new CacheItemCollection(array_map(
            fn (string $k) => $this->getItem(key: $k),
            $keys,
        ));
    }

    public function hasItem(string $key): bool
    {
        return (bool) $this->redis->exists(
            $this->prefixKeyForRedis(key: $key)
        );
    }

    public function clear(): bool
    {
        $items = $this->redis->keys(
            $this->prefixKeyForRedis(key: '*')
        );

        array_map(
            [$this->redis, 'del'],
            $items,
        );

        return true;
    }

    public function deleteItem(string $key): bool
    {
        $redisKey = $this->prefixKeyForRedis($key);

        return $this->redis->del($redisKey) === 1;
    }

    /**
     * @inheritDoc
     */
    public function deleteItems(array $keys): bool
    {
        $redisKeys = array_map(
            [$this, 'prefixKeyForRedis'],
            $keys,
        );

        return $this->redis->del($redisKeys) === 1;
    }

    /**
     * @throws Exception
     *
     * @inheritDoc
     */
    public function save(CacheItemInterface $item): bool
    {
        $expires = method_exists($item, 'expires') ?
            $item->expires() :
            null;

        $value = serialize($item->get());

        if ($expires !== null) {
            $currentTime = new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC')
            );

            /**
             * @phpstan-ignore-next-line
             * @psalm-suppress MixedArgument
             * @psalm-suppress MixedOperand
             * @psalm-suppress MixedMethodCall
             */
            return $this->redis->setex(
                $this->prefixKeyForRedis($item->getKey()),
                $expires->getTimestamp() - $currentTime->getTimestamp(),
                $value,
            );
        }

        return $this->redis->set(
            $this->prefixKeyForRedis($item->getKey()),
            $value,
        );
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->deferred[] = $item;

        return true;
    }

    /**
     * @throws Exception
     *
     * @inheritDoc
     */
    public function commit(): bool
    {
        array_map(
            fn (CacheItemInterface $i) => $this->save($i),
            $this->deferred,
        );

        $this->deferred = [];

        return true;
    }
}
