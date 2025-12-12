<?php

namespace App\Observers;

use App\Enum\Payment\IoIoType;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Exceptions\ClientException;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Payment\PaymentInout;

class PaymentObserver
{
    public function created(Payment $payment): void {}

    public function updated(Payment $payment): void
    {
        $occur_amount = $account = $io_type = null;
        $original     = $payment->getOriginal();

        if (RpIsValid::VALID === $payment->is_valid->value) {
            if (RpPayStatus::PAID === $payment->pay_status->value) { // 未支付→支付 支付→支付
                $occur_amount = bcsub($payment->actual_pay_amount, $original['actual_pay_amount'] ?? '0', 2);

                $account = $payment->PaymentAccount;
                if (!$account) {
                    return;
                }

                $io_type = bccomp($occur_amount, '0', '2') > 0 ? IoIoType::IN : IoIoType::OUT;
            } elseif (RpPayStatus::PAID === $original['pay_status']->value && RpPayStatus::UNPAID === $payment->pay_status->value) { // 回退， 支付→未支付
                // 更新账户金额
                // 写一笔反向记录

                $occur_amount = bcsub('0', $original['actual_pay_amount'], 2);

                $account = PaymentAccount::query()->find($original['pa_id']);

                $io_type = bccomp($occur_amount, '0', '2') > 0 ? IoIoType::OUT_ : IoIoType::IN_;
            }
        } elseif (RpIsValid::INVALID === $payment->is_valid->value) { // 变更成无效的状态
            // 已支付的 => 生成反向记录
            if (RpPayStatus::PAID === $payment->pay_status->value) {
                $occur_amount = bcsub('0', $original['actual_pay_amount'], 2);
                $account      = PaymentAccount::query()->find($original['pa_id']);
                $io_type      = bccomp($occur_amount, '0', '2') > 0 ? IoIoType::OUT_ : IoIoType::IN_;
            }
            // 未支付的 => 不做处理
        }

        if (null !== $occur_amount) {
            $lastPaymentInout = PaymentInout::query()->where('pa_id', '=', $account->pa_id)->orderByDesc('io_id')->first();

            if (0 !== bccomp($lastPaymentInout?->account_balance ?? '0', $account->pa_balance, 2)) {
                throw new ClientException('金额错误-before');
            }

            $MoneyIo = PaymentInout::query()->create([
                'io_type'         => $io_type,
                'cu_id'           => $payment->SaleContract->cu_id,
                'pa_id'           => $account->pa_id,
                'occur_datetime'  => now(),
                'occur_amount'    => $occur_amount,
                'account_balance' => bcadd($lastPaymentInout->account_balance ?? '0', $occur_amount, 2),
                'rp_id'           => $payment->rp_id,
            ]);

            // 更新钱包总金额

            $account->pa_balance = bcadd($account->pa_balance, $occur_amount, 2);

            if (0 !== bccomp($MoneyIo->account_balance, $account->pa_balance, 2)) {
                throw new ClientException('金额错误-after');
            }

            $account->save();
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
