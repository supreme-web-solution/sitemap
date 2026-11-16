<?php

namespace App\Services\Jvzoo;

use Illuminate\Http\Request;

class JvzooIpnVerifier
{
    public function verify(Request $request): bool
    {
        $secretKey = (string) config('jvzoo.secret_key');

        if ($secretKey === '' || ! $request->has('cverify')) {
            return false;
        }

        $payload = $request->except('cverify');
        $ipnFields = array_keys($payload);
        sort($ipnFields);

        $pop = '';

        foreach ($ipnFields as $field) {
            $pop .= (string) $payload[$field].'|';
        }

        $pop .= $secretKey;

        if (mb_detect_encoding($pop) !== 'UTF-8') {
            $pop = mb_convert_encoding($pop, 'UTF-8');
        }

        $calcedVerify = sha1($pop);
        $calcedVerify = strtoupper(substr($calcedVerify, 0, 8));

        return hash_equals($calcedVerify, (string) $request->input('cverify'));
    }
}
