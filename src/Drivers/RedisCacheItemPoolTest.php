<?php

declare(strict_types=1);

namespace BuzzingPixel\BpCache\Drivers;

use BuzzingPixel\BpCache\Entities\CacheItem;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;
use Redis;

use function serialize;
use function unserialize;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 * @psalm-suppress MixedArrayAccess
 * @psalm-suppress UndefinedInterfaceMethod
 * @psalm-suppress DocblockTypeContradiction
 * @psalm-suppress MixedMethodCall
 */
class RedisCacheItemPoolTest extends TestCase
{
    /** @var MockObject&Redis */
    private mixed $redis;

    /** @var mixed[] */
    private array $redisCalls = [];

    private mixed $redisGetReturn = false;

    private mixed $redisExistsReturn = false;

    private int $redisTtlReturn = 0;

    /** @var mixed[] */
    private array $redisKeysReturn = [];

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    protected function setUp(): void
    {
        parent::setUp();

        $this->redisCalls = [];

        $this->redisGetReturn = false;

        $this->redisTtlReturn = 0;

        $this->redisKeysReturn = [];

        $this->redis = $this->createMock(Redis::class);

        $this->redis->method('get')->willReturnCallback(
            function (string $key): mixed {
                $this->redisCalls[] = [
                    'method' => 'get',
                    'key' => $key,
                ];

                return $this->redisGetReturn;
            }
        );

        $this->redis->method('ttl')->willReturnCallback(
            function (string $key): mixed {
                $this->redisCalls[] = [
                    'method' => 'ttl',
                    'key' => $key,
                ];

                return $this->redisTtlReturn;
            }
        );

        $this->redis->method('exists')->willReturnCallback(
            function (string $key): mixed {
                $this->redisCalls[] = [
                    'method' => 'exists',
                    'key' => $key,
                ];

                return $this->redisExistsReturn;
            }
        );

        $this->redis->method('keys')->willReturnCallback(
            function (string $key): mixed {
                $this->redisCalls[] = [
                    'method' => 'keys',
                    'key' => $key,
                ];

                return $this->redisKeysReturn;
            }
        );

        $this->redis->method('del')->willReturnCallback(
            function (string|array $key): mixed {
                $this->redisCalls[] = [
                    'method' => 'del',
                    'key' => $key,
                ];

                return 1;
            }
        );

        $this->redis->method('setex')->willReturnCallback(
            function (string $key, int $ttl, string $value): mixed {
                $this->redisCalls[] = [
                    'method' => 'setex',
                    'key' => $key,
                    'ttl' => $ttl,
                    'value' => $value,
                ];

                return true;
            }
        );

        $this->redis->method('set')->willReturnCallback(
            function (string $key, string $value): mixed {
                $this->redisCalls[] = [
                    'method' => 'set',
                    'key' => $key,
                    'value' => $value,
                ];

                return true;
            }
        );
    }

    /**
     * @throws Exception
     */
    public function testGetItemWhenRedisDoesNotHaveItem(): void
    {
        $cacheItemPool = new RedisCacheItemPool(
            redis: $this->redis,
            redisKeyPrefix: 'TestCachePrefix/',
        );

        $cacheItem = $cacheItemPool->getItem('testKey');

        self::assertSame(
            'testKey',
            $cacheItem->getKey(),
        );

        self::assertNull($cacheItem->get());

        self::assertFalse($cacheItem->isHit());

        /** @phpstan-ignore-next-line */
        self::assertNull($cacheItem->expires());

        self::assertCount(
            1,
            $this->redisCalls,
        );

        self::assertSame(
            'get',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'TestCachePrefix/testKey',
            $this->redisCalls[0]['key'],
        );
    }

    /**
     * @throws Exception
     */
    public function testGetItemWhenNoTtl(): void
    {
        $returnItem = ['test', 'foo', 'bar'];

        $this->redisGetReturn = serialize($returnItem);

        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        $cacheItem = $cacheItemPool->getItem('a-key');

        self::assertSame(
            'a-key',
            $cacheItem->getKey(),
        );

        self::assertSame($returnItem, $cacheItem->get());

        self::assertTrue($cacheItem->isHit());

        /** @phpstan-ignore-next-line */
        self::assertNull($cacheItem->expires());

        self::assertCount(
            2,
            $this->redisCalls,
        );

        self::assertSame(
            'get',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:a-key',
            $this->redisCalls[0]['key'],
        );

        self::assertSame(
            'ttl',
            $this->redisCalls[1]['method'],
        );

        self::assertSame(
            'BpCache:a-key',
            $this->redisCalls[1]['key'],
        );
    }

    /**
     * @throws Exception
     *
     * @psalm-suppress DocblockTypeContradiction
     */
    public function testGetItemWhenItemHasTtl(): void
    {
        $returnItem = ['test'];

        $this->redisGetReturn = serialize($returnItem);

        $this->redisTtlReturn = 500;

        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        $cacheItem = $cacheItemPool->getItem('foo');

        self::assertSame(
            'foo',
            $cacheItem->getKey(),
        );

        self::assertSame($returnItem, $cacheItem->get());

        self::assertTrue($cacheItem->isHit());

        $expiresComparison = (new DateTimeImmutable(
            'now',
            new DateTimeZone('UTC'),
        ))->add(new DateInterval(
            'PT500S',
        ));

        self::assertSame(
            $expiresComparison->format(
                DateTimeInterface::ATOM
            ),
            /** @phpstan-ignore-next-line */
            $cacheItem->expires()->format(
                DateTimeInterface::ATOM
            ),
        );

        self::assertCount(
            2,
            $this->redisCalls,
        );

        self::assertSame(
            'get',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:foo',
            $this->redisCalls[0]['key'],
        );

        self::assertSame(
            'ttl',
            $this->redisCalls[1]['method'],
        );

        self::assertSame(
            'BpCache:foo',
            $this->redisCalls[1]['key'],
        );
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     *
     * @psalm-suppress DocblockTypeContradiction
     */
    public function testGetItems(): void
    {
        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        $cacheItems = $cacheItemPool->getItems(['foo', 'bar']);

        self::assertCount(2, $cacheItems);

        /**
         * @phpstan-ignore-next-line
         * @psalm-suppress MixedAssignment
         * @psalm-suppress InvalidArrayAccess
         */
        $cacheItem0 = $cacheItems[0];

        self::assertSame(
            'foo',
            $cacheItem0->getKey(),
        );

        self::assertNull($cacheItem0->get());

        self::assertFalse($cacheItem0->isHit());

        self::assertNull($cacheItem0->expires());

        /**
         * @phpstan-ignore-next-line
         * @psalm-suppress MixedAssignment
         * @psalm-suppress InvalidArrayAccess
         */
        $cacheItem1 = $cacheItems[1];

        self::assertSame(
            'bar',
            $cacheItem1->getKey(),
        );

        self::assertNull($cacheItem1->get());

        self::assertFalse($cacheItem1->isHit());

        self::assertNull($cacheItem1->expires());

        self::assertCount(
            2,
            $this->redisCalls,
        );

        self::assertSame(
            'get',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:foo',
            $this->redisCalls[0]['key'],
        );

        self::assertSame(
            'get',
            $this->redisCalls[1]['method'],
        );

        self::assertSame(
            'BpCache:bar',
            $this->redisCalls[1]['key'],
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testHasItemWhenNoItem1(): void
    {
        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        self::assertFalse($cacheItemPool->hasItem('testItem'));

        self::assertCount(1, $this->redisCalls);

        self::assertSame(
            'exists',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:testItem',
            $this->redisCalls[0]['key'],
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testHasItemWhenNoItem2(): void
    {
        $this->redisExistsReturn = 0;

        $cacheItemPool = new RedisCacheItemPool(
            redis: $this->redis,
            redisKeyPrefix: 'test-prefix:',
        );

        self::assertFalse($cacheItemPool->hasItem('testItem'));

        self::assertCount(1, $this->redisCalls);

        self::assertSame(
            'exists',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'test-prefix:testItem',
            $this->redisCalls[0]['key'],
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testHasItem1(): void
    {
        $this->redisExistsReturn = true;

        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        self::assertTrue($cacheItemPool->hasItem('testItem'));

        self::assertCount(1, $this->redisCalls);

        self::assertSame(
            'exists',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:testItem',
            $this->redisCalls[0]['key'],
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testHasItem2(): void
    {
        $this->redisExistsReturn = 3;

        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        self::assertTrue($cacheItemPool->hasItem('testItem'));

        self::assertCount(1, $this->redisCalls);

        self::assertSame(
            'exists',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:testItem',
            $this->redisCalls[0]['key'],
        );
    }

    public function testClearWhenNoKeys(): void
    {
        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        self::assertTrue($cacheItemPool->clear());

        self::assertCount(1, $this->redisCalls);

        self::assertSame(
            'keys',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:*',
            $this->redisCalls[0]['key'],
        );
    }

    public function testClear(): void
    {
        $this->redisKeysReturn = ['foo', 'bar'];

        $cacheItemPool = new RedisCacheItemPool(
            redis: $this->redis,
            redisKeyPrefix: 'test-prefix/',
        );

        self::assertTrue($cacheItemPool->clear());

        self::assertCount(3, $this->redisCalls);

        self::assertSame(
            'keys',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'test-prefix/*',
            $this->redisCalls[0]['key']
        );

        self::assertSame(
            'del',
            $this->redisCalls[1]['method'],
        );

        self::assertSame(
            'foo',
            $this->redisCalls[1]['key'],
        );

        self::assertSame(
            'del',
            $this->redisCalls[2]['method'],
        );

        self::assertSame(
            'bar',
            $this->redisCalls[2]['key'],
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testDeleteItem(): void
    {
        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        self::assertTrue($cacheItemPool->deleteItem('fooBar'));

        self::assertCount(1, $this->redisCalls);

        self::assertSame(
            'del',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:fooBar',
            $this->redisCalls[0]['key'],
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testDeleteItems(): void
    {
        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        self::assertTrue($cacheItemPool->deleteItems([
            'foo',
            'bar',
        ]));

        self::assertCount(1, $this->redisCalls);

        self::assertSame(
            'del',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            [
                'BpCache:foo',
                'BpCache:bar',
            ],
            $this->redisCalls[0]['key'],
        );
    }

    /**
     * @throws Exception
     *
     * @psalm-suppress MixedArgument
     */
    public function testSaveWhenExpires(): void
    {
        $expires = (new DateTimeImmutable(
            'now',
            new DateTimeZone('UTC'),
        ))->add(new DateInterval(
            'PT500S',
        ));

        $itemStub = new CacheItem(
            key: 'testKey',
            value: 'testValue',
            expiresAt: $expires,
            isHit: true,
        );

        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        self::assertTrue($cacheItemPool->save($itemStub));

        self::assertCount(1, $this->redisCalls);

        self::assertSame(
            'setex',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:testKey',
            $this->redisCalls[0]['key'],
        );

        self::assertSame(
            500,
            $this->redisCalls[0]['ttl'],
        );

        self::assertSame(
            'testValue',
            unserialize($this->redisCalls[0]['value']),
        );
    }

    /**
     * @throws Exception
     *
     * @psalm-suppress MixedArgument
     */
    public function testSaveWhenDoesNotExpire(): void
    {
        $itemStub = $this->createMock(
            CacheItemInterface::class,
        );

        $itemStub->method('getKey')->willReturn('testKey');

        $itemStub->method('get')->willReturn('testValue');

        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        self::assertTrue($cacheItemPool->save($itemStub));

        self::assertCount(1, $this->redisCalls);

        self::assertSame(
            'set',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:testKey',
            $this->redisCalls[0]['key'],
        );

        self::assertSame(
            'testValue',
            unserialize($this->redisCalls[0]['value']),
        );
    }

    /**
     * @throws Exception
     *
     * @psalm-suppress MixedArgument
     */
    public function testSaveDeferredCommit(): void
    {
        $item1Stub = $this->createMock(
            CacheItemInterface::class,
        );

        $item1Stub->method('getKey')->willReturn('testKey');

        $item1Stub->method('get')->willReturn('testValue');

        $item2Stub = $this->createMock(
            CacheItemInterface::class,
        );

        $item2Stub->method('getKey')->willReturn('testKey2');

        $item2Stub->method('get')->willReturn('testValue2');

        $cacheItemPool = new RedisCacheItemPool(redis: $this->redis);

        self::assertTrue(
            $cacheItemPool->saveDeferred($item1Stub),
        );

        self::assertTrue(
            $cacheItemPool->saveDeferred($item2Stub),
        );

        self::assertTrue($cacheItemPool->commit());

        self::assertCount(2, $this->redisCalls);

        self::assertSame(
            'set',
            $this->redisCalls[0]['method'],
        );

        self::assertSame(
            'BpCache:testKey',
            $this->redisCalls[0]['key'],
        );

        self::assertSame(
            'testValue',
            unserialize($this->redisCalls[0]['value']),
        );

        self::assertSame(
            'set',
            $this->redisCalls[1]['method'],
        );

        self::assertSame(
            'BpCache:testKey2',
            $this->redisCalls[1]['key'],
        );

        self::assertSame(
            'testValue2',
            unserialize($this->redisCalls[1]['value']),
        );
    }
}
