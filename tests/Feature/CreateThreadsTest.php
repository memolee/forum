<?php

namespace Tests\Feature;

use App\Activity;
use App\Rules\Recaptcha;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreateThreadsTest extends TestCase {

    use RefreshDatabase,MockeryPHPUnitIntegration;

    protected function setUp()
    {
        parent::setUp();

        app()->singleton(Recaptcha::class, function () {

            return \Mockery::mock(Recaptcha::class, function ($m) {

                $m->shouldReceive('passes')->andReturn(true);
            });

        });

    }

    /** @test * */
    function guests_may_not_create_threads()
    {
        $this->withExceptionHandling();

        $this->get('/threads/create')
            ->assertRedirect(route('login'));

        $this->post(route('threads'))
            ->assertRedirect(route('login'));
    }

    /** @test * */
    function new_users_must_first_confirm_their_email_address_before_creating_threads()
    {
        $user = factory('App\User')->states('unconfirmed')->create();
        $this->signIn($user);

        $thread = make('App\Thread');

        $this->post(route('threads'), $thread->toArray())
            ->assertRedirect(route('threads'))
            ->assertSessionHas('flash', 'You must confirm your email address.');
    }

    /** @test * */
    function a_user_can_create_new_forum_threads()
    {
        $response = $this->publishThread(['title' => 'Some Title', 'body' => 'Some body.']);
        $this->get($response->headers->get('Location'))
            ->assertSee('Some Title')
            ->assertSee('Some body.');
    }

    /** @test * */
    function a_thread_requires_a_title()
    {
        $this->publishThread(['title' => null])
            ->assertSessionHasErrors('title');
    }

    /** @test * */
    function a_thread_requires_a_body()
    {
        $this->publishThread(['body' => null])
            ->assertSessionHasErrors('body');
    }
    
    /** @test **/
    function a_thread_requires_recaptcha_verification()
    {
        unset(app()[Recaptcha::class]);
        $this->publishThread(['g-recaptcha-response'=>'test'])
            ->assertSessionHasErrors('g-recaptcha-response');

    }

    /** @test * */
    function a_thread_requires_a_valid_channel()
    {
        factory('App\Channel', 2)->create();

        $this->publishThread(['channel_id' => null])
            ->assertSessionHasErrors('channel_id');

        $this->publishThread(['channel_id' => 999])
            ->assertSessionHasErrors('channel_id');
    }

    /** @test * */
    function a_thread_requires_a_unique_slug()
    {
        $this->signIn();

        $thread = create('App\Thread', ['title' => 'Foo title']);

        $this->assertEquals($thread->fresh()->slug, 'foo-title');

        $thread =$this->postJson(route('threads'), $thread->toArray() + ['g-recaptcha-response'=> 'token'])->json();

        $this->assertEquals("foo-title-{$thread['id']}",$thread['slug']);
    }
    
    /** @test **/
    function a_thread_with_a_title_thats_end_in_a_number_should_generate_the_proper_slug()
    {
        $this->signIn();
        $thread = create('App\Thread', ['title' => 'Some title 24']);

        $thread =$this->postJson(route('threads'), $thread->toArray() + ['g-recaptcha-response' =>'token'])->json();

        $this->assertEquals("some-title-24-{$thread['id']}",$thread['slug']);
    }
    

    /** @test * */
    function unauthorized_users_may_not_delete_threads()
    {
        $this->withExceptionHandling();

        $thread = create('App\Thread');

        $this->delete($thread->path())->assertRedirect(route('login'));

        $this->signIn();
        $this->delete($thread->path())->assertStatus(403);

    }

    /** @test * */
    function authorized_users_can_delete_threads()
    {
        $this->signIn();

        $thread = create('App\Thread', ['user_id' => auth()->id()]);
        $reply = create('App\Reply', ['thread_id' => $thread->id]);

        $response = $this->json('DELETE', $thread->path());

        $response->assertStatus(204);

        $this->assertDatabaseMissing('threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('replies', ['id' => $reply->id]);

        $this->assertEquals(0, Activity::count());

    }

    public function publishThread($overrides = [])
    {
        $this->withExceptionHandling()->signIn();

        $thread = make('App\Thread', $overrides);

        return $this->post(route('threads'), $thread->toArray() + ['g-recaptcha-response' => 'token']);
    }
}
