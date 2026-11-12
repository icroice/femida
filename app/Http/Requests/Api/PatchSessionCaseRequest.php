<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PatchSessionCaseRequest extends FormRequest
{
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
            'agent_suggestion' => ['nullable', 'in:pass,fail,blocked'],
            'agent_notes' => ['nullable', 'string', 'max:5000'],
            'verdict' => ['nullable', 'in:pass,fail,blocked'],
            'verdict_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
