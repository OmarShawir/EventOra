<?php

namespace App;

/**
 * Minimal SMTP mailer that uses raw PHP stream sockets.
 *
 * Requires no additional Composer dependencies — reads credentials
 * from the environment variables loaded by Env::load():
 *   EMAIL_HOST, EMAIL_PORT, EMAIL_USER, EMAIL_PASS
 *
 * Sends over STARTTLS (port 587). For pure SSL (port 465), change
 * the socket prefix to "ssl://" and remove the STARTTLS handshake.
 */
class Mailer
{
    private static function env(string $key, string $default = ''): string
    {
        return (string) (getenv($key) ?: $_ENV[$key] ?? $default);
    }

    /**
     * Send a plain-text + HTML email via SMTP STARTTLS.
     *
     * @throws \RuntimeException if the SMTP conversation fails
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): void {
        $host = self::env('EMAIL_HOST', 'smtp.gmail.com');
        $port = (int) self::env('EMAIL_PORT', '587');
        $user = self::env('EMAIL_USER');
        $pass = self::env('EMAIL_PASS');

        if (!$textBody) {
            $textBody = strip_tags($htmlBody);
        }

        $isSsl = ($port === 465);
        $protocol = $isSsl ? 'ssl://' : 'tcp://';
        
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        $socket = @stream_socket_client($protocol . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new \RuntimeException("SMTP connect failed [{$errno}]: {$errstr}");
        }

        $read = fn() => fgets($socket, 515);
        $send = function (string $cmd) use ($socket): void {
            fwrite($socket, $cmd . "\r\n");
        };

        // ── SMTP handshake ────────────────────────────────────────────────────
        $read(); // 220 banner

        $send('EHLO localhost');
        while (true) {
            $line = $read();
            if ($line === false || strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        if (!$isSsl) {
            // Upgrade to TLS via STARTTLS
            $send('STARTTLS');
            $read(); // 220 Ready

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new \RuntimeException('STARTTLS crypto handshake failed.');
            }

            $send('EHLO localhost');
            while (true) {
                $line = $read();
                if ($line === false || strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
        }

        // ── AUTH LOGIN ────────────────────────────────────────────────────────
        $send('AUTH LOGIN');
        $read(); // 334 Username:
        $send(base64_encode($user));
        $read(); // 334 Password:
        $send(base64_encode($pass));
        $resp = $read();
        if (!str_starts_with(trim($resp), '235')) {
            fclose($socket);
            throw new \RuntimeException("SMTP AUTH failed: {$resp}");
        }

        // ── Envelope ─────────────────────────────────────────────────────────
        $send("MAIL FROM:<{$user}>");
        $read();
        $send("RCPT TO:<{$toEmail}>");
        $read();
        $send('DATA');
        $read(); // 354 Start input

        // ── Build MIME message ────────────────────────────────────────────────
        $boundary = 'b_' . bin2hex(random_bytes(8));
        $date     = date('r');
        $fromName = 'EventOra';

        $headers  = "Date: {$date}\r\n";
        $headers .= "From: {$fromName} <{$user}>\r\n";
        $headers .= "To: {$toName} <{$toEmail}>\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= $textBody . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $htmlBody . "\r\n";
        $body .= "--{$boundary}--\r\n";

        fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        $resp = $read(); // 250 OK
        if (!str_starts_with(trim($resp), '250')) {
            fclose($socket);
            throw new \RuntimeException("SMTP DATA rejected: {$resp}");
        }

        $send('QUIT');
        $read();
        fclose($socket);
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
