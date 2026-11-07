@php($px = $size ?? 22)
<svg viewBox="0 0 40 40" width="{{ $px }}" height="{{ $px }}" role="img" aria-hidden="true" style="flex:none;border-radius:6px;background:#fff">
    @switch($provider->value)
        @case('stripe')
            <rect width="40" height="40" fill="#635BFF"/>
            <path d="M24.6 15.2c0-2.2-2.3-3.2-5.2-3.2-2.6 0-4.9.9-4.9 2.9 0 4.2 10 2.6 10 7 0 2.2-2.2 3.4-5.6 3.4-2.7 0-5-.8-6.6-1.8" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/>
            @break
        @case('paypal')
            <rect width="40" height="40" fill="#003087"/>
            <path d="M14 29V11h6.2a5 5 0 0 1 0 10H14" fill="none" stroke="#009CDE" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" transform="translate(2.2 2.2)"/>
            <path d="M14 29V11h6.2a5 5 0 0 1 0 10H14" fill="none" stroke="#fff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('paystack')
            <rect width="40" height="40" fill="#0BA4DB"/>
            <rect x="10" y="11" width="20" height="3.6" rx="1.8" fill="#fff"/>
            <rect x="10" y="18.2" width="20" height="3.6" rx="1.8" fill="#fff"/>
            <rect x="10" y="25.4" width="12" height="3.6" rx="1.8" fill="#fff"/>
            @break
        @default
            <rect width="40" height="40" fill="#F5A623"/>
            <path d="M11 28c.6-8 5.8-13.4 17-14" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
            <path d="M11 21.4c3.2-3.6 7.2-5.2 12-5.4" fill="none" stroke="#FFE2A8" stroke-width="3" stroke-linecap="round"/>
    @endswitch
</svg>
