<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => 'required|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    public function messages()
    {
        return [
            'search.required' => 'The search field is required.',
            'search.string' => 'The search must be a string.',
            'search.max' => 'The search may not be greater than 255 characters.',
            'per_page.integer' => 'The per_page value must be an integer.',
            'per_page.min' => 'The per_page value must be at least 1.',
            'per_page.max' => 'The per_page value may not be greater than 100.',
        ];
    }
}
