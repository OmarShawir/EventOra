<?php

namespace App\Controllers;

use App\Auth\JwtHelper;
use App\Database\Connection;
use App\JsonResponse;
use App\Mailer;
use App\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Front-end base URL used when building email links. */
    private function frontendUrl(): string
    {
        return rtrim(getenv('FRONTEND_URL') ?: $_ENV['FRONTEND_URL'] ?? 'http://localhost:5173', '/');
    }

    /** API base URL used when building verification links that the backend handles. */
    private function apiUrl(): string
    {
        return rtrim(getenv('APP_URL') ?: $_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
    }

    /** Generate a cryptographically random 64-char hex token. */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function findUserById(\PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    private function findUserByEmail(\PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /** Strips sensitive columns before sending a user object to the client. */
    private function publicUser(array $user): array
    {
        return [
            'id'       => (int) $user['id'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'role'     => $user['role'],
            'society'  => $user['society'],
            'initials' => $this->initials($user['name']),
        ];
    }

    private function initials(string $name): string
    {
        $parts  = preg_split('/\s+/', trim($name));
        $first  = $parts[0][0] ?? '';
        $second = $parts[1][0] ?? '';
        return strtoupper($first . $second);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /auth/register
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Body: { name, email, password, matricNo? }
     * Creates the account (unverified) and sends a verification email.
     * Does NOT return a JWT — the user must verify their email first.
     */
    public function register(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);

        $errors = Validator::check($data, [
            'name'     => 'required|minlen:2',
            'email'    => 'required|email',
            'password' => 'required|minlen:8',
        ]);
        if ($errors) {
            return JsonResponse::error($response, 'Validation failed.', 422, ['fields' => $errors]);
        }

        $pdo = Connection::get();

        $existing = $pdo->prepare('SELECT id, email_verified FROM users WHERE email = ?');
        $existing->execute([$data['email']]);
        $row = $existing->fetch();

        if ($row) {
            if ((int) $row['email_verified'] === 0) {
                // Account exists but is unverified — resend verification email
                return $this->resendVerification($pdo, $response, $data['email']);
            }
            return JsonResponse::error($response, 'An account with this email already exists.', 409);
        }

        $hash  = password_hash($data['password'], PASSWORD_BCRYPT);
        $token = $this->generateToken();
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, matric_no, email_verified, verify_token, verify_token_expiry)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['email'],
            $hash,
            'attendee',
            $data['matricNo'] ?? null,
            $token,
            $expiry,
        ]);

        $verifyUrl = $this->apiUrl() . '/auth/verify-email?token=' . $token;

        try {
            Mailer::sendVerification($data['email'], $data['name'], $verifyUrl);
        } catch (\Throwable) {
            // SMTP failure must not block account creation
        }

        return JsonResponse::send($response, [
            'message' => 'Account created! Please check your UTM email and click the verification link to activate your account.',
        ], 202);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /auth/login
    // ─────────────────────────────────────────────────────────────────────────

    /** Body: { email, password } */
    public function login(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);

        $errors = Validator::check($data, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);
        if ($errors) {
            return JsonResponse::error($response, 'Validation failed.', 422, ['fields' => $errors]);
        }

        $pdo  = Connection::get();
        $user = $this->findUserByEmail($pdo, $data['email']);

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            return JsonResponse::error($response, 'Invalid email or password.', 401);
        }

        // Block unverified accounts
        if ((int) $user['email_verified'] === 0) {
            return JsonResponse::error(
                $response,
                'Please verify your email address before logging in. Check your inbox for the verification link.',
                403,
                ['unverified' => true]
            );
        }

        $token = JwtHelper::issue($user);

        return JsonResponse::send($response, [
            'token' => $token,
            'user'  => $this->publicUser($user),
        ]);
    }

    /** POST /auth/google */
    public function googleLogin(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $idToken = $data['credential'] ?? '';

        if (!$idToken) {
            return JsonResponse::error($response, 'Google ID token is required.', 400);
        }

        // Verify ID token via Google's tokeninfo API
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
        
        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 5.0
            ]
        ]);
        
        $tokenInfoJson = @file_get_contents($url, false, $context);
        if (!$tokenInfoJson) {
            return JsonResponse::error($response, 'Failed to verify Google token (network timeout).', 502);
        }

        $info = json_decode($tokenInfoJson, true);
        if (isset($info['error']) || !isset($info['email'])) {
            return JsonResponse::error($response, 'Invalid or expired Google token: ' . ($info['error_description'] ?? 'unknown error'), 401);
        }

        // Optional: verify audience (aud) matches your Google Client ID
        $googleClientId = getenv('GOOGLE_CLIENT_ID') ?: $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        if ($googleClientId && $info['aud'] !== $googleClientId) {
            return JsonResponse::error($response, 'Google token audience mismatch.', 401);
        }

        $email = $info['email'];
        $name = $info['name'] ?? explode('@', $email)[0];

        $pdo = Connection::get();
        $user = $this->findUserByEmail($pdo, $email);

        if (!$user) {
            // Register a new attendee user
            $randomPassword = bin2hex(random_bytes(16));
            $hash = password_hash($randomPassword, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, email_verified)
                 VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $name,
                $email,
                $hash,
                'attendee'
            ]);

            $user = $this->findUserByEmail($pdo, $email);
        } else {
            if ((int) $user['email_verified'] === 0) {
                $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = ?')->execute([$user['id']]);
                $user['email_verified'] = 1;
            }
        }

        $token = JwtHelper::issue($user);

        return JsonResponse::send($response, [
            'token' => $token,
            'user'  => $this->publicUser($user),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /auth/verify-email?token=<hex>
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Validates the one-time token, marks the account verified, issues a JWT,
     * then redirects the browser to the frontend /auth/verified?token=<jwt>
     * so the Vue app can log the user in automatically.
     */
    public function verifyEmail(Request $request, Response $response): Response
    {
        $token = $request->getQueryParams()['token'] ?? '';

        if (!$token) {
            return $this->badVerifyRedirect($response, 'missing');
        }

        $pdo  = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE verify_token = ? AND verify_token_expiry > NOW()'
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->badVerifyRedirect($response, 'invalid');
        }

        // Mark verified, clear token
        $pdo->prepare(
            'UPDATE users SET email_verified = 1, verify_token = NULL, verify_token_expiry = NULL WHERE id = ?'
        )->execute([$user['id']]);

        $user['email_verified'] = 1;
        $jwt = JwtHelper::issue($user);

        // Redirect to frontend with JWT in query param — Vue picks it up and stores it
        $redirectUrl = $this->frontendUrl() . '/auth/verified?token=' . urlencode($jwt);
        return $response
            ->withHeader('Location', $redirectUrl)
            ->withStatus(302);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /auth/forgot-password
    // ─────────────────────────────────────────────────────────────────────────

    /** Body: { email }  — Always returns 200 to prevent email enumeration. */
    public function forgotPassword(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);

        $errors = Validator::check($data, ['email' => 'required|email']);
        if ($errors) {
            return JsonResponse::error($response, 'Validation failed.', 422, ['fields' => $errors]);
        }

        $pdo  = Connection::get();
        $user = $this->findUserByEmail($pdo, $data['email']);

        // Always succeed — do nothing if email not found (prevents enumeration)
        if ($user && (int) $user['email_verified'] === 1) {
            $token  = $this->generateToken();
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare(
                'UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?'
            )->execute([$token, $expiry, $user['id']]);

            $resetUrl = $this->frontendUrl() . '/reset-password?token=' . $token;

            try {
                Mailer::sendPasswordReset($user['email'], $user['name'], $resetUrl);
            } catch (\Throwable) {
                // Swallow SMTP errors silently
            }
        }

        return JsonResponse::send($response, [
            'message' => 'If that email is registered and verified, a password reset link has been sent.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /auth/reset-password
    // ─────────────────────────────────────────────────────────────────────────

    /** Body: { token, password } */
    public function resetPassword(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);

        $errors = Validator::check($data, [
            'token'    => 'required',
            'password' => 'required|minlen:8',
        ]);
        if ($errors) {
            return JsonResponse::error($response, 'Validation failed.', 422, ['fields' => $errors]);
        }

        $pdo  = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()'
        );
        $stmt->execute([$data['token']]);
        $user = $stmt->fetch();

        if (!$user) {
            return JsonResponse::error(
                $response,
                'This reset link is invalid or has expired. Please request a new one.',
                400
            );
        }

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);

        $pdo->prepare(
            'UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?'
        )->execute([$hash, $user['id']]);

        return JsonResponse::send($response, [
            'message' => 'Password updated successfully. You can now log in with your new password.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /auth/me  (protected)
    // ─────────────────────────────────────────────────────────────────────────

    public function me(Request $request, Response $response): Response
    {
        $jwt  = $request->getAttribute('jwt');
        $pdo  = Connection::get();
        $user = $this->findUserById($pdo, (int) $jwt['sub']);

        if (!$user) {
            return JsonResponse::error($response, 'User not found.', 404);
        }

        return JsonResponse::send($response, ['user' => $this->publicUser($user)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Organiser Bank Details (protected)
    // ─────────────────────────────────────────────────────────────────────────

    public function getBankDetails(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $pdo = Connection::get();
        $user = $this->findUserById($pdo, (int) $jwt['sub']);

        if (!$user) {
            return JsonResponse::error($response, 'User not found.', 404);
        }

        return JsonResponse::send($response, [
            'bankName' => $user['bank_name'] ?? '',
            'bankAccountNo' => $user['bank_account_no'] ?? '',
            'bankAccountHolder' => $user['bank_account_holder'] ?? '',
            'stripeConnectId' => $user['stripe_connect_id'] ?? '',
        ]);
    }

    public function updateBankDetails(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $data = (array) ($request->getParsedBody() ?? []);

        $errors = Validator::check($data, [
            'bankName' => 'required',
            'bankAccountNo' => 'required',
            'bankAccountHolder' => 'required',
        ]);
        if ($errors) {
            return JsonResponse::error($response, 'Validation failed.', 422, ['fields' => $errors]);
        }

        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'UPDATE users SET bank_name = ?, bank_account_no = ?, bank_account_holder = ?, stripe_connect_id = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['bankName'],
            $data['bankAccountNo'],
            $data['bankAccountHolder'],
            $data['stripeConnectId'] ?? null,
            (int) $jwt['sub']
        ]);

        return JsonResponse::send($response, [
            'message' => 'Bank details updated successfully.',
        ]);
    }

    public function getOrganisers(Request $request, Response $response): Response
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare('
            SELECT id, name, email, role, society, bank_name, bank_account_no, bank_account_holder, stripe_connect_id 
            FROM users 
            WHERE role = "organiser"
        ');
        $stmt->execute();
        $organisers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return JsonResponse::send($response, [
            'organisers' => $organisers
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Re-send the verification email to an already-registered but unverified address.
     * Issues a fresh token so the old (possibly expired) one is replaced.
     */
    private function resendVerification(\PDO $pdo, Response $response, string $email): Response
    {
        $user   = $this->findUserByEmail($pdo, $email);
        $token  = $this->generateToken();
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $pdo->prepare(
            'UPDATE users SET verify_token = ?, verify_token_expiry = ? WHERE email = ?'
        )->execute([$token, $expiry, $email]);

        $verifyUrl = $this->apiUrl() . '/auth/verify-email?token=' . $token;

        try {
            Mailer::sendVerification($email, $user['name'], $verifyUrl);
        } catch (\Throwable) {
            // Swallow SMTP errors
        }

        return JsonResponse::send($response, [
            'message' => 'A new verification link has been sent to your email address.',
        ], 202);
    }

    /** Redirect browser to a frontend error page when verification fails. */
    private function badVerifyRedirect(Response $response, string $reason): Response
    {
        $url = $this->frontendUrl() . '/auth/verified?error=' . $reason;
        return $response->withHeader('Location', $url)->withStatus(302);
    }
}
