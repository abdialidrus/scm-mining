<?php

namespace App\Services\Email;

use App\Contracts\EmailServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Exception;

class ResendEmailService implements EmailServiceInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.resend.com';

    public function __construct()
    {
        $this->apiKey = config('services.resend.key');

        if (empty($this->apiKey)) {
            throw new Exception('Resend API key is not configured');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $options = []
    ): array {
        $payload = [
            'from' => $options['from'] ?? config('mail.from.address'),
            'to' => [$to],
            'subject' => $subject,
            'html' => $htmlBody,
        ];

        if ($textBody) {
            $payload['text'] = $textBody;
        }

        if (!empty($options['cc'])) {
            $payload['cc'] = is_array($options['cc']) ? $options['cc'] : [$options['cc']];
        }

        if (!empty($options['bcc'])) {
            $payload['bcc'] = is_array($options['bcc']) ? $options['bcc'] : [$options['bcc']];
        }

        if (!empty($options['reply_to'])) {
            $payload['reply_to'] = $options['reply_to'];
        }

        if (!empty($options['tags'])) {
            $payload['tags'] = $options['tags'];
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/emails', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'status' => 'sent',
                    'message_id' => $responseData['id'] ?? null,
                    'provider' => 'resend',
                ];
            }

            $responseData = $response->json();
            $errorMessage = $responseData['message'] ?? 'Unknown error';
            throw new Exception('Resend API error: ' . $errorMessage);
        } catch (Exception $e) {
            throw new Exception('Failed to send email via Resend: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function sendTemplate(
        string $to,
        string $subject,
        string $view,
        array $data = [],
        array $options = []
    ): array {
        $htmlBody = View::make($view, $data)->render();
        $textBody = null;

        // Try to render plain text version if exists
        $plainView = str_replace('.blade.php', '-plain.blade.php', $view);
        if (View::exists($plainView)) {
            $textBody = View::make($plainView, $data)->render();
        }

        return $this->send($to, $subject, $htmlBody, $textBody, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function getProvider(): string
    {
        return 'resend';
    }
}
