<?php

namespace App\Services\Mail;

/**
 * Prevent mailbox sync from connecting to private or unexpected hosts (SSRF).
 */
class MailboxHostGuard
{
    /**
     * @throws MailConnectionUnavailableException
     */
    public function ensureAllowed(string $host): void
    {
        $host = trim($host);

        if ($host === '' || strlen($host) > 253) {
            throw new MailConnectionUnavailableException(__('Enter a valid IMAP host.'));
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->ensureIpAllowed($host);

            return;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i', $host)) {
            throw new MailConnectionUnavailableException(__('Enter a valid IMAP host.'));
        }

        $resolved = gethostbynamel($host);

        if ($resolved === false) {
            return;
        }

        foreach ($resolved as $ip) {
            $this->ensureIpAllowed($ip);
        }
    }

    /**
     * @throws MailConnectionUnavailableException
     */
    protected function ensureIpAllowed(string $ip): void
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new MailConnectionUnavailableException(__('That IMAP host is not allowed.'));
        }
    }
}
