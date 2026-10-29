<?php

namespace Tests\Feature\Auth;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    public function test_state_changing_request_without_a_valid_token_is_rejected_by_csrf_middleware(): void
    {
        $session = new Store('csrf-test', new ArraySessionHandler(120));
        $session->start();

        $request = Request::create('/login', 'POST', ['_token' => 'invalid-token']);
        $request->setLaravelSession($session);

        $middleware = new class(app(), app(Encrypter::class)) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        try {
            $middleware->handle($request, fn () => new Response('unexpectedly accepted'));
            $this->fail('The CSRF middleware accepted a mismatched request token.');
        } catch (TokenMismatchException) {
            $this->assertTrue(true);
        }
    }
}
