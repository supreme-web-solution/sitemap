<?php

namespace App\Services\Mail;

use Illuminate\Support\Str;

/**
 * Minimal IMAP4rev1 client over PHP streams (no ext-imap required).
 */
class ImapConnection
{
    /** @var resource|null */
    private $stream;

    private int $tagCounter = 0;

    public function __construct(private readonly MailboxHostGuard $hostGuard) {}

    /**
     * @param  array{host: string, port: int, encryption: string, username: string, password: string}  $credentials
     *
     * @throws MailConnectionUnavailableException
     */
    public function connect(array $credentials): void
    {
        $host = (string) ($credentials['host'] ?? '');
        $port = (int) ($credentials['port'] ?? 993);
        $encryption = (string) ($credentials['encryption'] ?? 'ssl');

        $this->hostGuard->ensureAllowed($host);

        $timeout = (int) config('inbox.imap_timeout_seconds', 30);
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        if ($encryption === 'ssl') {
            $target = 'ssl://'.$host.':'.$port;
            $this->stream = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        } else {
            $target = 'tcp://'.$host.':'.$port;
            $this->stream = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

            if ($this->stream !== false && $encryption === 'tls') {
                $this->readLine();
                $this->command('STARTTLS');
                if (! stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new MailConnectionUnavailableException(__('Could not secure the IMAP connection.'));
                }
            }
        }

        if ($this->stream === false) {
            throw new MailConnectionUnavailableException(__('Could not connect to the IMAP server.'));
        }

        stream_set_timeout($this->stream, $timeout);
        $this->readLine();

        $username = (string) ($credentials['username'] ?? '');
        $password = (string) ($credentials['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new MailConnectionUnavailableException(__('Enter the mailbox username and password.'));
        }

        $this->command('LOGIN '.$this->quote($username).' '.$this->quote($password));
        $this->command('SELECT INBOX');
    }

    /**
     * @return list<int>
     */
    public function searchUidsSince(?\DateTimeInterface $since): array
    {
        $criteria = $since === null
            ? 'ALL'
            : 'SINCE '.$since->format('d-M-Y');

        $response = $this->command('UID SEARCH '.$criteria);
        $line = implode("\n", $response);

        if (! preg_match('/\* SEARCH(.*)/i', $line, $matches)) {
            return [];
        }

        $uids = array_values(array_filter(array_map('intval', preg_split('/\s+/', trim($matches[1])))));

        $max = (int) config('inbox.imap_max_messages_per_sync', 50);

        if (count($uids) > $max) {
            $uids = array_slice($uids, -$max);
        }

        return $uids;
    }

    /**
     * @return array{message_id: string, in_reply_to: ?string, references: list<string>, from: string, to: string, subject: string, date: string, snippet: string}
     */
    public function fetchMessage(int $uid): array
    {
        $response = $this->command('UID FETCH '.$uid.' (BODY.PEEK[HEADER] BODY.PEEK[TEXT]<0.500>)');
        $raw = implode("\n", $response);

        $header = $this->extractLiteral($raw, 'BODY[HEADER]') ?? '';
        $text = $this->extractLiteral($raw, 'BODY[TEXT]') ?? '';

        return [
            'message_id' => $this->headerValue($header, 'Message-ID') ?? '<uid-'.$uid.'@local>',
            'in_reply_to' => $this->headerValue($header, 'In-Reply-To'),
            'references' => $this->parseReferences($this->headerValue($header, 'References')),
            'from' => $this->headerValue($header, 'From') ?? '',
            'to' => $this->headerValue($header, 'To') ?? '',
            'subject' => $this->decodeHeader($this->headerValue($header, 'Subject') ?? '(no subject)'),
            'date' => $this->headerValue($header, 'Date') ?? now()->toRfc2822String(),
            'snippet' => trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? ''),
        ];
    }

    public function disconnect(): void
    {
        if (! is_resource($this->stream)) {
            return;
        }

        try {
            $this->command('LOGOUT');
        } catch (MailConnectionUnavailableException) {
            // ignore logout failures
        }

        fclose($this->stream);
        $this->stream = null;
    }

    /**
     * @return list<string>
     */
    protected function command(string $command): array
    {
        if (! is_resource($this->stream)) {
            throw new MailConnectionUnavailableException(__('IMAP connection is not open.'));
        }

        $tag = 'A'.(++$this->tagCounter);
        fwrite($this->stream, $tag.' '.$command."\r\n");

        return $this->readUntilTagged($tag);
    }

    /**
     * @return list<string>
     */
    protected function readUntilTagged(string $tag): array
    {
        $lines = [];

        while (is_resource($this->stream)) {
            $line = $this->readLine();

            if ($line === null) {
                throw new MailConnectionUnavailableException(__('IMAP connection closed unexpectedly.'));
            }

            if (preg_match('/\{(\d+)\}$/', $line, $matches)) {
                $literal = $this->readBytes((int) $matches[1]);
                $line .= "\n".$literal;
            }

            $lines[] = $line;

            if (str_starts_with($line, $tag.' OK')) {
                return $lines;
            }

            if (str_starts_with($line, $tag.' NO') || str_starts_with($line, $tag.' BAD')) {
                throw new MailConnectionUnavailableException(__('IMAP command failed: :detail', [
                    'detail' => Str::limit($line, 120),
                ]));
            }
        }

        throw new MailConnectionUnavailableException(__('IMAP connection closed unexpectedly.'));
    }

    protected function readLine(): ?string
    {
        if (! is_resource($this->stream)) {
            return null;
        }

        $line = fgets($this->stream);

        if ($line === false) {
            return null;
        }

        return rtrim($line, "\r\n");
    }

    protected function readBytes(int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length && is_resource($this->stream)) {
            $chunk = fread($this->stream, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $buffer .= $chunk;
        }

        // Literal responses end with CRLF.
        if (is_resource($this->stream)) {
            fgets($this->stream);
        }

        return $buffer;
    }

    protected function quote(string $value): string
    {
        return '"'.addcslashes($value, "\\\"\r\n").'"';
    }

    protected function extractLiteral(string $raw, string $section): ?string
    {
        if (! preg_match('/'.preg_quote($section, '/').' \{(\d+)\}\r?\n/s', $raw, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $length = (int) $matches[1][0];
        $start = $matches[0][1] + strlen($matches[0][0]);

        return substr($raw, $start, $length);
    }

    protected function headerValue(string $headers, string $name): ?string
    {
        if (! preg_match('/^'.preg_quote($name, '/').':\s*(.+)$/im', $headers, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * @return list<string>
     */
    protected function parseReferences(?string $header): array
    {
        if ($header === null || $header === '') {
            return [];
        }

        preg_match_all('/<[^>]+>/', $header, $matches);

        return $matches[0] ?? [];
    }

    protected function decodeHeader(string $value): string
    {
        if (function_exists('iconv_mime_decode')) {
            $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $value;
    }
}
