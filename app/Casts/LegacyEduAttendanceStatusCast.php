<?php

namespace App\Casts;

use App\Enums\EduAttendanceStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

// TODO: Remove this cast and switch to native enum casting (EduAttendanceStatus::class)
// once legacy values have been fully removed from the database.

/**
 * @deprecated Temporary workaround for legacy attendance values (e.g. "late", "absent").
 */
class LegacyEduAttendanceStatusCast implements CastsAttributes
{
    public static EduAttendanceStatus $defaultFallback = EduAttendanceStatus::Cancelled;

    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): EduAttendanceStatus
    {
        return match ($value) {
            'late' => EduAttendanceStatus::Leave,
            'absent' => EduAttendanceStatus::Cancelled,
            default => EduAttendanceStatus::tryFrom($value) ?? static::$defaultFallback,
        };
    }

    /**
     * Prepare the given value for storage.
     *
     * Only the three canonical values (present, leave, cancelled) are accepted.
     * Passing a legacy value (e.g. 'late', 'absent') or any other string will
     * throw an InvalidArgumentException to prevent polluting the database with
     * values that the cast will not be able to round-trip correctly.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value instanceof EduAttendanceStatus) {
            return $value->value;
        }

        $resolved = EduAttendanceStatus::tryFrom((string) $value);

        if ($resolved === null) {
            throw new InvalidArgumentException(
                "Invalid attendance value '{$value}'. Allowed values: "
                .implode(', ', array_column(EduAttendanceStatus::cases(), 'value'))
            );
        }

        return $resolved->value;
    }
}
