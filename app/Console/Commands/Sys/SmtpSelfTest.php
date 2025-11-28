<?php

namespace App\Console\Commands\Sys;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * @internal
 */
#[CoversNothing]
#[AsCommand(
    name: '_sys:smtp:self-test',
    description: 'Send a self-check email to verify SMTP delivery'
)]
class SmtpSelfTest extends Command
{
    protected $signature = '_sys:smtp:self-test {--to= : Override the recipient email address} {--subject= : Override the email subject}';

    protected $description = 'Send a self-check email to verify SMTP delivery';

    public function handle(): int
    {
        $recipient = $this->option('to') ?: config('mail.from.address');

        if (!$recipient) {
            $this->error('请在 .env 设置 或 MAIL_FROM_ADDRESS，或通过 --to 传参。');

            return CommandAlias::FAILURE;
        }

        $subject_prefix = app()->isProduction() ? '' : ('['.app()->environment().']');
        $subject        = $this->option('subject') ?: $subject_prefix.'SMTP Self Test';

        $now = Carbon::now()->toDateTimeString();

        $body = "SMTP 自检邮件发送于 {$now}。如果你收到这封邮件，说明当前 SMTP 正常工作。";

        Mail::raw($body, function ($message) use ($recipient, $subject): void {
            $message->to($recipient)->subject($subject);
        });

        $this->info('已派发 SMTP 自检邮件到：'.$recipient);

        return CommandAlias::SUCCESS;
    }
}
