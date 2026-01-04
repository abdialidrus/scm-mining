<?php

namespace App\Contracts;

interface EmailServiceInterface
{
    /**
     * Send an email.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody Email body (HTML)
     * @param string|null $textBody Email body (plain text, optional)
     * @param array $options Additional options (cc, bcc, attachments, etc.)
     * @return array Response with status and message_id
     * @throws \Exception If sending fails
     */
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $options = []
    ): array;

    /**
     * Send an email using a Blade template.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $view Blade view name
     * @param array $data Data to pass to the view
     * @param array $options Additional options
     * @return array Response with status and message_id
     */
    public function sendTemplate(
        string $to,
        string $subject,
        string $view,
        array $data = [],
        array $options = []
    ): array;

    /**
     * Get the provider name.
     *
     * @return string Provider name (resend, smtp, ses)
     */
    public function getProvider(): string;
}
