<?php

namespace App\Http\Requests\WorkOrders;

use App\Support\BranchTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Complaint, diagnosis, odometer, estimate and notes of a work order.
 */
class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('work_order')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'complaint' => ['required', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'odometer_in' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'estimated_completion' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{complaint: string, diagnosis: string|null, odometer_in: int|null, estimated_completion: string|null, notes: string|null}
     */
    public function workOrderData(): array
    {
        return [
            'complaint' => $this->string('complaint')->toString(),
            'diagnosis' => $this->input('diagnosis'),
            'odometer_in' => $this->filled('odometer_in') ? $this->integer('odometer_in') : null,
            'estimated_completion' => BranchTime::fromInput($this->input('estimated_completion')),
            'notes' => $this->input('notes'),
        ];
    }
}
