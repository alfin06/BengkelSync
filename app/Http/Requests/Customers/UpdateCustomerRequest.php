<?php

namespace App\Http\Requests\Customers;

class UpdateCustomerRequest extends CustomerRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer')) ?? false;
    }
}
