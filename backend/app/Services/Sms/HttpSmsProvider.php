<?php

namespace App\Services\Sms;

use App\Contracts\NotificationProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Generic REST SMS gateway adapter.
 *
 * Supports the common request shapes of popular gateways out of the box:
 *   - "twilio":  POST /2010-04-01/Accounts/{sid}/Messages.json  (basic auth)
 *   - "clicksend": POST /v3/sms/send  (basic auth, JSON { messages: [...] })
 *   - "custom": a configurable JSON POST where the body is mapped from
 *     from/to/text template keys and auth is basic/bearer.
 *
 * Configure via config/services.php → sms, and env SMS_PROVIDER,
 * SMS_API_URL, SMS_API_KEY, SMS_API_SECRET, SMS_SENDER, SMS_GATEWAY.
 */
class HttpSmsProvider implements NotificationProvider
{
    public function send(string $channel, string $recipient, string $body): string
    {
        if ($channel !== 'sms') {
            throw new \InvalidArgumentException("HttpSmsProvider only handles the 'sms' channel, got '{$channel}'.");
        }

        $gateway = config('services.sms.gateway', 'custom');
        $url = config('services.sms.url');
        if (! $url) {
            throw new \RuntimeException('SMS gateway is not configured (services.sms.url is empty).');
        }

        $response = match ($gateway) {
            'twilio' => $this->sendTwilio($url, $recipient, $body),
            'clicksend' => $this->sendClickSend($url, $recipient, $body),
            default => $this->sendCustom($url, $recipient, $body),
        };

        if (! $response->successful()) {
            throw new \RuntimeException('SMS gateway responded with HTTP '.$response->status().': '.Str::limit($response->body(), 500));
        }

        return $gateway.'_'.$this->extractReference($response->json());
    }

    private function sendTwilio(string $url, string $recipient, string $body)
    {
        $url = rtrim($url, '/').'/2010-04-01/Accounts/'.config('services.sms.key').'/Messages.json';

        return Http::withBasicAuth(config('services.sms.key'), config('services.sms.secret'))
            ->asForm()
            ->post($url, ['To' => $recipient, 'From' => config('services.sms.sender'), 'Body' => $body]);
    }

    private function sendClickSend(string $url, string $recipient, string $body)
    {
        $url = rtrim($url, '/').'/v3/sms/send';

        return Http::withBasicAuth(config('services.sms.key'), config('services.sms.secret'))
            ->acceptJson()
            ->post($url, ['messages' => [['to' => $recipient, 'from' => config('services.sms.sender'), 'body' => $body]]]);
    }

    private function sendCustom(string $url, string $recipient, string $body)
    {
        $mapping = config('services.sms.fields', ['to' => 'to', 'from' => 'from', 'text' => 'text']);
        $payload = [
            $mapping['to'] ?? 'to' => $recipient,
            $mapping['text'] ?? 'text' => $body,
        ];
        if (($mapping['from'] ?? null) && config('services.sms.sender')) {
            $payload[$mapping['from']] = config('services.sms.sender');
        }

        $request = Http::acceptJson();

        if (config('services.sms.auth') === 'bearer') {
            $request = $request->withToken(config('services.sms.key'));
        } elseif (config('services.sms.secret')) {
            $request = $request->withBasicAuth(config('services.sms.key'), config('services.sms.secret'));
        } else {
            $request = $request->withToken(config('services.sms.key'));
        }

        return $request->post($url, $payload);
    }

    private function extractReference(?array $json): string
    {
        $candidates = ['id', 'message_id', 'sid', 'messageId', 'reference', 'data.message_id', 'data.id'];
        foreach ($candidates as $key) {
            $value = data_get($json, $key);
            if (is_string($value) || is_numeric($value)) {
                return (string) $value;
            }
        }

        return Str::lower((string) Str::ulid());
    }
}
