<?php

namespace App\Http\Requests\Api;

use App\Models\TestCase;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // developer-level rights are already checked by the project key
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'folder' => TestCase::FOLDER_RULES,
            'preconditions' => ['nullable', 'string', 'max:5000'],
            'steps' => ['required', 'array', 'min:1', 'max:50'],
            'steps.*.action' => ['required', 'string', 'max:2000'],
            'steps.*.expected' => ['required', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:P0,P1,P2,P3'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
