<?php

namespace App\Rules;

use App\Enum\SaleContract\ScPaymentPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

readonly class PaymentDayCheck implements ValidationRule
{
    public function __construct(private ?string $sc_payment_period) {}

    /**
     * Run the validation rule.
     *
     * @param \Closure(string, ?string=): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (null === $this->sc_payment_period) {
            return;
        }

        $day_labels = ScPaymentPeriod::payment_day_classes[$this->sc_payment_period]::LABELS;

        $exist = array_key_exists($value, $day_labels);

        if (!$exist) {
            $fail('付款日无效。')->translate();
        }
    }
}
