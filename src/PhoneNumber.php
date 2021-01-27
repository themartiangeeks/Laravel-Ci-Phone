<?php

namespace Martiangeeks\LaravelCiPhone;

use Exception;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use JsonSerializable;
use libphonenumber\NumberParseException as libNumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Martiangeeks\LaravelCiPhone\Exceptions\CountryCodeException;
use Martiangeeks\LaravelCiPhone\Exceptions\NumberFormatException;
use Martiangeeks\LaravelCiPhone\Exceptions\NumberParseException;
use Martiangeeks\LaravelCiPhone\Traits\ParsesCountries;
use Martiangeeks\LaravelCiPhone\Traits\ParsesFormats;
use Martiangeeks\LaravelCiPhone\Traits\ParsesTypes;
use Serializable;

class PhoneNumber implements Jsonable, JsonSerializable, Serializable
{
    use Macroable,
        ParsesCountries,
        ParsesFormats,
        ParsesTypes;

    /**
     * The provided phone number.
     *
     * @var string
     */
    protected string $number;

    /**
     * The provided phone country.
     *
     * @var array
     */
    protected array $countries = [];

    /**
     * The detected phone country.
     *
     * @var string
     */
    protected string $country;

    /**
     * Whether to allow lenient checks (i.e. landline numbers without area codes).
     *
     * @var bool
     */
    protected bool $lenient = false;

    /**
     * @var PhoneNumberUtil
     */
    protected PhoneNumberUtil $lib;

    /**
     * Phone constructor.
     *
     * @param string $number
     */
    public function __construct(string $number)
    {
        $this->number = $number;
        $this->lib = PhoneNumberUtil::getInstance();
    }

    /**
     * Create a phone instance.
     *
     * @param string $number
     * @param array $country
     * @return static
     */
    public static function make(string $number, $country = []): static
    {
        $instance = new static($number);

        return $instance->ofCountry($country);
    }

    /**
     * Set the country to which the phone number belongs to.
     *
     * @param string|array $country
     * @return static
     */
    public function ofCountry(array|string $country): PhoneNumber|static
    {
        $countries = is_array($country) ? $country : func_get_args();

        $instance = clone $this;
        $instance->countries = array_unique(
            array_merge($instance->countries, static::parseCountries($countries))
        );

        return $instance;
    }

    /**
     * Format the phone number in international format.
     *
     * @return string
     * @throws NumberFormatException|libNumberParseException
     */
    public function formatInternational(): string
    {
        return $this->format(PhoneNumberFormat::INTERNATIONAL);
    }

    /**
     * Format the phone number in national format.
     *
     * @return string
     * @throws NumberFormatException|libNumberParseException
     */
    public function formatNational(): string
    {
        return $this->format(PhoneNumberFormat::NATIONAL);
    }

    /**
     * Format the phone number in E164 format.
     *
     * @return string
     * @throws NumberFormatException|libNumberParseException
     */
    public function formatE164(): string
    {
        return $this->format(PhoneNumberFormat::E164);
    }

    /**
     * Format the phone number in RFC3966 format.
     *
     * @return string
     * @throws NumberFormatException|libNumberParseException
     */
    public function formatRFC3966(): string
    {
        return $this->format(PhoneNumberFormat::RFC3966);
    }

    /**
     * Format the phone number in a given format.
     *
     * @param string|int $format
     * @return string
     * @throws NumberFormatException|libNumberParseException
     */
    public function format(int|string $format): string
    {
        $parsedFormat = static::parseFormat($format);

        if (is_null($parsedFormat)) {
            throw NumberFormatException::invalid($format);
        }

        return $this->lib->format(
            $this->getPhoneNumberInstance(),
            $parsedFormat
        );
    }

    /**
     * Format the phone number in a way that it can be dialled from the provided country.
     *
     * @param string $country
     * @return string
     * @throws CountryCodeException|libNumberParseException
     */
    public function formatForCountry(string $country): string
    {
        if (! static::isValidCountryCode($country)) {
            throw CountryCodeException::invalid($country);
        }

        return $this->lib->formatOutOfCountryCallingNumber(
            $this->getPhoneNumberInstance(),
            $country
        );
    }

    /**
     * Format the phone number in a way that it can be dialled from the provided country using a cellphone.
     *
     * @param string $country
     * @param bool $withFormatting
     * @return string
     * @throws CountryCodeException|libNumberParseException
     */
    public function formatForMobileDialingInCountry(string $country, $withFormatting = false): string
    {
        if (! static::isValidCountryCode($country)) {
            throw CountryCodeException::invalid($country);
        }

        return $this->lib->formatNumberForMobileDialing(
            $this->getPhoneNumberInstance(),
            $country,
            $withFormatting
        );
    }

    /**
     * Get the phone number's country.
     *
     * @return string
     * @throws NumberParseException
     * @throws libNumberParseException
     */
    public function getCountry(): string
    {
        if (! $this->country) {
            $this->country = $this->filterValidCountry($this->countries);
        }

        return $this->country;
    }

    /**
     * Check if the phone number is of (a) given country(ies).
     *
     * @param string|array $country
     * @return bool
     * @throws NumberParseException
     * @throws libNumberParseException
     */
    public function isOfCountry(array|string $country): bool
    {
        $countries = static::parseCountries($country);

        return in_array($this->getCountry(), $countries);
    }

    /**
     * Filter the provided countries to the one that is valid for the number.
     *
     * @param string|array $countries
     * @return string
     * @throws NumberParseException|libNumberParseException
     */
    protected function filterValidCountry(array|string $countries): string
    {
        $result = Collection::make($countries)
            ->filter(function ($country) {
                try {
                    $instance = $this->lib->parse($this->number, $country);

                    return $this->lenient
                        ? $this->lib->isPossibleNumber($instance, $country)
                        : $this->lib->isValidNumberForRegion($instance, $country);
                } catch (libNumberParseException $e) {
                    return false;
                }
            })->first();

        // If we got a new result, return it.
        if ($result) {
            return $result;
        }

        // Last resort: try to detect it from an international number.
        if ($this->numberLooksInternational()) {
            $countries[] = null;
        }

        foreach ($countries as $country) {
            $instance = $this->lib->parse($this->number, $country);

            if ($this->lib->isValidNumber($instance)) {
                return $this->lib->getRegionCodeForNumber($instance);
            }
        }

        if ($countries = array_filter($countries)) {
            throw NumberParseException::countryMismatch($this->number, $countries);
        }

        throw NumberParseException::countryRequired($this->number);
    }

    /**
     * Get the phone number's type.
     *
     * @param bool $asConstant
     * @return string|int|null
     * @throws libNumberParseException
     */
    public function getType($asConstant = false): int|string|null
    {
        $type = $this->lib->getNumberType($this->getPhoneNumberInstance());

        if ($asConstant) {
            return $type;
        }

        $stringType = Arr::get(static::parseTypesAsStrings($type), 0);

        return $stringType ? strtolower($stringType) : null;
    }

    /**
     * Check if the phone number is of (a) given type(s).
     *
     * @param string $type
     * @return bool
     * @throws libNumberParseException
     */
    public function isOfType(string $type): bool
    {
        $types = static::parseTypes($type);

        // Add the unsure type when applicable.
        if (array_intersect([PhoneNumberType::FIXED_LINE, PhoneNumberType::MOBILE], $types)) {
            $types[] = PhoneNumberType::FIXED_LINE_OR_MOBILE;
        }

        return in_array($this->getType(true), $types, true);
    }

    /**
     * Get the raw provided number.
     *
     * @return string
     */
    public function getRawNumber(): string
    {
        return $this->number;
    }

    /**
     * Get the PhoneNumber instance of the current number.
     *
     * @return \libphonenumber\PhoneNumber
     * @throws NumberParseException
     * @throws libNumberParseException
     */
    public function getPhoneNumberInstance(): \libphonenumber\PhoneNumber
    {
        return $this->lib->parse($this->number, $this->getCountry());
    }

    /**
     * Determine whether the phone number seems to be in international format.
     *
     * @return bool
     */
    protected function numberLooksInternational(): bool
    {
        return Str::startsWith($this->number, '+');
    }

    /**
     * Enable lenient number parsing.
     *
     * @return $this
     */
    public function lenient(): static
    {
        $this->lenient = true;

        return $this;
    }

    /**
     * Convert the phone instance to JSON.
     *
     * @param int $options
     * @return string
     * @throws NumberFormatException|libNumberParseException
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the phone instance into something JSON serializable.
     *
     * @return string
     * @throws NumberFormatException|libNumberParseException
     */
    public function jsonSerialize(): string
    {
        return $this->formatE164();
    }

    /**
     * Convert the phone instance into a string representation.
     *
     * @return string
     * @throws NumberFormatException|libNumberParseException
     */
    public function serialize(): string
    {
        return $this->formatE164();
    }

    /**
     * Reconstructs the phone instance from a string representation.
     *
     * @param string $serialized
     * @throws libNumberParseException
     */
    public function unserialize($serialized)
    {
        $this->lib = PhoneNumberUtil::getInstance();
        $this->number = $serialized;
        $this->country = $this->lib->getRegionCodeForNumber($this->getPhoneNumberInstance());
    }

    /**
     * Convert the phone instance to a formatted number.
     *
     * @return string
     */
    public function __toString(): string
    {
        // Formatting the phone number could throw an exception, but __toString() doesn't cope well with that.
        // Let's just return the original number in that case.
        try {
            return $this->formatE164();
        } catch (Exception $exception) {
            return (string) $this->number;
        }
    }
}