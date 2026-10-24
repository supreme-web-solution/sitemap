<?php

namespace App\Http\Requests\Mail;

use App\Enums\MailConnectionProvider;
use App\Services\Mail\MailboxHostGuard;
use App\Services\Mail\MailConnectionUnavailableException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectMailConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('current_team')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in([MailConnectionProvider::Imap->value])],
            'email_address' => ['required', 'email', 'max:255'],
            'imap_host' => [
                'required_if:provider,imap',
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->input('provider') !== MailConnectionProvider::Imap->value || ! config('inbox.imap_sync_enabled')) {
                        return;
                    }

                    try {
                        app(MailboxHostGuard::class)->ensureAllowed((string) $value);
                    } catch (MailConnectionUnavailableException $exception) {
                        $fail($exception->getMessage());
                    }
                },
            ],
            'imap_port' => ['required_if:provider,imap', 'nullable', 'integer', 'between:1,65535'],
            'imap_encryption' => ['required_if:provider,imap', 'nullable', Rule::in(['ssl', 'tls', 'none'])],
            'imap_username' => ['required_if:provider,imap', 'nullable', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Build credential payload for storage.
     *
     * @return array<string, mixed>
     */
    public function credentials(?array $existing = null): array
    {
        if ($this->validated('provider') !== MailConnectionProvider::Imap->value) {
            return $existing ?? [];
        }

        $password = $this->validated('imap_password');

        return [
            'host' => $this->validated('imap_host'),
            'port' => (int) $this->validated('imap_port'),
            'encryption' => $this->validated('imap_encryption'),
            'username' => $this->validated('imap_username'),
            'password' => filled($password) ? $password : ($existing['password'] ?? null),
        ];
    }
}
