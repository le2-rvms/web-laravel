<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerException extends \Exception
{
    private string $key;

    public function __construct(string $message = '', string $key = '')
    {
        $this->key = $key;

        $this->message = $message;

        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(
            [
                'errors'  => $this->key,
                'message' => $this->message,
            ],
            500,
        );
    }
}
