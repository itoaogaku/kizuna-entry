<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 12:00');
    }

    public function test_meets_page_lists_this_fiscal_years_meets_with_events_and_links(): void
    {
        $past = Meet::factory()->create([
            'name' => '第20回絆記録挑戦会', 'held_on' => '2026-09-26', 'eligible' => '中学生・高校生・大学生・一般',
            'entry_opens_at' => '2026-07-01', 'entry_closes_at' => '2026-09-10',
            'result_url' => 'https://example.com/result.pdf', 'photo_url' => 'https://example.com/photo',
            'program_url' => 'https://example.com/program.pdf', 'program_note' => '0925更新',
        ]);
        Event::factory()->for($past)->create(['name' => '10000m', 'gender' => 'M']);
        Event::factory()->for($past)->create(['name' => '3000m', 'gender' => null]);
        $open = Meet::factory()->create([
            'name' => '第21回絆記録挑戦会', 'held_on' => '2026-11-15',
            'entry_opens_at' => '2026-10-01', 'entry_closes_at' => '2026-10-31',
        ]);
        Meet::factory()->create(['name' => '非公開の大会', 'held_on' => '2026-12-01', 'is_published' => false]);
        Meet::factory()->create(['name' => '前年度の大会', 'held_on' => '2026-03-20']);

        $this->get('/')->assertOk()
            ->assertSee('大会開催')
            ->assertSee('2026年度 大会開催予定')
            ->assertSeeInOrder(['第20回絆記録挑戦会', '第21回絆記録挑戦会']) // 開催予定は日付順
            ->assertSee('公認の部・男子')
            ->assertSee('10000m')
            ->assertSee('大会終了')
            ->assertSee('エントリー受付中')
            ->assertSee('https://example.com/result.pdf')
            ->assertSee('0925更新')
            ->assertSee('写真館')
            ->assertSee('NEXT RACE')
            ->assertSee(route('home'))
            ->assertSee('過去の大会結果')
            ->assertSee(route('site.meets', ['year' => 2021]).'#schedule', false)
            ->assertSee('info@acckizuna.com')
            ->assertSee('042-722-2930')
            ->assertDontSee('非公開の大会')
            ->assertDontSee('前年度の大会');

        $this->get('/?year=2025')->assertOk()->assertSee('2025年度 大会開催予定')->assertSee('前年度の大会');
        $this->assertSame('open', $open->stage());
    }

    public function test_admin_can_attach_pdfs_for_timetable_startlist_program_and_results(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $meet = Meet::factory()->create(['held_on' => '2026-11-15']);
        Event::factory()->for($meet)->create(['name' => '5000m', 'gender' => null]);

        $this->actingAs($admin)->get(route('admin.documents.edit', $meet))->assertOk()
            ->assertSee('エントリーデータをExcelで出力')->assertSee('組編成（スタートリスト）');

        $this->actingAs($admin)->post(route('admin.documents.update', $meet), [
            'timetable_file' => UploadedFile::fake()->create('timetable.pdf', 100, 'application/pdf'),
            'startlist_file' => UploadedFile::fake()->create('startlist.pdf', 100, 'application/pdf'),
            'startlist_note' => '暫定版',
            'result_file' => UploadedFile::fake()->create('photo-finish.pdf', 3000, 'application/pdf'),
            'photo_url' => 'https://example.com/photo',
        ])->assertRedirect(route('admin.documents.edit', $meet));

        $meet->refresh();
        foreach (['timetable', 'startlist', 'result'] as $key) {
            Storage::disk('public')->assertExists($meet->{$key.'_path'});
        }
        $this->get('/')
            ->assertSee($meet->documentUrl('timetable'))
            ->assertSee('スタートリスト')
            ->assertSee('暫定版')
            ->assertSee($meet->documentUrl('result'))
            ->assertSee('https://example.com/photo');

        // 差し替え・削除
        $old = $meet->startlist_path;
        $this->actingAs($admin)->post(route('admin.documents.update', $meet), [
            'startlist_file' => UploadedFile::fake()->create('startlist2.pdf', 100, 'application/pdf'),
            'startlist_note' => '1110更新',
            'timetable_remove' => '1',
        ]);
        $meet->refresh();
        Storage::disk('public')->assertMissing($old);
        $this->assertNull($meet->timetable_path);
        $this->assertSame('1110更新', $meet->startlist_note);

        // PDF以外は貼れない
        $this->actingAs($admin)->post(route('admin.documents.update', $meet), [
            'program_file' => UploadedFile::fake()->create('program.xlsx', 10),
        ])->assertSessionHasErrors('program_file');
    }

    public function test_legal_notice_page_lists_seller_and_refund_policy(): void
    {
        $this->get(route('site.legal'))->assertOk()
            ->assertSee('特定商取引法に基づく表記')
            ->assertSee('株式会社アスリートキャリアセンター')
            ->assertSee('返金はお受けしていません');
        $this->get(route('site.meets'))->assertSee(route('site.legal'));
    }

    public function test_site_is_marked_as_separate_entry_site_with_its_own_login(): void
    {
        $this->get(route('site.meets'))->assertOk()
            ->assertSee('大会エントリー専用サイト')
            ->assertSee('エントリー用ログイン')
            ->assertSee('公式サイトへ戻る');
        $this->get(route('team.login'))->assertOk()->assertSee('このサイトのアカウントは、大会エントリー専用です。');
        $this->get(route('individual.register'))->assertOk()->assertSee('このサイトのアカウントは、大会エントリー専用です。');
    }
}
