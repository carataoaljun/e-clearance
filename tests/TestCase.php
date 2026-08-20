<?php

namespace Tests;

use App\Support\LoginChallenge;
use App\Support\TrustedDevice;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sign in through a portal the way a browser does, including the emailed
     * code every portal now demands from a device it has not seen before.
     *
     * The code itself is unknowable from a test, so the challenge's hash is
     * swapped for one of a code the test picked before posting it back.
     */
    protected function loginThroughDeviceCode(
        string $guard,
        string $loginRoute,
        array $credentials,
        string $otpRoute,
        string $code = '123456',
    ): TestResponse {
        $response = $this->post(route($loginRoute), $credentials);
        $challenge = session(LoginChallenge::sessionKey($guard));

        // A trusted device or a rejected password never raises a challenge.
        if (! is_array($challenge)) {
            return $response;
        }

        $challenge['code_hash'] = Hash::make($code);

        return $this->withSession([LoginChallenge::sessionKey($guard) => $challenge])
            ->post(route($otpRoute), ['verification_code' => $code]);
    }

    /**
     * The cookie a verified device carries, ready to hand to the next request
     * via withCookie() so it can skip the code.
     */
    protected function verifiedDeviceCookie(TestResponse $response): ?string
    {
        return $response->getCookie(TrustedDevice::COOKIE)?->getValue();
    }
}
