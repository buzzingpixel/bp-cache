<?php

declare(strict_types=1);

namespace BuzzingPixel\BpCache\Entities;

use BuzzingPixel\BpCache\Shared\DateTimeUtility;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Psr\Cache\CacheItemInterface;

use function method_exists;
use function time;

/**
 * @codeCoverageIgnore
 */
class CacheItem implements CacheItemInterface
{
    public static function fromInterface(CacheItemInterface $cacheItem): self
    {
        /**
         * This inspection is not correct here...
         *
         * @noinspection PhpConditionAlreadyCheckedInspection
         */
        if ($cacheItem instanceof CacheItem) {
            return $cacheItem;
        }

        /** @psalm-suppress MixedArgument */
        return new self(
            key: $cacheItem->getKey(),
            value: $cacheItem->get(),
            expiresAt: method_exists(
                $cacheItem,
                'expires'
            ) ? $cacheItem->expires() : null,
            isHit: $cacheItem->isHit(),
        );
    }

    private ?DateTimeImmutable $expiresAt;

    public function __construct(
        private string $key,
        private mixed $value = null,
        null | string | DateTimeInterface $expiresAt = null,
        private bool $isHit = false,
    ) {
        $this->expiresAt = DateTimeUtility::createDateTimeImmutableOrNull(
            dateTime: $expiresAt,
        );
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiresAt = DateTimeUtility::createDateTimeImmutableOrNull(
            dateTime: $expiration,
        );

        return $this;
    }

    public function expiresAfter(DateInterval|int|null $time): static
    {
        if ($time === null) {
            $this->expiresAt = null;

            return $this;
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $expires = (new DateTimeImmutable(
            'now',
            new DateTimeZone('UTC'),
        ));

        if ($time instanceof DateInterval) {
            $expires = $expires->add($time);

            $this->expiresAt = $expires;

            return $this;
        }

        $expires = $expires->setTimestamp(time() + $time);

        $this->expiresAt = $expires;

        return $this;
    }

    public function expires(): ?DateTimeInterface
    {
        return $this->expiresAt;
    }
}
