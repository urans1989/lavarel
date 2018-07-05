<?php

namespace Tests\Unit\App\Domain\Accounts\Verification;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery as m;

use App\Domain\Accounts\Account;
use App\Domain\Accounts\Verification\VerifyCode;
use App\Domain\Accounts\Verification\VerifyCodeService;

class VerifyCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_GivenAuthenticated_RedirectsToHome()
    {
        // Given
        $this->register();

        // When
        $response = $this->get(route('verify-codes.create'));

        // Then
        $response->assertRedirect(route('page/home'));
    }

    public function test_create_GivenGuest_ShowsForm()
    {
        // Given
        $this->assertGuest();

        // When
        $response = $this->get(route('verify-codes.create'));

        // Then
        $response->assertStatus(200);
    }

    public function test_show_GivenAuthenticated_RedirectsToHome()
    {
        // Given
        $this->register();

        // When
        $response = $this->get(route('verify-codes.show', 'invalid'));

        // Then
        $response->assertRedirect(route('page/home'));
    }

    public function test_show_GivenInvalidCode_RedirectsToLoginAndShowsFlash()
    {
        // Given
        $this->assertGuest();

        // When
        $response = $this->get(route('verify-codes.show', 'invalid'));

        // Then
        $response->assertRedirect(route('session.create'));
        $response->assertSessionHas('message', trans('accounts.verification.confirmation'));
    }

    public function test_show_GivenValidCode_RedirectsToLoginAndShowsFlash()
    {
        // Given
        $this->assertGuest();
        $code = factory(VerifyCode::class)->create();

        // When
        $response = $this->get(route('verify-codes.show', $code->code));

        // Then
        $response->assertRedirect(route('session.create'));
        $response->assertSessionHas('message', trans('accounts.verification.confirmation'));
    }

    public function test_show_GivenCodeExists_CodeDeletedAndAccountVerified()
    {
        $this->withoutExceptionHandling();

        // Given
        $code = factory(VerifyCode::class)->create();

        // When
        $response = $this->get(route('verify-codes.show', $code->code));

        // Then
        $deleted = VerifyCode::withTrashed()->find($code->id);

        $this->assertNotNull($deleted->deleted_at);
        $this->assertNotNull($deleted->account->verified_at);
    }

    public function test_show_DoesNotAuthenticate()
    {
        // Given
        $code = factory(VerifyCode::class)->create();

        // Then
        $this->get(route('verify-codes.show', $code->code));

        // When
        $this->assertGuest();
    }

    public function test_store_GivenEmailExists_CallsVerifyCodeService()
    {
        // Given
        $account = factory(Account::class)->create();

        // Then
        $mock = m::mock(VerifyCodeService::class);
        $mock->shouldReceive('createForAccount')
            ->once()
            ->withArgs(function ($actual) use ($account) {
                $this->assertEquals($account->id, $actual->id);
                return true;
            });

        $this->app->instance(VerifyCodeService::class, $mock);

        // When
        $this->json('post', route('verify-codes.store'), $account->only('email'));
    }

    public function test_store_GivenEmailDoesNotExists_CallsVerifyCodeService()
    {
        // Given
        $account = factory(Account::class)->make();

        // Then
        $mock = m::mock(VerifyCodeService::class);
        $mock->shouldNotReceive('createForAccount');

        $this->app->instance(VerifyCodeService::class, $mock);

        // When
        $this->json('post', route('verify-codes.store'), $account->only('email'));
    }

}
