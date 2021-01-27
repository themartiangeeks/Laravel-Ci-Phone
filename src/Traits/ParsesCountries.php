<?php

namespace Martiangeeks\LaravelCiPhone\Traits;

use Illuminate\Support\Collection;
use League\ISO3166\ISO3166;

trait ParsesCountries
{
    /**
     * Determine whether the given country code is valid.
     *
     * @param string $country
     * @return bool
     */
    public static function isValidCountryCode(string $country): bool
    {
        $iso3166 = new ISO3166;

        try {
            $iso3166->alpha2($country);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Parse the provided phone countries to a valid array.
     *
     * @param string|array $countries
     * @return array
     */
    protected function parseCountries(array|string $countries): array
    {
        return Collection::make(is_array($countries) ? $countries : func_get_args())
            ->map(fn($country) => strtoupper($country))
            ->filter(fn($value) => static::isValidCountryCode($value))->toArray();
    }
}