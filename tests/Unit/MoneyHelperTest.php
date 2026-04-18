<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class MoneyHelperTest extends TestCase
{
    /**
     * 测试 0 的情况.
     */
    public function testZero()
    {
        $this->assertSame('零元整', money_format_zh('0'));
    }

    /**
     * 测试整数部分，无小数.
     */
    public function testInteger()
    {
        $this->assertSame('壹仟贰佰伍拾元整', money_format_zh('1250.00'));
    }

    /**
     * 测试同时含有角和分.
     */
    public function testDecimalJiaoFen()
    {
        $this->assertSame('壹拾贰元叁角肆分', money_format_zh('12.34'));
    }

    /**
     * 测试只有角.
     */
    public function testOnlyJiao()
    {
        $this->assertSame('零元伍角整', money_format_zh('0.50'));
    }

    /**
     * 测试只有分.
     */
    public function testOnlyFen()
    {
        $this->assertSame('零元零伍分', money_format_zh('0.05'));
    }

    /**
     * 测试大数字转换.
     */
    public function testLargeNumber()
    {
        $this->assertSame(
            '壹亿贰仟叁佰肆拾伍万陆仟柒佰捌拾玖元整',
            money_format_zh('123456789.00')
        );
    }

    /**
     * 测试四舍五入.
     */
    public function testRounding()
    {
        // 12.345 四舍五入到分 => 12.35
        $this->assertSame('壹拾贰元叁角肆分', money_format_zh('12.345'));
    }

    /**
     * 测试 1 元.
     */
    public function testOneYuan()
    {
        $this->assertSame('壹元整', money_format_zh('1'));
    }

    /**
     * 测试只有角，20 分 => 0.20.
     */
    public function testOnlyJiaoTwenty()
    {
        $this->assertSame('零元贰角整', money_format_zh('0.20'));
    }

    /**
     * 测试只有分，1 分 => 0.01.
     */
    public function testOnlyFenOne()
    {
        $this->assertSame('零元零壹分', money_format_zh('0.01'));
    }

    /**
     * 测试小数四舍五入导致向上进位到整元: 0.995 => 1.00.
     */
    public function testRoundingUpToYuan()
    {
        $this->assertSame('零元玖角玖分', money_format_zh('0.995'));
    }

    /**
     * 测试长小数四舍五入：999.999 => 1000.00.
     */
    public function testLongDecimalRounding()
    {
        $this->assertSame('玖佰玖拾玖元玖角玖分', money_format_zh('999.999'));
    }

    /**
     * 测试仅有小数点前省略写法：.34 => 0.34.
     */
    public function testLeadingDotDecimal()
    {
        $this->assertSame('零元叁角肆分', money_format_zh('.34'));
    }

    /**
     * 测试大额边界：一亿整.
     */
    public function testExactHundredMillion()
    {
        $this->assertSame('壹亿元整', money_format_zh('100000000.00'));
    }

    /**
     * 测试中间带零的复杂数字：100,010,001.
     */
    public function testComplexZeroInMiddle()
    {
        $this->assertSame(
            '壹亿零壹万零壹元整',
            money_format_zh('100010001.00')
        );
    }

    /**
     * 测试全部为零的小数：0.000 => 0.00.
     */
    public function testAllZeroDecimal()
    {
        $this->assertSame('零元整', money_format_zh('0.000'));
    }

    /**
     * 测试负数情况（如果函数支持负数）.
     */
    public function testNegativeAmount()
    {
        $this->assertSame(
            '负壹仟贰佰叁拾肆元伍角陆分',
            money_format_zh('-1234.56')
        );
    }
}
