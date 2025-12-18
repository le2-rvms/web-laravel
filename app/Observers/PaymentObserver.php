<?php

namespace App\Observers;

use App\Enum\Payment\IoType;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Exceptions\ClientException;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Payment\PaymentInout;

class PaymentObserver
{
    public function created(Payment $payment): void {}

    public function updated(Payment $payment): void
    {
        $occur_amount = $pa = $io_type = null;
        $original     = $payment->getOriginal();

        if (PIsValid::VALID === $payment->p_is_valid->value) {
            if (PPayStatus::PAID === $payment->p_pay_status->value) { // 未支付→支付 支付→支付
                $occur_amount = bcsub($payment->p_actual_pay_amount, $original['p_actual_pay_amount'] ?? '0', 2);

                $pa = $payment->PaymentAccount;
                if (!$pa) {
                    return;
                }

                $io_type = bccomp($occur_amount, '0', '2') > 0 ? IoType::IN : IoType::OUT;
            } elseif (PPayStatus::PAID === $original['p_pay_status']->value && PPayStatus::UNPAID === $payment->p_pay_status->value) { // 回退， 支付→未支付
                // 更新账户金额
                // 写一笔反向记录

                $occur_amount = bcsub('0', $original['p_actual_pay_amount'], 2);

                $pa = PaymentAccount::query()->find($original['p_pa_id']);

                $io_type = bccomp($occur_amount, '0', '2') > 0 ? IoType::OUT_ : IoType::IN_;
            }
        } elseif (PIsValid::INVALID === $payment->p_is_valid->value) { // 变更成无效的状态
            // 已支付的 => 生成反向记录
            if (PPayStatus::PAID === $payment->p_pay_status->value) {
                $occur_amount = bcsub('0', $original['p_actual_pay_amount'], 2);
                $pa           = PaymentAccount::query()->find($original['p_pa_id']);
                $io_type      = bccomp($occur_amount, '0', '2') > 0 ? IoType::OUT_ : IoType::IN_;
            }
            // 未支付的 => 不做处理
        }

        if (null !== $occur_amount) {
            $lastPaymentInout = PaymentInout::query()->where('io_pa_id', '=', $pa->pa_id)->orderByDesc('io_id')->first();

            if (0 !== bccomp($lastPaymentInout?->io_account_balance ?? '0', $pa->pa_balance, 2)) {
                throw new ClientException('金额错误-before');
            }

            $MoneyIo = PaymentInout::query()->create([
                'io_type'            => $io_type,
                'io_cu_id'           => $payment->SaleContract->sc_cu_id,
                'io_pa_id'           => $pa->pa_id,
                'io_occur_datetime'  => now(),
                'io_occur_amount'    => $occur_amount,
                'io_account_balance' => bcadd($lastPaymentInout->io_account_balance ?? '0', $occur_amount, 2),
                'io_p_id'            => $payment->p_id,
            ]);

            // 更新钱包总金额

            $pa->pa_balance = bcadd($pa->pa_balance, $occur_amount, 2);

            if (0 !== bccomp($MoneyIo->io_account_balance, $pa->pa_balance, 2)) {
                throw new ClientException('金额错误-after');
            }

            $pa->save();
        }
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void {}

    /**
     * Handle the Payment "restored" event.
     */
    public function restored(Payment $payment): void {}

    /**
     * Handle the Payment "force deleted" event.
     */
    public function forceDeleted(Payment $payment): void {}
}
