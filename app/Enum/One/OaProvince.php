<?php

namespace App\Enum\One;

use App\Enum\ArrayTrait;

class OaProvince
{
    use ArrayTrait;

    public const string option_text_key  = 'name';
    public const string option_value_key = 'name';

    public const array values = [
        [
            'id'   => '110000',
            'name' => '北京',
            'lic'  => '京',
            'url'  => 'https://bj.122.gov.cn',
        ],
        [
            'id'   => '120000',
            'name' => '天津',
            'lic'  => '津',
            'url'  => 'https://tj.122.gov.cn',
        ],
        [
            'id'   => '130000',
            'name' => '河北',
            'lic'  => '冀',
            'url'  => 'https://he.122.gov.cn',
        ],
        [
            'id'   => '140000',
            'name' => '山西',
            'lic'  => '晋',
            'url'  => 'https://sx.122.gov.cn',
        ],
        [
            'id'   => '150000',
            'name' => '内蒙古',
            'lic'  => '蒙',
            'url'  => 'https://nm.122.gov.cn',
        ],
        [
            'id'   => '210000',
            'name' => '辽宁',
            'lic'  => '辽',
            'url'  => 'https://ln.122.gov.cn',
        ],
        [
            'id'   => '220000',
            'name' => '吉林',
            'lic'  => '吉',
            'url'  => 'https://jl.122.gov.cn',
        ],
        [
            'id'   => '230000',
            'name' => '黑龙江',
            'lic'  => '黑',
            'url'  => 'https://hl.122.gov.cn',
        ],
        [
            'id'   => '310000',
            'name' => '上海',
            'lic'  => '沪',
            'url'  => 'https://sh.122.gov.cn',
        ],
        [
            'id'   => '320000',
            'name' => '江苏',
            'lic'  => '苏',
            'url'  => 'https://js.122.gov.cn',
        ],
        [
            'id'   => '330000',
            'name' => '浙江',
            'lic'  => '浙',
            'url'  => 'https://zj.122.gov.cn',
        ],
        [
            'id'   => '340000',
            'name' => '安徽',
            'lic'  => '皖',
            'url'  => 'https://ah.122.gov.cn',
        ],
        [
            'id'   => '350000',
            'name' => '福建',
            'lic'  => '闽',
            'url'  => 'https://fj.122.gov.cn',
        ],
        [
            'id'   => '360000',
            'name' => '江西',
            'lic'  => '赣',
            'url'  => 'https://jx.122.gov.cn',
        ],
        [
            'id'   => '370000',
            'name' => '山东',
            'lic'  => '鲁',
            'url'  => 'https://sd.122.gov.cn',
        ],
        [
            'id'   => '410000',
            'name' => '河南',
            'lic'  => '豫',
            'url'  => 'https://ha.122.gov.cn',
        ],
        [
            'id'   => '420000',
            'name' => '湖北',
            'lic'  => '鄂',
            'url'  => 'https://hb.122.gov.cn',
        ],
        [
            'id'   => '430000',
            'name' => '湖南',
            'lic'  => '湘',
            'url'  => 'https://hn.122.gov.cn',
        ],
        [
            'id'   => '440000',
            'name' => '广东',
            'lic'  => '粤',
            'url'  => 'https://gd.122.gov.cn',
        ],
        [
            'id'   => '450000',
            'name' => '广西',
            'lic'  => '桂',
            'url'  => 'https://gx.122.gov.cn',
        ],
        [
            'id'   => '460000',
            'name' => '海南',
            'lic'  => '琼',
            'url'  => 'https://hi.122.gov.cn',
        ],
        [
            'id'   => '500000',
            'name' => '重庆',
            'lic'  => '渝',
            'url'  => 'https://cq.122.gov.cn',
        ],
        [
            'id'   => '510000',
            'name' => '四川',
            'lic'  => '川',
            'url'  => 'https://sc.122.gov.cn',
        ],
        [
            'id'   => '520000',
            'name' => '贵州',
            'lic'  => '贵',
            'url'  => 'https://gz.122.gov.cn',
        ],
        [
            'id'   => '530000',
            'name' => '云南',
            'lic'  => '云',
            'url'  => 'https://yn.122.gov.cn',
        ],
        [
            'id'   => '540000',
            'name' => '西藏',
            'lic'  => '藏',
            'url'  => 'https://xz.122.gov.cn',
        ],
        [
            'id'   => '610000',
            'name' => '陕西',
            'lic'  => '陕',
            'url'  => 'https://sn.122.gov.cn',
        ],
        [
            'id'   => '620000',
            'name' => '甘肃',
            'lic'  => '甘',
            'url'  => 'https://gs.122.gov.cn',
        ],
        [
            'id'   => '630000',
            'name' => '青海',
            'lic'  => '青',
            'url'  => 'https://qh.122.gov.cn',
        ],
        [
            'id'   => '640000',
            'name' => '宁夏',
            'lic'  => '宁',
            'url'  => 'https://nx.122.gov.cn',
        ],
        [
            'id'   => '650000',
            'name' => '新疆',
            'lic'  => '新',
            'url'  => 'https://xj.122.gov.cn',
        ],
    ];

    private static function getValues()
    {
        return static::values;
    }
}
