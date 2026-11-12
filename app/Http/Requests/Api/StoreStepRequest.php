<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreStepRequest extends FormRequest
{
    public const MAX_KB = 5120; // 5 MB

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'case_id' => ['required', 'integer', 'exists:test_cases,id'],
            'step_index' => ['required', 'integer', 'min:1', 'max:99'],
            'status' => ['required', 'in:passed,failed,skipped,error'],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:status,skipped,error'],
            'elapsed_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'screenshot' => ['nullable', 'file', 'max:'.self::MAX_KB], // multipart (MCP has base64, stage 5)
        ];
    }
}
