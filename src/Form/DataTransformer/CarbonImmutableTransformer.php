<?php

// src/Form/DataTransformer/CarbonImmutableTransformer.php
declare(strict_types=1);

namespace App\Form\DataTransformer;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Symfony\Component\Form\DataTransformerInterface;
use UnexpectedValueException;

/**
 * @implements DataTransformerInterface<CarbonImmutable|null, DateTimeInterface|string|null>
 */
final class CarbonImmutableTransformer implements DataTransformerInterface
{
    /**
     * Model (CarbonImmutable|null) -> View (\DateTimeInterface|null)
     */
    public function transform($value): ?DateTimeInterface
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            // CarbonImmutable extends DateTimeImmutable, this is already fine for the form view.
            return $value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or DateTimeInterface, got %s',
            get_debug_type($value)
        ));
    }

    /**
     * View (\DateTimeInterface|string|null) -> Model (CarbonImmutable|null)
     */
    public function reverseTransform($value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            // Preserve the exact instant; Carbon clones it as immutable.
            return CarbonImmutable::instance($value);
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value);
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null, string, or DateTimeInterface, got %s',
            get_debug_type($value)
        ));
    }
}
