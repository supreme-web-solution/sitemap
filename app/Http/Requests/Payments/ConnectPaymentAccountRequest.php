<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConnectPaymentAccountRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->route('provider')) {
            PaymentProvider::Stripe => [
                'secret_key' => ['required', 'string', 'regex:/^(sk|rk)_(live|test)_[A-Za-z0-9]+$/'],
                'webhook_secret' => ['nullable', 'string', 'regex:/^whsec_[A-Za-z0-9]+$/'],
            ],
            PaymentProvider::PayPal => [
                'client_id' => ['required', 'string', 'max:255'],
                'client_secret' => ['required', 'string', 'max:255'],
                'mode' => ['required', 'in:live,sandbox'],
                'webhook_id' => ['nullable', 'string', 'max:100'],
            ],
            PaymentProvider::Paystack => [
                'secret_key' => ['required', 'string', 'max:255'],
                'webhook_secret' => ['nullable', 'string', 'max:255'],
            ],
            PaymentProvider::Flutterwave => [
                'secret_key' => ['required', 'string', 'max:255'],
                'webhook_hash' => ['nullable', 'string', 'max:255'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'secret_key.regex' => 'Paste the secret key that starts with sk_live_ or sk_test_ (or a restricted key starting with rk_).',
            'webhook_secret.regex' => 'The signing secret starts with whsec_.',
        ];
    }
}
