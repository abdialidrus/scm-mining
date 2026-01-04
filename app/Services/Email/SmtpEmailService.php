<?php

namespace App\Services\Email;

use App\Contracts\EmailServiceInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Exception;

class SmtpEmailService implements EmailServiceInterface
{
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
        try {
            Mail::html($htmlBody, function ($message) use ($to, $subject, $textBody, $options) {
                $message->to($to)
                    ->subject($subject);

                if ($textBody) {
                    $message->text($textBody);
                }

                if (!empty($options['from'])) {
                    $message->from($options['from']);
                }

                if (!empty($options['cc'])) {
                    $message->cc($options['cc']);
                }

                if (!empty($options['bcc'])) {
                    $message->bcc($options['bcc']);
                }

                if (!empty($options['reply_to'])) {
                    $message->replyTo($options['reply_to']);
                }
            });

            return [
                'status' => 'sent',
                'message_id' => null, // SMTP doesn't provide message ID easily
                'provider' => 'smtp',
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to send email via SMTP: ' . $e->getMessage());
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
        return 'smtp';
    }
}
