<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group->isAdmin(Auth::id());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'max:255'],
            'auto_approval' => ['required', 'boolean'],
            'about' => ['nullable']
        ];
    }
    public function messages()
    {
        return [
            'name.required' => 'Vui lòng nhập tên nhóm',
            'name.max' => 'Tên nhóm không được > 255 ký tự'
        ];
    }
}
