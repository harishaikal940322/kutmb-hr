<?php

namespace Database\Seeders;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Leave\AnnualEntitlementCreditService;
use App\Services\Leave\ConfirmedVersionLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DevelopmentLeaveConfigurationSeeder extends Seeder
{
    /** Explicit demo values in half-day units, not HR entitlement rates. */
    private const FIXTURES = [
        'DEV-001' => ['dev-staff', 40],
        'DEV-002' => ['dev-unit-manager', 40],
        'DEV-003' => ['dev-general-manager', 40],
        'DEV-004' => ['dev-hr-executive', 12],
        'DEV-005' => ['dev-hr-manager', 40],
        'DEV-006' => ['dev-it-admin', 40],
    ];

    public function run(): void
    {
        if (! app()->environment('local') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'kutmb_hr_dev') {
            throw new RuntimeException('Leave configuration seeding requires local kutmb_hr_dev.');
        }

        $summary = DB::transaction(function (): array {
            foreach (['leave_calendar_confirmation_guard', 'leave_working_schedule_guard', 'office_assignment_guard', 'iam_guard'] as $guard) {
                if (DB::table($guard)->where('id', 1)->lockForUpdate()->first() === null) {
                    throw new RuntimeException('Missing required baseline: '.$guard);
                }
            }
            $year = (int) now('Asia/Kuala_Lumpur')->format('Y');
            $from = sprintf('%04d-01-01', $year);
            $to = sprintf('%04d-12-31', $year);
            $actor = $this->fixtureUser('dev-hr-manager');
            if (! $actor->hasRole('hr-manager') || ! $actor->can('leave.calendar.manage')
                || ! $actor->can('leave.policy.manage') || ! $actor->can('leave.entitlement.confirm')
                || ! $actor->can('leave.entitlement.credit')) {
                throw new RuntimeException('The HR Manager fixture requires existing calendar, policy, confirmation and credit permissions.');
            }

            $employees = [];
            foreach (self::FIXTURES as $number => [$username, $units]) {
                $user = $this->fixtureUser($username);
                $employee = DB::table('employees')->where('employee_no', $number)->lockForUpdate()->first();
                if ($employee === null || (int) $employee->user_id !== (int) $user->id
                    || ! AuditEvent::query()->where('action_code', 'hr.employee.development_seeded')
                        ->where('target_ref', 'employee:'.$employee->id)->exists()) {
                    throw new RuntimeException('Missing or conflicting synthetic employee mapping: '.$number);
                }
                if ($employee->service_start > $to || ($employee->service_end !== null && $employee->service_end < $from)) {
                    throw new RuntimeException('Employee fixture is outside the current leave year: '.$number);
                }
                $employees[] = [$employee, $units];
            }

            $lookup = app(ConfirmedVersionLookup::class);
            $this->configureCalendar($actor, $year, $from, $to);
            $this->configureSchedule($actor, $year, $from, $to);
            // Validate each date through the same lookup used by real submissions.
            $day = \Carbon\CarbonImmutable::parse($from, 'Asia/Kuala_Lumpur');
            while ($day->toDateString() <= $to) {
                $lookup->calendarFor($day->toDateString());
                $lookup->workingScheduleFor($day->toDateString());
                $day = $day->addDay();
            }

            $service = app(AnnualEntitlementCreditService::class);
            $credited = 0;
            $preserved = 0;
            foreach ($employees as [$employee, $units]) {
                $annual = DB::table('leave_annual_accounts')->where('employee_id', $employee->id)->where('leave_year', $year)->lockForUpdate()->first();
                $tabung = DB::table('leave_tabung_accounts')->where('employee_id', $employee->id)->lockForUpdate()->first();

                if ($annual !== null && (
                    DB::table('leave_annual_entitlement_confirmations')->where('annual_account_id', $annual->id)->exists()
                    || DB::table('leave_ledger_entries')->where('annual_account_id', $annual->id)->exists()
                    || DB::table('leave_reservations')->where('annual_account_id', $annual->id)->exists()
                )) {
                    // Preserve confirmed, credited or otherwise used accounts. Never top up.
                    $preserved++;
                    continue;
                }
                $annualId = $annual?->id ?? DB::table('leave_annual_accounts')->insertGetId([
                    'employee_id' => $employee->id, 'leave_year' => $year, 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($tabung === null) {
                    DB::table('leave_tabung_accounts')->insert([
                        'employee_id' => $employee->id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $confirmation = $service->confirmAnnualEntitlement(
                    $actor->id, $annualId, $units, 'DEV-FIXTURE-OPENING-'.$year,
                    'dev-fixture-entitlement:'.$annualId.':'.$year,
                );
                $service->creditDevelopmentFixture($actor->id, $confirmation['confirmation_id']);
                $credited++;
            }

            return [$year, $credited, $preserved];
        }, 3);

        [$year, $credited, $preserved] = $summary;
        $this->command?->info("Development leave setup {$year}: {$credited} new opening credits, {$preserved} existing annual accounts preserved.");
    }

    private function fixtureUser(string $username): User
    {
        $user = User::query()->where('username_canonical', $username)->lockForUpdate()->first();
        if ($user === null || $user->status !== 'ACTIVE'
            || ! AuditEvent::query()->where('action_code', 'iam.development_user.seeded')->where('target_ref', 'user:'.$user->id)->exists()) {
            throw new RuntimeException('Missing active recorded development account: '.$username);
        }

        return $user;
    }

    private function configureCalendar(User $actor, int $year, string $from, string $to): void
    {
        $table = 'leave_calendar_versions';
        if ($this->hasOverlappingVersion($table, $from, $to)) {
            // Existing versions are never modified; lookup validation below will
            // reject incomplete or conflicting coverage rather than replace it.
            return;
        }
        $this->assertNoSnapshots($from, $to);
        $id = DB::table($table)->insertGetId([
            'label' => 'DEV DEMO '.$year.' - synthetic calendar (not official Johor holidays)',
            'effective_from' => $from, 'effective_to' => $to,
            'confirmed_by' => $actor->id, 'confirmed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['01-01' => 'Demo New Year', '08-31' => 'Demo National Day', '12-25' => 'Demo Christmas'] as $suffix => $label) {
            DB::table('leave_calendar_days')->insert([
                'calendar_version_id' => $id, 'date' => $year.'-'.$suffix,
                'name' => $label, 'kind' => 'holiday', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->audit('leave.calendar.development_seeded', 'calendar_version:'.$id, ['calendar_version']);
    }

    private function configureSchedule(User $actor, int $year, string $from, string $to): void
    {
        $table = 'leave_policy_versions';
        if ($this->hasOverlappingVersion($table, $from, $to)) {
            return;
        }
        $this->assertNoSnapshots($from, $to);
        $id = DB::table($table)->insertGetId([
            'label' => 'DEV DEMO '.$year.' - synthetic working schedule',
            'effective_from' => $from, 'effective_to' => $to,
            'working_weekdays' => json_encode([1, 2, 3, 4, 5], JSON_THROW_ON_ERROR),
            'work_start' => '08:00:00', 'session_boundary' => '13:00:00', 'work_end' => '17:00:00',
            'schedule_simulated_by' => $actor->id, 'schedule_simulated_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit('leave.policy.development_seeded', 'policy_version:'.$id, ['working_schedule']);
    }

    private function hasOverlappingVersion(string $table, string $from, string $to): bool
    {
        return DB::table($table)->where('effective_from', '<=', $to)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
            ->lockForUpdate()->get(['id'])->isNotEmpty();
    }

    private function assertNoSnapshots(string $from, string $to): void
    {
        if (DB::table('leave_calendar_usage_snapshots')->whereBetween('leave_date', [$from, $to])->exists()) {
            throw new RuntimeException('Existing leave calendar usage prevents automatic fixture configuration.');
        }
    }

    private function audit(string $action, string $target, array $fields): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(), 'actor_id' => null, 'action_code' => $action,
            'target_ref' => $target, 'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'development_seeder', 'changed_fields' => $fields],
        ]);
    }
}
