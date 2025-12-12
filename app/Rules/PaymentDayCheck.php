<?php

namespace App\Rules;

use App\Enum\Sale\ScPaymentDayType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

readonly class PaymentDayCheck implements ValidationRule
{
    public function __construct(private ?string $payment_day_type) {}

    /**
     * Run the validation rule.
     *
     * @param \Closure(string, ?string=): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (null === $this->payment_day_type) {
            return;
        }

        $day_labels = ScPaymentDayType::payment_day_classes[$this->payment_day_type]::LABELS;

        $exist = array_key_exists($value, $day_labels);

        if (!$exist) {
            $fail('付款日无效。')->translate();
        }
    }
}
