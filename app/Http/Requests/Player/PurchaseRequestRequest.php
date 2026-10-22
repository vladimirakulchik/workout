<?php

declare(strict_types=1);

namespace App\Http\Requests\Player;

use App\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'payment_type' => ['required', Rule::enum(PaymentType::class)],
        ];
    }
}
