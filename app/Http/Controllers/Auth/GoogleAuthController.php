<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\RoleName;
use App\UserStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect('/');
        }

        if (! $this->isConfigured() || parse_url(config('services.google.redirect_uri'), PHP_URL_HOST) !== $request->getHost()) {
            return $this->failure('unavailable');
        }

        $state = Str::random(64);
        $verifier = Str::random(96);
        $request->session()->put('google_oauth', [
            'state' => $state,
            'verifier' => $verifier,
            'expires_at' => now()->addMinutes(10)->timestamp,
            'destination' => $this->safeDestination($request->query('redirect')),
            'locale' => in_array($request->query('locale'), ['ar', 'en'], true) ? $request->query('locale') : 'ar',
        ]);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull('google_oauth');
        $state = $request->query('state');
        $code = $request->query('code');

        if (! $this->isConfigured() || ! is_array($pending)
            || ! is_string($state) || ! is_string($pending['state'] ?? null)
            || ! hash_equals($pending['state'], $state)
            || ! is_int($pending['expires_at'] ?? null) || $pending['expires_at'] < now()->timestamp
            || ! is_string($pending['verifier'] ?? null)
            || ! is_string($code) || $code === '' || $request->has('error')) {
            return $this->failure('failed');
        }

        try {
            $tokenResponse = Http::asForm()->connectTimeout(3)->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => config('services.google.client_id'),
                    'client_secret' => config('services.google.client_secret'),
                    'redirect_uri' => config('services.google.redirect_uri'),
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'code_verifier' => $pending['verifier'],
                ]);

            if (! $tokenResponse->successful() || ! is_string($tokenResponse->json('access_token')) || $tokenResponse->json('access_token') === '') {
                return $this->failure('failed');
            }

            $profileResponse = Http::withToken($tokenResponse->json('access_token'))
                ->connectTimeout(3)->timeout(10)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');
        } catch (ConnectionException) {
            return $this->failure('failed');
        }

        if (! $profileResponse->successful()) {
            return $this->failure('failed');
        }

        $profile = $profileResponse->json();
        if (! is_array($profile)) {
            return $this->failure('failed');
        }
        $subject = $profile['sub'] ?? null;
        $email = $profile['email'] ?? null;
        $name = $profile['name'] ?? null;

        if (! is_string($subject) || $subject === '' || mb_strlen($subject) > 255
            || ! is_string($email) || mb_strlen($email) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($profile['email_verified'] ?? false) !== true) {
            return $this->failure('failed');
        }

        $wasCreated = false;
        try {
            $user = DB::transaction(function () use ($subject, $email, $name, $pending, $profile, &$wasCreated): ?User {
                $normalizedEmail = mb_strtolower(trim($email));
                $user = User::withTrashed()->where('google_id', $subject)->first();

                if (! $user) {
                    $user = User::withTrashed()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();
                }

                if ($user) {
                    if ($user->trashed() || $user->status !== UserStatus::Active
                        || ! $user->hasRole(RoleName::Student->value)
                        || $user->roles()->where('name', '!=', RoleName::Student->value)->exists()
                        || ($user->google_id !== null && $user->google_id !== $subject)
                        || ($user->google_id === null && ! $this->isGoogleAuthoritativeForEmail($normalizedEmail, $profile))) {
                        return null;
                    }

                    $user->forceFill([
                        'google_id' => $subject,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ])->save();

                    return $user;
                }

                $user = User::create([
                    'name' => is_string($name) && trim($name) !== '' ? Str::limit(trim($name), 255, '') : $normalizedEmail,
                    'email' => $normalizedEmail,
                    'password' => Str::random(64),
                    'preferred_locale' => $pending['locale'],
                    'status' => UserStatus::Active,
                ]);
                $user->forceFill(['google_id' => $subject, 'email_verified_at' => now()])->save();
                $user->assignRole(Role::findOrCreate(RoleName::Student->value));
                $wasCreated = true;

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->failure('failed');
        }

        if (! $user) {
            return $this->failure('account');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $destination = $pending['destination'] === '/student/cart'
            ? '/student/cart?welcome='.($wasCreated ? 'registered' : 'login')
            : $pending['destination'];

        return redirect()->to($destination);
    }

    private function isConfigured(): bool
    {
        foreach (['client_id', 'client_secret', 'redirect_uri'] as $key) {
            if (! is_string(config("services.google.{$key}")) || trim(config("services.google.{$key}")) === '') {
                return false;
            }
        }

        return str_starts_with(config('services.google.redirect_uri'), 'https://');
    }

    /** @param array<string, mixed> $profile */
    private function isGoogleAuthoritativeForEmail(string $email, array $profile): bool
    {
        if (str_ends_with($email, '@gmail.com')) {
            return true;
        }

        $domain = substr(strrchr($email, '@'), 1);

        return is_string($profile['hd'] ?? null) && strcasecmp($profile['hd'], $domain) === 0;
    }

    private function safeDestination(mixed $destination): string
    {
        if (! is_string($destination) || ! preg_match('/\A\/(?!\/)[^\x00-\x1F\\\\]*\z/u', $destination)) {
            return '/student';
        }

        return $destination;
    }

    private function failure(string $reason): RedirectResponse
    {
        return redirect()->to('/login?google_error='.$reason);
    }
}
