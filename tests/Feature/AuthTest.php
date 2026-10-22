<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_screen_asks_team_or_individual(): void
    {
        $this->get('/entry')
            ->assertOk()
            ->assertSee('団体でお申し込み')
            ->assertSee('個人でお申し込み')
            ->assertSee(route('team.login'))
            ->assertSee(route('individual-entry.create'));
    }

    public function test_pages_requiring_login_send_guests_to_the_first_screen(): void
    {
        $this->get('/entries')->assertRedirect('/login');
        $this->get('/login')->assertRedirect('/entry');
    }

    public function test_team_can_register_and_is_logged_in(): void
    {
        $this->post('/team/register', $this->teamForm())->assertRedirect(route('athletes.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'type' => 'team', 'team_name' => '絆AC', 'login_name' => '絆ac', 'email' => 'team@example.com',
        ]);
    }

    public function test_team_name_must_be_unique_ignoring_width_case_and_spaces(): void
    {
        User::factory()->create(['team_name' => '絆 AC']);

        $this->post('/team/register', $this->teamForm(['team_name' => '絆ａｃ']))
            ->assertSessionHasErrors('team_name');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_team_opens_its_data_with_email_and_password(): void
    {
        $team = User::factory()->create(['team_name' => '絆AC', 'email' => 'team@example.com']);
        Athlete::factory()->for($team)->create(['last_name' => '青山']);

        $this->post('/team/login', ['email' => ' Team@Example.com ', 'password' => 'password'])
            ->assertRedirect(route('entry.web'));
        $this->assertAuthenticatedAs($team);

        $this->get(route('athletes.index'))->assertSee('青山');
    }

    public function test_team_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['team_name' => '絆AC', 'email' => 'team@example.com']);

        $this->post('/team/login', ['email' => 'team@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_individual_cannot_log_in_through_team_login(): void
    {
        User::factory()->individual()->create(['team_name' => '〇〇大学', 'email' => 'solo@example.com']);

        $this->post('/team/login', ['email' => 'solo@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_individual_registers_with_own_athlete_data(): void
    {
        $this->post('/individual/register', [
            'last_name' => '中村', 'first_name' => '光',
            'kana' => 'なかむら ひかる',
            'gender' => 'M', 'category' => '大学生', 'grade' => '2', 'association' => '東京',
            'team_name' => '〇〇大学',
            'phone' => '090-0000-0000',
            'email' => 'solo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'agree' => '1',
        ])->assertRedirect(route('entry.web'));

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->isIndividual());
        $this->assertSame('中村 光', $user->name);
        $this->assertNull($user->login_name);
        $this->assertDatabaseHas('athletes', ['user_id' => $user->id, 'kana' => 'ナカムラ ヒカル', 'grade' => 2, 'association' => '東京']);
    }

    public function test_individual_logs_in_with_email(): void
    {
        $user = User::factory()->individual()->create(['email' => 'solo@example.com']);

        $this->post('/individual/login', ['email' => 'solo@example.com', 'password' => 'password'])
            ->assertRedirect(route('entry.web'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_team_cannot_log_in_through_individual_login(): void
    {
        User::factory()->create(['email' => 'team@example.com']);

        $this->post('/individual/login', ['email' => 'team@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_individual_cannot_add_more_athletes(): void
    {
        $user = User::factory()->individual()->create();

        $this->actingAs($user)->get(route('athletes.create'))->assertNotFound();
        $this->actingAs($user)->get(route('athletes.import'))->assertNotFound();
    }

    private function teamForm(array $overrides = []): array
    {
        return $overrides + [
            'team_name' => '絆AC',
            'name' => '代表 花子',
            'phone' => '090-1234-5678',
            'email' => 'team@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'agree' => '1',
        ];
    }

    public function test_entry_button_always_opens_method_choice_even_after_login(): void
    {
        $team = User::factory()->create(['team_name' => '絆AC']);

        $this->actingAs($team)->get(route('home'))->assertOk()
            ->assertSee('エクセルを添付して申し込む')
            ->assertSee('Webエントリーへ進む')
            ->assertSee(route('entry.web'));
        $this->actingAs($team)->get(route('entry.web'))->assertOk()->assertSee('絆AC');
    }
}
