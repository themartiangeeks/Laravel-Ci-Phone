<?php

namespace Martiangeeks\LaravelCiPhone\Validation;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use Martiangeeks\LaravelCiPhone\Exceptions\InvalidParameterException;
use Martiangeeks\LaravelCiPhone\PhoneNumber;
use Martiangeeks\LaravelCiPhone\Traits\ParsesCountries;
use Martiangeeks\LaravelCiPhone\Traits\ParsesTypes;

class Phone
{
    use ParsesCountries,
        ParsesTypes;

    /**
     * @var PhoneNumberUtil
     */
    protected PhoneNumberUtil $lib;

    /**
     * Phone constructor.
     */
    public function __construct()
    {
        $this->lib = PhoneNumberUtil::getInstance();
    }

    /**
     * Validates a phone number.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @param object $validator
     * @return bool
     * @throws InvalidParameterException
     */
    public function validate(string $attribute, mixed $value, array $parameters, object $validator): bool
    {
        $data = $validator->getData();

        list(
            $countries,
            $types,
            $detect,
            $lenient) = $this->extractParameters($attribute, $parameters, $data);

        // A "null" country is prepended:
        // 1. In case of auto-detection to have the validation run first without supplying a country.
        // 2. In case of lenient validation without provided countries; we still might have some luck...
        if ($detect || ($lenient && empty($countries))) {
            array_unshift($countries, null);
        }

        foreach ($countries as $country) {
            try {
                // Parsing the phone number also validates the country, so no need to do this explicitly.
                // It'll throw a PhoneCountryException upon failure.
                $phoneNumber = PhoneNumber::make($value, $country);

                // Type validation.
                if (! empty($types) && ! $phoneNumber->isOfType($types)) {
                    continue;
                }

                $lenientPhoneNumber = $phoneNumber->lenient()->getPhoneNumberInstance();

                // Lenient validation.
                if ($lenient && $this->lib->isPossibleNumber($lenientPhoneNumber, $country)) {
                    return true;
                }

                $phoneNumberInstance = $phoneNumber->getPhoneNumberInstance();

                // Country detection.
                if ($detect && $this->lib->isValidNumber($phoneNumberInstance)) {
                    return true;
                }

                // Default number+country validation.
                if ($this->lib->isValidNumberForRegion($phoneNumberInstance, $country)) {
                    return true;
                }
            } catch (NumberParseException $e) {
                continue;
            }
        }

        return false;
    }

    /**
     * Parse and extract parameters in the appropriate validation arguments.
     *
     * @param string $attribute
     * @param array $parameters
     * @param array $data
     * @return array
     * @throws InvalidParameterException
     */
    protected function extractParameters(string $attribute, array $parameters, array $data): array
    {
        // Discover if an input field was provided. If not, guess the field's name.
        $inputField = Collection::make($parameters)
            ->intersect(array_keys(Arr::dot($data)))
            ->first() ?: "${attribute}_country";

        // Attempt to retrieve the field's value.
        if ($inputCountry = Arr::get($data, $inputField)) {

            if (static::isValidType($inputField)) {
                throw InvalidParameterException::ambiguous($inputField);
            }

            // Invalid country field values should just validate to false.
            // This will also prevent parameter hijacking through the country field.
            if (static::isValidCountryCode($inputCountry)) {
                $parameters[] = $inputCountry;
            }
        }

        $parameters = array_map('strtolower', $parameters);

        return [
            static::parseCountries($parameters),
            static::parseTypes($parameters),
            in_array('auto', $parameters),
            in_array('lenient', $parameters)
        ];
    }
}
