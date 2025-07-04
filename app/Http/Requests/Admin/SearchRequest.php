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
        ];
    }

    public function messages()
    {
        return [
            'search.required' => 'The search field is required.',
            'search.string' => 'The search must be a string.',
            'search.max' => 'The search may not be greater than 255 characters.',
        ];
    }
}
