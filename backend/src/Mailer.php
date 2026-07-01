<?php

namespace App;

/**
 * Email sender using the Resend HTTP API (https://resend.com).
 *
 * Railway (and many other PaaS hosts) block outbound raw SMTP ports
 * (25/465/587) to prevent spam abuse, so a socket-based SMTP mailer times
 * out there. Resend delivers over plain HTTPS (port 443), which is never
 * blocked. Reads config from environment variables loaded by Env::load():
 *   RESEND_API_KEY  — your Resend API key (starts with "re_")
 *   MAIL_FROM       — verified sender, e.g. "EventOra <no-reply@yourdomain>"
 *                     (defaults to Resend's shared onboarding@resend.dev)
 */
class Mailer
{
    private static function env(string $key, string $default = ''): string
    {
        return (string) (getenv($key) ?: $_ENV[$key] ?? $default);
    }

    /**
     * Send a plain-text + HTML email via the Resend HTTP API.
     *
     * @throws \RuntimeException if the API call fails
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): void {
        $apiKey = self::env('RESEND_API_KEY');
        if ($apiKey === '') {
            throw new \RuntimeException('RESEND_API_KEY is not configured.');
        }

        $from = self::env('MAIL_FROM', 'EventOra <onboarding@resend.dev>');

        if (!$textBody) {
            $textBody = strip_tags($htmlBody);
        }

        $payload = json_encode([
            'from'    => $from,
            'to'      => [$toEmail],
            'subject' => $subject,
            'html'    => $htmlBody,
            'text'    => $textBody,
        ]);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Resend request failed: {$err}");
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Resend API returned HTTP {$status}: {$response}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Convenience mailers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Send the email-verification email containing the one-time link.
     *
     * @param string $verifyUrl  Full URL: http://localhost:8080/auth/verify-email?token=<hex>
     */
    public static function sendVerification(string $toEmail, string $toName, string $verifyUrl): void
    {
        $subject = 'Verify your EventOra account ✅';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f1a;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:16px;overflow:hidden;border:1px solid rgba(99,102,241,.3);">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:40px 40px 32px;text-align:center;">
            <div style="width:64px;height:64px;background:rgba(255,255,255,.2);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
              <span style="font-size:28px;">✉️</span>
            </div>
            <h1 style="margin:0;font-size:28px;color:#fff;letter-spacing:-0.5px;">Verify your email</h1>
            <p style="margin:8px 0 0;color:rgba(255,255,255,.8);font-size:14px;">EventOra · UTM Campus Event Management</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:40px;">
            <h2 style="margin:0 0 12px;color:#e2e8f0;font-size:20px;">Hi {$toName},</h2>
            <p style="margin:0 0 16px;color:#94a3b8;line-height:1.7;font-size:15px;">
              Thank you for registering on <strong style="color:#a78bfa;">EventOra</strong>!
              Click the button below to verify your UTM email address and activate your account.
            </p>
            <p style="margin:0 0 32px;color:#64748b;line-height:1.7;font-size:13px;">
              This link expires in <strong style="color:#e2e8f0;">24 hours</strong>. If you did not create an account, you can safely ignore this email.
            </p>
            <div style="text-align:center;">
              <a href="{$verifyUrl}" style="display:inline-block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none;padding:16px 40px;border-radius:10px;font-size:16px;font-weight:700;letter-spacing:.3px;">
                ✅ Verify my email
              </a>
            </div>
            <p style="margin:32px 0 0;color:#475569;font-size:12px;text-align:center;">
              Or copy this link into your browser:<br>
              <span style="color:#818cf8;word-break:break-all;">{$verifyUrl}</span>
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="padding:24px 40px;border-top:1px solid rgba(99,102,241,.2);text-align:center;">
            <p style="margin:0;color:#475569;font-size:12px;">
              Sent to <strong style="color:#94a3b8;">{$toEmail}</strong><br>
              &copy; 2025 EventOra · Universiti Teknologi Malaysia
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        self::send($toEmail, $toName, $subject, $html);
    }

    /**
     * Send the password-reset email containing the one-time link.
     *
     * @param string $resetUrl  Full URL: http://localhost:5173/reset-password?token=<hex>
     */
    public static function sendPasswordReset(string $toEmail, string $toName, string $resetUrl): void
    {
        $subject = 'Reset your EventOra password 🔐';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f1a;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:16px;overflow:hidden;border:1px solid rgba(239,68,68,.25);">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#dc2626,#9f1239);padding:40px 40px 32px;text-align:center;">
            <div style="width:64px;height:64px;background:rgba(255,255,255,.2);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
              <span style="font-size:28px;">🔐</span>
            </div>
            <h1 style="margin:0;font-size:28px;color:#fff;letter-spacing:-0.5px;">Password reset</h1>
            <p style="margin:8px 0 0;color:rgba(255,255,255,.8);font-size:14px;">EventOra · UTM Campus Event Management</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:40px;">
            <h2 style="margin:0 0 12px;color:#e2e8f0;font-size:20px;">Hi {$toName},</h2>
            <p style="margin:0 0 16px;color:#94a3b8;line-height:1.7;font-size:15px;">
              We received a request to reset the password for your <strong style="color:#fca5a5;">EventOra</strong> account.
              Click the button below to choose a new password.
            </p>
            <p style="margin:0 0 32px;color:#64748b;line-height:1.7;font-size:13px;">
              This link expires in <strong style="color:#e2e8f0;">1 hour</strong>. If you did not request a password reset, no action is needed — your password has not been changed.
            </p>
            <div style="text-align:center;">
              <a href="{$resetUrl}" style="display:inline-block;background:linear-gradient(135deg,#dc2626,#9f1239);color:#fff;text-decoration:none;padding:16px 40px;border-radius:10px;font-size:16px;font-weight:700;letter-spacing:.3px;">
                🔑 Reset my password
              </a>
            </div>
            <p style="margin:32px 0 0;color:#475569;font-size:12px;text-align:center;">
              Or copy this link into your browser:<br>
              <span style="color:#fca5a5;word-break:break-all;">{$resetUrl}</span>
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="padding:24px 40px;border-top:1px solid rgba(239,68,68,.2);text-align:center;">
            <p style="margin:0;color:#475569;font-size:12px;">
              Sent to <strong style="color:#94a3b8;">{$toEmail}</strong><br>
              &copy; 2025 EventOra · Universiti Teknologi Malaysia
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        self::send($toEmail, $toName, $subject, $html);
    }
}
