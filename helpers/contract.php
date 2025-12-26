<?php

function gen_sc_no()
{
    $datetime = DateTime::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
    $datetime->setTimezone(new DateTimeZone(date_default_timezone_get()));  // 转换为目标时区

    return $datetime->format('ymdHisv'); // ymdHisv
}
