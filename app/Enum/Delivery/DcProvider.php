<?php

namespace App\Enum\Delivery;

use App\Enum\EnumLikeBase;

class DcProvider extends EnumLikeBase
{
    public const string WECOM_BOT = 'wecom_bot';
    public const string WECOM_APP = 'wecom_app';
    public const string SMS       = 'sms';

    public const array LABELS = [
        self::WECOM_BOT => '消息推送（群机器人）',
        self::WECOM_APP => '应用推送消息',
        self::SMS       => '短信',
    ];
}
