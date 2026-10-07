<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InternetArchiveSettings implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $settings = json_decode((string) $value, true);

        if (! is_array($settings) || array_is_list($settings)) {
            $fail('The :attribute field must be a JSON object.');

            return;
        }

        $requiredKeys = ['allowed_hosts', 'timeout', 'poll_delay', 'max_attempts'];

        if (array_diff($requiredKeys, array_keys($settings)) !== []) {
            $fail('The :attribute field must include allowed_hosts, timeout, poll_delay, and max_attempts.');

            return;
        }

        if (! is_array($settings['allowed_hosts'])
            || $settings['allowed_hosts'] === []
            || count($settings['allowed_hosts']) > 20) {
            $fail('The allowed_hosts setting must contain between 1 and 20 host names.');

            return;
        }

        foreach ($settings['allowed_hosts'] as $host) {
            if (! is_string($host)
                || ! str_contains($host, '.')
                || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                $fail('Each allowed_hosts value must be a valid host name without a scheme or path.');

                return;
            }
        }

        $this->validateIntegerRange($settings, 'timeout', 1, 120, $fail);
        $this->validateIntegerRange($settings, 'poll_delay', 1, 86400, $fail);
        $this->validateIntegerRange($settings, 'max_attempts', 1, 100000, $fail);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function validateIntegerRange(array $settings, string $key, int $minimum, int $maximum, Closure $fail): void
    {
        if (! is_int($settings[$key]) || $settings[$key] < $minimum || $settings[$key] > $maximum) {
            $fail("The {$key} setting must be an integer between {$minimum} and {$maximum}.");
        }
    }
}
