<?php

use libphonenumber\NumberParseException;
use Martiangeeks\LaravelCiPhone\Exceptions\NumberFormatException;
use Martiangeeks\LaravelCiPhone\PhoneNumber;

if (! function_exists('ciPhone')) {
    /**
     * Get a PhoneNumber instance or a formatted string.
     *
     * @param string $number
     * @param array $country
     * @param null $format
     * @return string|Martiangeeks\LaravelCiPhone\PhoneNumber
     * @throws NumberFormatException
     * @throws NumberParseException
     */
    function ciPhone(string $number, $country = [], $format = null): PhoneNumber|string
    {
        $phone = PhoneNumber::make($number, $country);

        if (! is_null($format)) {
            return $phone->format($format);
        }

        return $phone;
    }
}