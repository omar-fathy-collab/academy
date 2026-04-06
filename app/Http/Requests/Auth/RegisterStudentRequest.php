<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:profiles,phone_number'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'date_of_birth' => ['required', 'date', 'before:-10 years'],
            'gender' => ['required', 'in:male,female'],
            'education_level' => ['nullable', 'string', 'max:100'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
            'interests' => ['nullable', 'string'],
            'how_know_us' => ['nullable', 'string', 'max:255'],
            'terms' => ['required', 'accepted'],
        ];

        if ($this->has('course_id')) {
            $rules['course_id'] = ['exists:courses,id'];
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'date_of_birth.before' => 'يجب أن يكون عمرك 10 سنوات على الأقل',
            'terms.accepted' => 'يجب الموافقة على الشروط والأحكام',
            'phone.unique' => 'رقم الهاتف مسجل بالفعل',
            'email.unique' => 'البريد الإلكتروني مسجل بالفعل',
        ];
    }
}
