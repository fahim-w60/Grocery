<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => 'nullable|email|required_without:phone',
            'phone' => 'nullable|string|required_without:email',
            'password' => [
                'required', 
                'string', 
                'min:8', 
                'regex:/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]+$/',
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'password.regex' => 'The password must contain at least one letter, one number, and one special character.',
            'email.required_without' => 'The email field is required when phone is not provided.',
            'phone.required_without' => 'The phone field is required when email is not provided.',
        ];
    }
}
