<?php

namespace App\Http\Controllers\Concerns;

use App\Support\AuditLogger;
use App\Support\LoginChallenge;
use App\Support\TrustedDevice;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The device-verification half of every portal login.
 *
 * A portal's own AuthController still owns the credential check; once the
 * password is accepted it hands the account here. If the browser has verified
 * this account before, the session opens straight away. Otherwise a six-digit
 * code goes to the address on file and the session only opens once that code
 * comes back.
 *
 * The three code routes (verify/resend/cancel) are public because they are
 * routed to directly, so every portal gets the same three endpoints from the
 * six small declarations below.
 */
trait VerifiesNewDevices
{
    /** The guard this portal authenticates against, e.g. 'student'. */
    abstract protected function deviceGuard(): string;

    /** Human label for the portal, e.g. 'Student'. Used in copy and email subjects. */
    abstract protected function devicePanel(): string;

    /** Route name of this portal's login page. */
    abstract protected function deviceLoginRoute(): string;

    /** Route name to land on once the session opens. */
    abstract protected function deviceHomeRoute(): string;

    /** Re-resolve the account a pending challenge belongs to. */
    abstract protected function deviceAccount(string|int $id): ?Authenticatable;

    /** Field the login form shows errors against, e.g. 'student_id'. */
    protected function deviceErrorField(): string
    {
        return 'email';
    }

    /**
     * Whether a browser may be remembered and skip the code next time. Main
     * Admin overrides this to false: it is the portal that can reach every
     * other one, so it re-verifies on every sign-in.
     */
    protected function deviceTrustAllowed(): bool
    {
        return true;
    }

    /** Anything the portal still needs in the session after logging in. */
    protected function afterDeviceLogin(Request $request, Authenticatable $account): void
    {
        //
    }

    /**
     * Credentials have checked out. Open the session on a known device, or ask
     * for an emailed code on one we have not seen before.
     */
    protected function completeLogin(Request $request, Authenticatable $account, bool $remember = false)
    {
        if ($this->deviceTrustAllowed()
            && TrustedDevice::trusted($request, $this->deviceGuard(), $account->getAuthIdentifier())) {
            return $this->openSession($request, $account, $remember);
        }

        $request->session()->regenerate();

        return $this->sendDeviceCode($request, $account, $remember);
    }

    public function verifyLoginCode(Request $request)
    {
        $data = $request->validate([
            'verification_code' => ['required', 'digits:6'],
        ], [
            'verification_code.required' => 'Enter the six-digit sign-in code.',
            'verification_code.digits' => 'The sign-in code must contain exactly six digits.',
        ]);

        $challenge = LoginChallenge::verify($request, $this->deviceGuard(), $data['verification_code']);
        $account = $this->deviceAccount($challenge['account_id'] ?? '');

        if (! $account) {
            return redirect()->route($this->deviceLoginRoute())->withErrors([
                $this->deviceErrorField() => 'This sign-in request is no longer valid.',
            ]);
        }

        if ($this->deviceTrustAllowed()) {
            TrustedDevice::remember($request, $this->deviceGuard(), $account->getAuthIdentifier());
        }

        AuditLogger::record('authentication.mfa_verified', $this->deviceGuard(), $account, null, null, [
            'device' => TrustedDevice::label($request),
            'trusted_for_days' => $this->deviceTrustAllowed() ? TrustedDevice::lifetimeDays() : 0,
        ]);

        return $this->openSession($request, $account, (bool) ($challenge['remember'] ?? false));
    }

    public function resendLoginCode(Request $request)
    {
        $challenge = LoginChallenge::pending($request, $this->deviceGuard());
        $account = $challenge ? $this->deviceAccount($challenge['account_id'] ?? '') : null;

        if (! $account) {
            LoginChallenge::forget($request, $this->deviceGuard());

            return redirect()->route($this->deviceLoginRoute())->withErrors([
                $this->deviceErrorField() => 'Your sign-in request expired. Please enter your password again.',
            ]);
        }

        return $this->sendDeviceCode($request, $account, (bool) ($challenge['remember'] ?? false), true);
    }

    public function cancelLoginCode(Request $request)
    {
        LoginChallenge::forget($request, $this->deviceGuard());

        return redirect()->route($this->deviceLoginRoute());
    }

    private function sendDeviceCode(Request $request, Authenticatable $account, bool $remember, bool $resend = false)
    {
        $status = LoginChallenge::send($request, $this->deviceGuard(), $this->devicePanel(), [
            'id' => $account->getAuthIdentifier(),
            'email' => $account->email ?? null,
            'name' => $this->deviceAccountName($account),
            'remember' => $remember,
        ], $resend);

        if ($status !== LoginChallenge::SENT) {
            return redirect()->route($this->deviceLoginRoute())->withErrors([
                $this->deviceErrorField() => match ($status) {
                    LoginChallenge::NO_EMAIL => 'This account has no email address on file, so a sign-in code cannot be sent. Contact your administrator.',
                    LoginChallenge::LOCKED => 'Too many incorrect sign-in codes. Please wait a few minutes before trying again.',
                    default => 'The sign-in code could not be emailed. Please try again shortly.',
                },
            ]);
        }

        return redirect()->route($this->deviceLoginRoute())->with(
            'status',
            $resend
                ? 'A new sign-in code was sent to your registered email.'
                : 'New device detected. Check your email for the six-digit sign-in code.',
        );
    }

    private function openSession(Request $request, Authenticatable $account, bool $remember)
    {
        Auth::guard($this->deviceGuard())->login($account, $remember);
        $request->session()->regenerate();
        $this->afterDeviceLogin($request, $account);

        return redirect()->route($this->deviceHomeRoute())
            ->with('login_success', 'Login successful. Welcome to the '.$this->devicePanel().' panel.');
    }

    private function deviceAccountName(Authenticatable $account): string
    {
        foreach (['full_name', 'name'] as $attribute) {
            $value = trim((string) ($account->{$attribute} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        $composed = trim(($account->firstname ?? '').' '.($account->lastname ?? ''));

        return $composed !== '' ? $composed : $this->devicePanel();
    }
}
