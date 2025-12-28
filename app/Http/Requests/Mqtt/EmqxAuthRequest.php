<?php

namespace App\Http\Requests\Mqtt;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class EmqxAuthRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array|string|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'username' => 'required|string',
            'password' => 'required|string',
            'clientid' => 'sometimes|string', // clientid 是可选的
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        // 返回 EMQX 认证接口期望的错误格式。
        $response = response()->json([
            'result'  => 'ignore',
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 400);

        throw new ValidationException($validator, $response);
    }
}
