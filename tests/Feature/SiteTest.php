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
            ->assertDontSee('非公開の大会')
            ->assertDontSee('前年度の大会');

        $this->get('/?year=2025')->assertOk()->assertSee('2025年度 大会開催予定')->assertSee('前年度の大会');
        $this->assertSame('open', $open->stage());
    }

    public function test_admin_can_upload_pdfs_and_links_for_the_meets_page(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $meet = Meet::factory()->create(['held_on' => '2026-11-15']);
        Event::factory()->for($meet)->create(['name' => '5000m', 'gender' => null]);

        $this->actingAs($admin)->put(route('admin.meets.update', $meet), [
            'name' => $meet->name, 'held_on' => '2026-11-15',
            'entry_opens_at' => '2026-10-01T00:00', 'entry_closes_at' => '2026-10-31T23:59',
            'max_events_per_athlete' => 2, 'is_published' => '1',
            'division' => '公認の部', 'eligible' => '高校生・大学生・一般',
            'outline_file' => UploadedFile::fake()->create('要項.pdf', 100, 'application/pdf'),
            'photo_url' => 'https://example.com/photo',
        ])->assertRedirect();

        $meet->refresh();
        Storage::disk('public')->assertExists($meet->outline_path);
        $this->get('/')->assertSee($meet->documentUrl('outline'))->assertSee('高校生・大学生・一般');

        // PDFを削除すると、ファイルも消える
        $path = $meet->outline_path;
        $this->actingAs($admin)->put(route('admin.meets.update', $meet), [
            'name' => $meet->name, 'held_on' => '2026-11-15',
            'entry_opens_at' => '2026-10-01T00:00', 'entry_closes_at' => '2026-10-31T23:59',
            'max_events_per_athlete' => 2, 'division' => '公認の部', 'outline_remove' => '1',
        ])->assertRedirect();
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($meet->fresh()->outline_path);
    }
}
