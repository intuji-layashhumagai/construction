<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
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

            'name' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            'os_version' => 'nullable|string|max:255',
            'storage_available' => 'nullable|integer|min:0', // Minimum value of 0
            'network_type' => 'nullable|string|max:50',

        ];
    }
}
