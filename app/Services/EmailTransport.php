<?php

namespace App\Services;

use App\Models\EmailSetting;
use Illuminate\Support\Facades\Mail;

class EmailTransport
{
    public function send(EmailSetting $settings, string $destination, array $payload, ?string $name = null): void
    {
        $safe = $settings->safeRecipient($destination);
        if (!$safe) throw new \RuntimeException('EmailSafeRecipientMissing');
        // Symfony uses smtp for STARTTLS and smtps for implicit TLS (normally 465).
        // Credentials stay in encrypted settings, never in logs or client markup.
        config(['mail.mailers.newtic_smtp' => [
            'transport' => 'smtp',
            'scheme' => $settings->smtp_scheme === 'smtps' ? 'smtps' : 'smtp',
            'host' => $settings->smtp_host,
            'port' => (int) $settings->smtp_port,
            'username' => $settings->smtp_username,
            'password' => $settings->smtp_password,
            'timeout' => 12,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
        ]]);
        Mail::purge('newtic_smtp');
        Mail::mailer('newtic_smtp')->send([
            'html' => 'emails.newtic-notification',
            'text' => 'emails.newtic-notification-text',
        ], ['mailData' => $payload], function ($message) use ($settings, $safe, $name, $payload) {
            $message->to($safe, EmailSetting::previewEnvironment() ? null : $name)
                ->from($settings->from_address, $settings->from_name ?: config('app.name'))
                ->subject($payload['subject']);
            if (filled($settings->reply_to)) $message->replyTo($settings->reply_to);
        });
    }
}
