<?php

use App\Support\Format;
use Carbon\CarbonImmutable;

it('uses French and the Benin timezone as the application defaults', function () {
    expect(config('app.locale'))->toBe('fr')
        ->and(config('app.fallback_locale'))->toBe('fr')
        ->and(config('app.faker_locale'))->toBe('fr_FR')
        ->and(config('app.timezone'))->toBe('Africa/Porto-Novo')
        ->and(date_default_timezone_get())->toBe('Africa/Porto-Novo')
        ->and(now()->timezoneName)->toBe('Africa/Porto-Novo');
});

it('formats calendar dates and local timestamps consistently for Benin', function () {
    $utcTimestamp = CarbonImmutable::parse('2026-10-04 23:15:00', 'UTC');

    expect(Format::date($utcTimestamp))->toBe('04/10/2026')
        ->and(Format::dateTime($utcTimestamp))->toBe('05/10/2026 à 00:15')
        ->and(Format::month(CarbonImmutable::parse('2026-10-01')))->toBe('octobre 2026');
});
