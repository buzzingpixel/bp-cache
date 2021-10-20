<?php

declare(strict_types=1);

namespace BuzzingPixel\BpCache\Shared;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

use function assert;

/**
 * @codeCoverageIgnore
 */
class DateTimeUtility
{
    public static function createDateTimeImmutableOrNull(
        null | string | DateTimeInterface $dateTime,
    ): ?DateTimeImmutable {
        if ($dateTime === null) {
            return null;
        }

        return self::createDateTimeImmutable(dateTime: $dateTime);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public static function createDateTimeImmutable(
        null | string | DateTimeInterface $dateTime,
    ): DateTimeImmutable {
        if ($dateTime === null) {
            return new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            );
        }

        if ($dateTime instanceof DateTimeInterface) {
            $class = DateTimeImmutable::createFromFormat(
                DateTimeInterface::ATOM,
                $dateTime->format(DateTimeInterface::ATOM),
            );
        } else {
            $class = DateTimeImmutable::createFromFormat(
                DateTimeInterface::ATOM,
                $dateTime,
            );
        }

        assert($class instanceof DateTimeImmutable);

        return $class->setTimezone(
            new DateTimeZone('UTC')
        );
    }
}
