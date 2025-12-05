<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRuleRequest extends FormRequest
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
            'entity_type' => 'required|string|max:50',
            'event_type' => 'required|string|max:50',
            'conditions' => 'required|array',
            'actions' => 'required|array',
            'priority' => 'integer|min:0',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after:effective_from',
        ];
    }
}
