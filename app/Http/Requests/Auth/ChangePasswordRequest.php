<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePasswordRequest extends FormRequest
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
            'new_password' => [
                'required', 
                'string', 
                'min:8', 
                'regex:/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]+$/',
                Rule::notIn([$this->password]),
            ],
            'confirmed_password' => [
                'required', 
                'string', 
                'min:8', 
                'same:new_password', 
                'regex:/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]+$/',
            ],
        ];
    }

    public function messages()
    {
        return [
            'new_password.regex' => 'The new password must contain at least one letter, one number, and one special character.',
            'confirmed_password.regex' => 'The confirmed password must contain at least one letter, one number, and one special character.',
            'confirmed_password.same' => 'The confirmed password must match the new password.',
        ];
    }
}
