<?php

namespace Martiangeeks\LaravelCiPhone\Rules;

use libphonenumber\PhoneNumberType;
use Martiangeeks\LaravelCiPhone\Traits\ParsesTypes;

class Phone
{
    use ParsesTypes;

    /**
     * The provided phone countries.
     *
     * @var array
     */
    protected array $countries = [];

    /**
     * The input field name to check for a country value.
     *
     * @var string
     */
    protected string $countryField;

    /**
     * The provided phone types.
     *
     * @var array
     */
    protected array $types = [];

    /**
     * Whether the number's country should be auto-detected.
     *
     * @var bool
     */
    protected bool $detect = false;

    /**
     * Whether to allow lenient checks (i.e. landline numbers without area codes).
     *
     * @var bool
     */
    protected bool $lenient = false;

    /**
     * Set the phone countries.
     *
     * @param string|array $country
     * @return $this
     */
    public function country(array|string $country): static
    {
        $countries = is_array($country) ? $country : func_get_args();

        $this->countries = array_merge($this->countries, $countries);

        return $this;
    }

    /**
     * Set the country input field.
     *
     * @param string $name
     * @return $this
     */
    public function countryField(string $name): static
    {
        $this->countryField = $name;

        return $this;
    }

    /**
     * Set the phone types.
     *
     * @param int|string|array $type
     * @return $this
     */
    public function type(array|int|string $type): static
    {
        $types = is_array($type) ? $type : func_get_args();

        $this->types = array_merge($this->types, $types);

        return $this;
    }

    /**
     * Shortcut method for mobile type restriction.
     *
     * @return $this
     */
    public function mobile(): static
    {
        $this->type(PhoneNumberType::MOBILE);

        return $this;
    }

    /**
     * Shortcut method for fixed line type restriction.
     *
     * @return $this
     */
    public function fixedLine(): static
    {
        $this->type(PhoneNumberType::FIXED_LINE);

        return $this;
    }

    /**
     * Enable automatic country detection.
     *
     * @return $this
     */
    public function detect(): static
    {
        $this->detect = true;

        return $this;
    }

    /**
     * Enable lenient number checking.
     *
     * @return $this
     */
    public function lenient(): static
    {
        $this->lenient = true;

        return $this;
    }

    /**
     * Convert the rule to a validation string.
     *
     * @return string
     */
    public function __toString(): string
    {
        $parameters = implode(',', array_merge(
            $this->countries,
            static::parseTypes($this->types),
            ($this->countryField ? [$this->countryField]: []),
            ($this->detect ? ['AUTO'] : []),
            ($this->lenient ? ['LENIENT'] : [])
        ));

        return 'phone' . (! empty($parameters) ? ":$parameters" : '');
    }
}
