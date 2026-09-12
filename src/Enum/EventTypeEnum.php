<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What kind of thing is happening.
 *
 * Two shapes hide behind the five cases. A SOCIAL is recurring: it has a weekly
 * or monthly schedule and no start date, and it is listed on its city page for
 * as long as somebody confirms it is still running. Everything else is dated
 * and drops off the calendar once it has passed.
 */
enum EventTypeEnum: string
{
    case FESTIVAL = 'festival';
    case WEEKENDER = 'weekender';
    case WORKSHOP = 'workshop';
    case PARTY = 'party';
    case SOCIAL = 'social';

    public function isRecurring(): bool
    {
        return $this === self::SOCIAL;
    }

    /**
     * Translation key. The catalogue is where UI copy lives.
     */
    public function label(): string
    {
        return 'event.type.' . $this->value;
    }

    /**
     * @return list<self>
     */
    public static function dated(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type): bool => ! $type->isRecurring()));
    }

    /**
     * The schema.org type. There is no "festival" or "social" there; the closest
     * honest ancestor is DanceEvent for all of them.
     */
    public function schemaType(): string
    {
        return 'DanceEvent';
    }
}
