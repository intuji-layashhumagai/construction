<?php

namespace App\Http\Requests;

use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
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
            'entity_type' => 'required|string|max:50',
            'entity_id' => 'required|uuid',
            'event_type' => 'required|string|in:'.implode(',', array_map(fn ($case) => $case->value, EventType::cases())),
            'event_data' => 'required|array',
            'device_id' => 'required|uuid',
            'worker_id' => 'required|uuid',
            'sequence_number' => 'required|integer|min:1',
        ];
    }
}
