<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateways;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * 大会要項（区分別の参加料・部門・最小遂行人数・支払い）に合わせた動き。
 */
class GuidelineTest extends TestCase
{
    use RefreshDatabase;

    private Meet $meet;

    private Event $five;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret' => 'sk_test', 'services.komoju.secret' => 'komoju_test', 'services.komoju.fee' => 200]);
        $this->app->instance(PaymentGateways::class, new PaymentGateways([
            Entry::METHOD_CARD => fn () => new FakePaymentGateway('cs_'),
            Entry::METHOD_KONBINI => fn () => new FakePaymentGateway('komoju_'),
        ]));
        $this->meet = Meet::factory()->create([
            'name' => '第20回絆記録挑戦会', 'venue' => '町田市立陸上競技場', 'start_time' => '15:30',
            'fee_junior_high' => 1500, 'fee_student' => 2200, 'fee_general' => 3300,
        ]);
        $this->five = Event::factory()->for($this->meet)->create(['name' => '5000m', 'gender' => null, 'fee' => 0]);
    }

    public function test_fee_depends_on_category_and_konbini_fee_is_added(): void
    {
        $team = User::factory()->create();
        $junior = Athlete::factory()->for($team)->create(['category' => '中学生', 'grade' => 3]);
        $student = Athlete::factory()->for($team)->create(['category' => '大学生']);
        $adult = Athlete::factory()->for($team)->create(['category' => '一般']);

        $this->actingAs($team)->post(route('entries.review', $this->meet), ['items' => [
            $junior->id => ['selected' => '1', 'events' => [['event_id' => $this->five->id, 'record' => '1630']]],
            $student->id => ['selected' => '1', 'events' => [['event_id' => $this->five->id, 'record' => '1430']]],
            $adult->id => ['selected' => '1', 'events' => [['event_id' => $this->five->id, 'record' => '1500']]],
        ]]);
        $this->actingAs($team)->get(route('entries.confirm', $this->meet))
            ->assertSee('1,500円')->assertSee('2,200円')->assertSee('3,300円')->assertSee('7,000円')
            ->assertSee('手数料 200円・合計 7,200円')
            ->assertSee('競技者登録');

        // 競技者登録・参加料の確認にチェックがないと進めない
        $this->actingAs($team)->post(route('entries.store', $this->meet), ['payment_method' => 'konbini'])->assertSessionHasErrors('terms');
        $this->assertDatabaseCount('entries', 0);

        $this->actingAs($team)->post(route('entries.store', $this->meet), ['payment_method' => 'konbini', 'terms' => '1']);
        $entry = Entry::sole();
        $this->assertSame(200, $entry->payment_fee);
        $this->assertSame(7200, $entry->total_amount);
        $this->assertEqualsCanonicalizing([1500, 2200, 3300], $entry->items->pluck('fee')->all());
    }

    public function test_division_is_shown_in_roster_and_excel_for_awards(): void
    {
        $admin = User::factory()->admin()->create();
        foreach ([['中学', '中学生', "16'00"], ['一般', '一般', "14'30"]] as [$name, $category, $time]) {
            $team = User::factory()->create();
            $athlete = Athlete::factory()->for($team)->create(['last_name' => $name, 'category' => $category]);
            $entry = $team->entries()->create(['meet_id' => $this->meet->id, 'status' => Entry::STATUS_PAID, 'total_amount' => 2200]);
            $entry->items()->create(['athlete_id' => $athlete->id, 'event_id' => $this->five->id, 'fee' => 2200, 'declared_record' => $time]);
        }

        $this->actingAs($admin)->get(route('admin.roster.index', $this->meet))
            ->assertSee('中学生の部')->assertSee('高校・大学・一般の部');

        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, $this->actingAs($admin)->get(route('admin.entries.export', $this->meet))->streamedContent());
        $rows = IOFactory::load($path)->getSheet(0)->toArray(null, false, false, false);
        $division = array_search('部門', $rows[0], true);
        $this->assertSame(['高校・大学・一般の部', '中学生の部'], [$rows[1][$division], $rows[2][$division]]); // 申請タイム順
    }

    public function test_meets_page_shows_start_time_fees_and_contact(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('15:30 予定')
            ->assertSee('町田市立陸上競技場')
            ->assertSee('中学生 1,500円')
            ->assertSee('高校生・大学生 2,200円')
            ->assertSee('一般・実業団 3,300円')
            ->assertSee('競技会担当者（お問い合わせ）')
            ->assertSee('kizuna.taikai@gmail.com');
    }
}
