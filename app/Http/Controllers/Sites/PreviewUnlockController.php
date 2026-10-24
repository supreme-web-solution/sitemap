<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\Preview\PreviewLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PreviewUnlockController extends Controller
{
    /**
     * Unlock a password-protected preview for this browser session.
     */
    public function store(Request $request, Site $site): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'max:100']]);

        if ($site->preview_password === null || ! Hash::check($request->input('password'), $site->preview_password)) {
            throw ValidationException::withMessages(['password' => __('That password is not right.')]);
        }

        $request->session()->put($this->sessionKey($site), true);

        return redirect(PreviewLink::page($site));
    }

    public static function sessionKey(Site $site): string
    {
        return 'preview-unlocked:'.$site->id;
    }
}
