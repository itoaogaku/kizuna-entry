<?php

namespace Tests\Feature;

use App\Mail\KonbiniPendingMail;
use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateways;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SheetEntryTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $card;

    private FakePaymentGateway $konbini;

    private Meet $meet;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        config(['services.stripe.secret' => 'sk_test', 'services.komoju.secret' => 'komoju_test']);
        $this->card = new FakePaymentGateway('cs_test_');
        $this->konbini = new FakePaymentGateway('komoju_');
        $this->app->instance(PaymentGateways::class, new PaymentGateways([
            Entry::METHOD_CARD => fn () => $this->card,
            Entry::METHOD_KONBINI => fn () => $this->konbini,
        ]));

        $this->meet = Meet::factory()->create();
        foreach ([['1500m', 2000], ['5000m', 2200]] as $i => [$name, $fee]) {
            Event::factory()->for($this->meet)->create(['name' => $name, 'gender' => null, 'fee' => $fee, 'sort_order' => $i]);
        }
    }

    /**
     * 今使っているエントリーシート（申込み欄）と同じ配置のファイルを作る。
     */
    private function sheet(array $rows, string $teamName = '絆AC'): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('申込み');
        $sheet->setCellValue('B1', '大会への団体申込み・団体サイト会員登録');
        $sheet->setCellValue('B4', '団体名');
        $sheet->setCellValue('C4', $teamName);
        $sheet->setCellValue('G4', '合計人数');
        $sheet->setCellValue('K4', '合計料金');
        $sheet->fromArray(['', '姓', '名', 'フリガナ', "学年\n(学生の場合)", '性別', '所属陸協', "申込み種目\n（例5000m）",
            "申請タイム\n(例14'30)", "自己ベスト\n(例14'45)", "種別\n（高校生・大学生）", "PRしたい一言\u{3000}※任意\n(実況で使用します)", '料金'], null, 'A7');
        $sheet->fromArray(['例', '鈴木', '太郎', 'ｽｽﾞｷ ﾀﾛｳ', 3, '男', '東京', '5000m', "14'30", "14'45", '高校生・大学生', 'チーム内No.1の練習量', 2200], null, 'A9');
        foreach (array_values($rows) as $i => $row) {
            $sheet->fromArray(array_merge([$i + 1], $row), null, 'A'.(10 + $i));
        }
        $path = tempnam(sys_get_temp_dir(), 'sheet').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'エントリーシート.xlsx', null, null, true);
    }

    private function goodRows(): array
    {
        return [
            ['青山', '駿', 'ｱｵﾔﾏ ｼｭﾝ', 2, '男', '東京', '5000m', "14'30", "14'41", '大学生', '練習量No.1'],
            ['青山', '駿', 'ｱｵﾔﾏ ｼｭﾝ', 2, '男', '東京', '１５００ｍ', '358', '', '大学生', ''],
            ['鈴木', '美咲', 'ｽｽﾞｷ ﾐｻｷ', 2, '女', '埼玉', '5000M', "16'50", '', '高校生', ''],
        ];
    }

    private function upload(array $rows, array $form = []): TestResponse
    {
        return $this->post(route('sheet.upload'), $form + [
            'meet_id' => $this->meet->id,
            'name' => '代表 花子',
            'email' => 'coach@example.com',
            'phone' => '090-0000-0000',
            'agree' => '1',
            'file' => $this->sheet($rows),
        ]);
    }

    public function test_first_screen_explains_two_ways_to_enter(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('エントリー方法は')
            ->assertSee('Webでエントリー')
            ->assertSee('エクセル（エントリーシート）を添付')
            ->assertSee(route('sheet.create'));
    }

    public function test_guest_can_enter_by_attaching_the_entry_sheet_and_it_appears_in_the_admin_roster(): void
    {
        $this->upload($this->goodRows())->assertRedirect(route('sheet.preview'));

        $this->get(route('sheet.preview'))->assertOk()
            ->assertSee('青山 駿')
            ->assertSee('1500m')
            ->assertSee("3'58")
            ->assertSee('6,400円')
            ->assertSee('この内容で支払いへ進む');

        $response = $this->post(route('sheet.store'), ['payment_method' => 'card']);
        $entry = Entry::sole();
        $response->assertRedirect('https://checkout.example.test/'.$entry->payment_session_id);

        $this->assertTrue($entry->isFromSheet());
        $this->assertSame(6400, $entry->total_amount);
        $this->assertSame(User::TYPE_SHEET, $entry->user->type);
        $this->assertSame('絆AC', $entry->user->team_name); // シートの団体名欄から
        $this->assertSame(2, $entry->user->athletes()->count());
        $this->assertSame("14'41", $entry->items->firstWhere('declared_record', "14'30")->personal_best);
        Storage::disk('local')->assertExists($entry->sheet_path);
        $this->assertGuest();

        // 支払い後の戻り先（決済サービスが session_id を付けても署名は有効）
        $this->card->markPaid($entry->payment_session_id);
        $this->get(URL::signedRoute('sheet.status', $entry).'&session_id='.$entry->payment_session_id)
            ->assertOk()->assertSee('エントリーが完了しました');
        $this->assertTrue($entry->fresh()->isPaid());

        // 運営の申込一覧・Excel出力に、Webエントリーと同じように入る
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.roster.index', $this->meet))
            ->assertOk()->assertSee('絆AC')->assertSee('Excel')->assertSeeInOrder(['青山 駿', '鈴木 美咲']);
        $this->actingAs($admin)->get(route('admin.entries.export', $this->meet))->assertOk();
        $this->actingAs($admin)->get(route('admin.entries.sheet', $entry))->assertOk();
    }

    public function test_status_page_requires_the_signed_link(): void
    {
        $this->upload($this->goodRows());
        $this->post(route('sheet.store'), ['payment_method' => 'card']);

        $this->get(route('sheet.status', Entry::sole()))->assertForbidden();
    }

    public function test_rows_with_mistakes_are_shown_and_cannot_be_submitted(): void
    {
        $this->upload([
            ['青山', '駿', 'ｱｵﾔﾏ ｼｭﾝ', 2, '男', '東京', '800m', "1'55", '', '大学生', ''],
            ['鈴木', '美咲', 'ｽｽﾞｷ ﾐｻｷ', 2, '女', '埼玉', '5000m', '', '', '高校生', ''],
        ])->assertRedirect(route('sheet.preview'));

        $this->get(route('sheet.preview'))->assertOk()
            ->assertSee('申込み種目「800m」はこの大会にありません')
            ->assertSee('申請タイムが空欄です')
            ->assertDontSee('この内容で支払いへ進む');

        $this->post(route('sheet.store'), ['payment_method' => 'card'])->assertRedirect(route('sheet.preview'));
        $this->assertDatabaseCount('entries', 0);
    }

    public function test_email_of_a_web_account_must_log_in_first(): void
    {
        User::factory()->create(['email' => 'coach@example.com']);

        $this->upload($this->goodRows())->assertSessionHasErrors('email');
    }

    public function test_logged_in_team_can_also_attach_a_sheet_and_existing_athletes_are_reused(): void
    {
        $team = User::factory()->create(['team_name' => '絆AC']);
        $aoyama = Athlete::factory()->for($team)->create(['last_name' => '青山', 'first_name' => '駿']);

        $this->actingAs($team)->post(route('sheet.upload'), [
            'meet_id' => $this->meet->id,
            'file' => $this->sheet($this->goodRows()),
        ])->assertRedirect(route('sheet.preview'));
        $this->actingAs($team)->post(route('sheet.store'), ['payment_method' => 'card']);

        $entry = Entry::sole();
        $this->assertSame($team->id, $entry->user_id);
        $this->assertSame(2, $team->athletes()->count());
        $this->assertSame($aoyama->id, $entry->items->first()->athlete_id);
    }

    public function test_konbini_payment_sends_a_same_day_request_once(): void
    {
        $this->upload($this->goodRows());
        $this->post(route('sheet.store'), ['payment_method' => 'konbini']);
        $entry = Entry::sole();

        $this->konbini->markAwaiting($entry->payment_session_id, 'https://komoju.test/slip');
        $url = URL::signedRoute('sheet.status', $entry).'&session_id='.$entry->payment_session_id;
        $this->get($url)->assertOk()->assertSee('本日中')->assertSee('https://komoju.test/slip');
        $this->get($url)->assertOk();

        Mail::assertSent(KonbiniPendingMail::class, 1);
        Mail::assertSent(KonbiniPendingMail::class, fn ($mail) => $mail->hasTo('coach@example.com'));
    }
}
