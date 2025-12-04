<?php

namespace App\Http\Requests;

use App\Enums\DataPriority;
use Illuminate\Foundation\Http\FormRequest;

class SyncEventProtocolRequest extends FormRequest
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
            'events' => 'required|array|min:1',
            'events.*.entity_type' => 'required|string|max:50',
            'events.*.entity_id' => 'required|uuid',
            'events.*.event_type' => 'required|string',
            'events.*.event_priority' => 'required|string|in:'.implode(',', array_map(fn ($case) => $case->value, DataPriority::cases())),
            'events.*.event_data' => 'array',
            'events.*.device_id' => 'required|uuid|exists:devices,id',
            'events.*.worker_id' => 'required|uuid',
            'events.*.device_vector_clock' => 'required|array',
            'events.*.sequence_number' => 'required|integer|min:1',
            'events.*.direction' => 'required|string',
            'events.*.phase' => 'required|string',
        ];
    }
}
