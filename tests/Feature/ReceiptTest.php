<?php

namespace Tests\Feature;

use App\Mail\EntryPaidMail;
use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateways;
use App\Services\EntryService;
use App\Services\ReceiptPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $team;

    private Meet $meet;

    private Event $five;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.stripe.secret' => 'sk_test']);
        $this->app->instance(PaymentGateways::class, new PaymentGateways([
            Entry::METHOD_CARD => fn () => new FakePaymentGateway('cs_'),
            Entry::METHOD_KONBINI => fn () => new FakePaymentGateway('komoju_'),
        ]));
        $this->team = User::factory()->create(['team_name' => '絆AC']);
        $this->meet = Meet::factory()->create(['name' => '第21回絆記録挑戦会', 'fee_student' => 2200, 'fee_general' => 3300]);
        $this->five = Event::factory()->for($this->meet)->create(['name' => '5000m', 'gender' => null, 'fee' => 0]);
    }

    private function enterAndPay(array $form = []): Entry
    {
        $athlete = Athlete::factory()->for($this->team)->create(['last_name' => '青山', 'first_name' => '駿', 'category' => '大学生']);
        $this->actingAs($this->team)->post(route('entries.review', $this->meet), ['items' => [
            $athlete->id => ['selected' => '1', 'events' => [['event_id' => $this->five->id, 'record' => '1430']]],
        ]]);
        $this->actingAs($this->team)->get(route('entries.confirm', $this->meet))->assertSee('領収書の宛名')->assertSee('value="絆AC"', false);
        $this->actingAs($this->team)->post(route('entries.store', $this->meet), $form + ['payment_method' => 'card', 'terms' => '1']);
        $entry = Entry::sole();
        app(EntryService::class)->markPaid($entry, 'pi_1');

        return $entry->fresh();
    }

    public function test_receipt_is_issued_and_attached_when_payment_completes(): void
    {
        $entry = $this->enterAndPay(['receipt_name' => '学校法人 絆学園']);

        $this->assertSame(now()->format('Y').'-'.str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT), $entry->receipt_number);
        $this->assertSame('学校法人 絆学園', $entry->receiptAddressee());

        Mail::assertSent(EntryPaidMail::class, function (EntryPaidMail $mail) use ($entry) {
            $attachments = $mail->attachments();

            return count($attachments) === 1 && $attachments[0]->as === $entry->receiptFilename();
        });

        $pdf = app(ReceiptPdf::class)->render($entry);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(5000, strlen($pdf)); // 日本語フォントを埋め込んでいる
    }

    public function test_receipt_can_be_downloaded_by_the_owner_and_admin_only(): void
    {
        $entry = $this->enterAndPay();
        $this->assertSame('絆AC', $entry->receiptAddressee()); // 宛名を空にしたら団体名

        $this->actingAs($this->team)->get(route('entries.show', $entry))->assertSee('領収書（PDF）を表示');
        $this->actingAs($this->team)->get(route('entries.receipt', $entry))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs(User::factory()->create())->get(route('entries.receipt', $entry))->assertNotFound();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.entries.receipt', $entry))->assertOk();
        $this->actingAs($admin)->put(route('admin.entries.receipt.update', $entry), ['receipt_name' => '絆陸上クラブ 御中'])->assertRedirect();
        $this->assertSame('絆陸上クラブ 御中', $entry->fresh()->receiptAddressee());
        $this->assertSame($entry->receipt_number, $entry->fresh()->receipt_number); // 番号は変えない
    }

    public function test_no_receipt_before_payment(): void
    {
        $athlete = Athlete::factory()->for($this->team)->create();
        $entry = $this->team->entries()->create(['meet_id' => $this->meet->id, 'status' => Entry::STATUS_PENDING, 'total_amount' => 2200]);
        $entry->items()->create(['athlete_id' => $athlete->id, 'event_id' => $this->five->id, 'fee' => 2200, 'declared_record' => "14'30"]);

        $this->actingAs($this->team)->get(route('entries.receipt', $entry))->assertNotFound();
    }
}
