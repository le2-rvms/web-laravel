<?php

namespace App\Models\Delivery;

use App\Enum\Delivery\DlDcKey;
use App\Enum\Delivery\DlSendStatus;
use App\Models\_\ModelTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * @property int            $dl_id     消息序号
 * @property DlDcKey|string $dl_dc_key 消息类型key
 * @property string         $dl_key    消息key
 * @property int            $dl_p_id   关联对象ID
 * @property string         $dl_sc_id  关联对象ID
 *
 * -- 收件人与消息体
 * @property array  $dl_recipients      收件人
 * @property string $dl_recipients_url  收件人地址
 * @property string $dl_content_title   消息标题
 * @property string $dl_content_body    消息内容
 * @property array  $dl_content_actions 消息按钮/链接等结构化动作
 *
 * -- 发送状态
 * @property DlSendStatus|int $dl_send_status  发送状态,
 * @property int              $dl_send_attempt 发送尝试次数
 *                                             retry_of            UUID         NULL REFERENCES delivery_log(id) ON DELETE SET NULL,
 *
 * -- 调度与时间
 * @property Carbon $dl_scheduled_for          计划发送时间
 * @property Carbon $dl_scheduled_sent_at      时机发送时间,
 * @property Carbon $dl_scheduled_delivered_at 发送成功时间,
 * @property Carbon $dl_scheduled_canceled_at  发送取消时间,
 *
 * -- 下游供应商反馈
 * @property string $dl_resp_message_id    消息发送ID,
 * @property string $dl_resp_body          原始回执/错误等
 * @property string $dl_resp_error_code    错误码,
 * @property string $dl_resp_error_message 错误信息,
 *
 *  -- relations
 * @property DeliveryChannel $DeliveryChannel
 */
class DeliveryLog extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'dl_created_at';
    public const UPDATED_AT = 'dl_updated_at';
    public const UPDATED_BY = 'dl_updated_by';

    protected $primaryKey = 'dl_id';

    protected $guarded = ['dl_id'];

    protected $casts = [
        'dl_dc_key'      => DlDcKey::class,
        'dl_send_status' => DlSendStatus::class,
        'dl_recipients'  => 'array',
    ];

    protected $attributes = [];

    protected $appends = [
        'dl_dc_key_label',
        'dl_send_status_label',
    ];

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('delivery_logs', 'dl')
            ->orderByDesc('dl.dl_id')
            ->select('dl.*')
            ->addSelect(
                DB::raw(DlDcKey::toCaseSQL()),
                DB::raw(DlSendStatus::toCaseSQL()),
            )
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    public function DeliveryChannel(): BelongsTo
    {
        return $this->belongsTo(DeliveryChannel::class, 'dl_dc_key', 'dc_key');
    }

    /**
     * https://developer.work.weixin.qq.com/document/path/99110.
     */
    public function send_wecom_bot(): string
    {
        $resp = Http::asJson()->post(
            $this->recipients_url,
            ['msgtype' => 'text', 'text' => ['content' => $this->content_body]]
        );

        if ($resp->failed()) {
            throw new \RuntimeException($resp->body());
        }

        if (0 != $resp->json('errcode')) {
            throw new \RuntimeException($resp->body());
        }

        return $resp->body();
    }

    /**
     * https://developer.work.weixin.qq.com/document/path/90236.
     */
    public function send_wecom_app(): string
    {
        $cacheKey = config('setting.wecom.app_delivery_token_cache_key');

        $access_token = Cache::get($cacheKey);
        if (!$access_token) {
            throw new \RuntimeException('access_token 不存在，稍后再试。');
        }

        $resp = Http::asJson()->post(
            "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token={$access_token}",
            [
                'agentid' => config('setting.wecom.app_delivery_agent_id'),
                'touser'  => join('|', $this->recipients),
                'msgtype' => 'text',
                'text'    => ['content' => $this->content_body],
            ]
        );

        if ($resp->failed()) {
            throw new \RuntimeException($resp->body());
        }

        if (0 != $resp->json('errcode')) {
            throw new \RuntimeException($resp->body());
        }

        return $resp->body();
    }

    protected function dlDcKeyLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('dl_dc_key')?->label
        );
    }

    protected function dlSendStatusLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('dl_send_status')?->label
        );
    }
}
