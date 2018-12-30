<?php

namespace Tests\Feature;

use App\Mail\PleaseConfirmYourEmail;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

class RegistrationTest extends TestCase {

    use RefreshDatabase;

    /** @test * */
    public function a_comfirmation_email_is_send_upon_registration()
    {
        Mail::fake();

        $this->post(route('register'), [
            'name'                  => 'Jane',
            'email'                 => 'jane@example.com',
            'password'              => 'mehmet',
            'password_confirmation' => 'mehmet',
        ]);

        Mail::assertQueued(PleaseConfirmYourEmail::class);
    }

    /** @test * */
    function user_can_fully_confirmed_their_email_addresses()
    {
        Mail::fake();
        $this->post(route('register'), [
            'name'                  => 'Jane',
            'email'                 => 'jane@example.com',
            'password'              => 'mehmet',
            'password_confirmation' => 'mehmet',
        ]);

        $user = User::whereName('Jane')->first();

        $this->assertFalse($user->confirmed);
        $this->assertNotNull($user->confirmation_token);

        $this->get(route('register.confirm', [ 'token' => $user->confirmation_token]))
                ->assertRedirect(route('threads'));

        tap($user->fresh(), function($user){
            $this->assertTrue($user->confirmed);
            $this->assertNull($user->confirmation_token);
        });
    }
    
    /** @test **/
    function confirming_an_invalid_token()
    {

        $this->get(route('register.confirm', [ 'token' => 'invalid']))
        ->assertRedirect(route('threads'))
        ->assertSessionHas('flash','Unknown token.');
    }
}