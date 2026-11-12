<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSessionRequest extends FormRequest
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
            'external_ref' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_commit' => ['nullable', 'string', 'between:7,40'],
            'head_commit' => ['required', 'string', 'between:7,40'],
            'only_after' => ['nullable', 'boolean'],
            'repo_remote' => ['nullable', 'url', 'max:500'],
            'branch' => ['nullable', 'string', 'max:200'],
            'agent' => ['nullable', 'string', 'max:200'],
            'case_ids' => ['required', 'array', 'min:1'],
            'case_ids.*' => ['integer', 'exists:test_cases,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // base_commit is required unless this is an "after only" run
            if (! $this->boolean('only_after') && ! $this->filled('base_commit')) {
                $validator->errors()->add('base_commit', 'base_commit is required unless only_after is true.');
            }

            if ($this->boolean('only_after') && $this->filled('base_commit')) {
                $validator->errors()->add('base_commit', 'base_commit must be null in only_after mode.');
            }
        });
    }
}
