<?php

namespace App\Http\Requests\Sites;

use App\Models\Site;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSiteDomainRequest extends FormRequest
{
    /**
     * Labels that can never be a customer's public domain.
     *
     * @var list<string>
     */
    protected const RESERVED_ENDINGS = ['localhost', 'local', 'internal', 'test', 'invalid', 'example'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Clean up what was pasted: a full address becomes just the hostname.
     */
    public static function normalize(?string $value): string
    {
        $host = strtolower(trim((string) $value));
        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host) ?? $host;
        $host = preg_split('#[/?\#:]#', $host)[0] ?? '';

        return rtrim($host, '.');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hostname' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+([a-z]{2,63}|xn--[a-z0-9-]{1,59})$/',
                $this->notReserved(...),
                $this->notTheDashboard(...),
                $this->notAlreadyAdded(...),
                $this->withinLimit(...),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hostname.regex' => 'Enter a domain like example.com or www.example.com.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['hostname' => self::normalize($this->input('hostname'))]);
    }

    protected function notReserved(string $attribute, mixed $value, Closure $fail): void
    {
        if (in_array(substr(strrchr('.'.$value, '.'), 1), self::RESERVED_ENDINGS, true)) {
            $fail('Enter a real, public domain name.');
        }
    }

    protected function notTheDashboard(string $attribute, mixed $value, Closure $fail): void
    {
        $dashboards = array_filter([
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            ...array_map('strtolower', config('sites.app_hosts')),
        ]);

        foreach ($dashboards as $dashboard) {
            if ($value === $dashboard || str_ends_with($value, '.'.$dashboard)) {
                $fail('This domain belongs to the app and cannot be used for a website.');
            }
        }
    }

    protected function notAlreadyAdded(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->site()->domains()->where('hostname', $value)->exists()) {
            $fail('This domain is already added to this website.');
        }
    }

    protected function withinLimit(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->site()->domains()->count() >= config('sites.max_domains_per_site')) {
            $fail('A website can have at most '.config('sites.max_domains_per_site').' domains.');
        }
    }

    protected function site(): Site
    {
        return $this->route('site');
    }
}
