<?php

namespace Tests\Feature;

use App\Mail\ObligationDueReminder;
use App\Mail\OverdueDigest;
use App\Mail\SignatureReminder;
use App\Models\Lab;
use App\Models\LabUser;
use App\Models\Obligation;
use App\Models\User;
use App\Services\ReminderService;
use App\Services\SignatureService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    private Lab $lab;
    private ReminderService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lab = $this->makeLab();
        $this->svc = app(ReminderService::class);

        foreach ([
            'lab.director@example.com' => 'lab_director',
            'tech.supervisor@example.com' => 'tech_supervisor',
            'general.supervisor@example.com' => 'general_supervisor',
            'compliance@example.com' => 'compliance_specialist',
            'safety.officer@example.com' => 'safety_officer',
        ] as $email => $role) {
            $user = User::factory()->create(['email' => $email]);
            LabUser::create(['lab_id' => $this->lab->id, 'user_id' => $user->id, 'active' => true])->syncRoles([$role]);
        }

        // C06 (interval 12) completed 2026-01-01 -> next_due 2027-01-01, the anchor for date math.
        Obligation::where('code', 'C06')->update(['last_completed' => '2026-01-01']);
    }

    public function test_due_30_goes_to_owner_only(): void
    {
        Mail::fake();
        $this->svc->sendDueReminders(CarbonImmutable::parse('2026-12-02')); // 30 days out

        Mail::assertSent(ObligationDueReminder::class, fn ($m) => $m->type === 'due_30' && $m->hasTo('lab.director@example.com'));
        Mail::assertSent(ObligationDueReminder::class, 1);
    }

    public function test_due_7_adds_lab_director(): void
    {
        Mail::fake();
        $this->svc->sendDueReminders(CarbonImmutable::parse('2026-12-25')); // 7 days out

        Mail::assertSent(ObligationDueReminder::class, fn ($m) => $m->type === 'due_7');
    }

    public function test_overdue_escalates_to_compliance(): void
    {
        Mail::fake();
        $this->svc->sendDueReminders(CarbonImmutable::parse('2027-01-02')); // 1 day overdue

        Mail::assertSent(ObligationDueReminder::class, fn ($m) => $m->type === 'overdue_1' && $m->hasTo('compliance@example.com'));
    }

    public function test_on_track_sends_nothing(): void
    {
        Mail::fake();
        $this->svc->sendDueReminders(CarbonImmutable::parse('2026-11-01')); // ~61 days out

        Mail::assertNothingSent();
    }

    public function test_reminders_are_idempotent(): void
    {
        Mail::fake();
        $today = CarbonImmutable::parse('2026-12-02');

        $this->svc->sendDueReminders($today);
        $this->svc->sendDueReminders($today);

        Mail::assertSent(ObligationDueReminder::class, 1);
    }

    public function test_signature_reminder_at_5_days(): void
    {
        $req = app(SignatureService::class)->sendForSignature(Obligation::where('code', 'C02')->first());
        $req->update(['sent_date' => CarbonImmutable::parse('2026-06-20')->subDays(5)->toDateString()]);

        Mail::fake();
        $this->svc->sendSignatureReminders(CarbonImmutable::parse('2026-06-20'));

        Mail::assertSent(SignatureReminder::class, fn ($m) => $m->escalation === false && $m->daysPending === 5);
    }

    public function test_signature_escalation_at_10_days(): void
    {
        $req = app(SignatureService::class)->sendForSignature(Obligation::where('code', 'C02')->first());
        $req->update(['sent_date' => CarbonImmutable::parse('2026-06-20')->subDays(10)->toDateString()]);

        Mail::fake();
        $this->svc->sendSignatureReminders(CarbonImmutable::parse('2026-06-20'));

        Mail::assertSent(SignatureReminder::class, fn ($m) => $m->escalation === true && $m->hasTo('compliance@example.com'));
    }

    public function test_weekly_digest_lists_overdue(): void
    {
        // C11 is annual (12 mo), so use an older date to keep it overdue; C12 is monthly.
        Obligation::where('code', 'C11')->update(['last_completed' => '2024-01-01']);
        Obligation::where('code', 'C12')->update(['last_completed' => '2026-01-01']);

        Mail::fake();
        $count = $this->svc->sendOverdueDigest(CarbonImmutable::parse('2026-06-20'));

        $this->assertGreaterThanOrEqual(2, $count);
        Mail::assertSent(OverdueDigest::class, fn ($m) => $m->hasTo('compliance@example.com'));
    }
}
