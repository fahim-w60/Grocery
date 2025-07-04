<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AddStoreRequest extends FormRequest
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
            'store_name' => 'required|string|max:255', 
            'category' => 'required|string', 
            'email' => 'required|email|unique:users,email', 
            'password' => [
                'required', 
                'string', 
                'min:8', 
                'regex:/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'
            ],
            [
                'password.regex' => 'The password must contain at least one letter, one number, and one special character.',
        
            ]
         ];
    }

    public function messages()
    {
        return [
            'store_name.required' => 'The store name is required.',
            'store_name.string' => 'The store name must be a string.',
            'store_name.max' => 'The store name may not be greater than 255 characters.',
            'category.required' => 'The category is required.',
            'category.string' => 'The category must be a string.',
            'email.required' => 'The email is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'The email has already been taken.',
            'password.required' => 'The password is required.',
            'password.string' => 'The password must be a string.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.regex' => 'The password must contain at least one letter, one number, and one special character (@, $, !, %, *, ?, or &).',
        ];
    }
}
