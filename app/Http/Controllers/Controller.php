<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function response(?Request $request = null): ?ResponseBuilder
    {
        static $response = null;
        if (null === $response) {
            // 控制器内复用 ResponseBuilder，避免重复初始化。
            $response = new ResponseBuilder(get_class($this), $request);
        }

        return $response;
    }
}
