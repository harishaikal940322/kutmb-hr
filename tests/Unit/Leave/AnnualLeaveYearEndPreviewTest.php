<?php

namespace Tests\Unit\Leave;

use App\Services\Leave\AnnualLeaveYearEndPreview;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class AnnualLeaveYearEndPreviewTest extends TestCase
{
    public function test_close_preview_subtracts_pending_holds_and_caps_transfer_without_deciding_excess_disposition(): void
    {
        $preview = app(AnnualLeaveYearEndPreview::class)->previewCloseTransfer(
            employeeId: 7001,
            leaveYear: 2026,
            postedAnnualHalfDayUnits: 20,
            pendingAnnualHoldHalfDayUnits: 4,
            tabungPostedHalfDayUnits: 40,
            confirmedAnnualCapHalfDayUnits: 50,
        );

        $this->assertSame('annual-close:7001:2026', $preview['operation_key']);
        $this->assertSame(16, $preview['free_annual_half_day_units']);
        $this->assertSame(10, $preview['tabung_capacity_half_day_units']);
        $this->assertSame(10, $preview['transfer_half_day_units']);
        $this->assertSame(6, $preview['untransferred_half_day_units']);
        $this->assertTrue($preview['requires_hr_disposition']);
    }

    public function test_close_preview_never_carries_negative_or_fully_held_annual_balance(): void
    {
        $service = app(AnnualLeaveYearEndPreview::class);

        $negative = $service->previewCloseTransfer(7002, 2026, -2, 1, 3, 10);
        $fullyHeld = $service->previewCloseTransfer(7003, 2026, 4, 4, 1, 10);

        $this->assertSame(0, $negative['eligible_carry_half_day_units']);
        $this->assertSame(0, $negative['transfer_half_day_units']);
        $this->assertSame(0, $fullyHeld['eligible_carry_half_day_units']);
        $this->assertSame(0, $fullyHeld['transfer_half_day_units']);
        $this->assertFalse($fullyHeld['requires_hr_disposition']);
    }

    public function test_late_reconciliation_is_source_keyed_and_respects_current_capacity(): void
    {
        $service = app(AnnualLeaveYearEndPreview::class);
        $input = [
            'employeeId' => 7004,
            'sourceYear' => 2025,
            'eligibleUntransferredHalfDayUnits' => 8,
            'tabungPostedHalfDayUnits' => 48,
            'confirmedAnnualCapHalfDayUnits' => 50,
            'sourceReference' => 'SYN-RETURN-42',
        ];

        $first = $service->previewLateReconciliation(...$input);
        $replay = $service->previewLateReconciliation(...$input);

        $this->assertSame('annual-reconcile:7004:2025:SYN-RETURN-42', $first['operation_key']);
        $this->assertSame($first, $replay);
        $this->assertSame(2, $first['additional_transfer_half_day_units']);
        $this->assertSame(6, $first['untransferred_half_day_units']);
        $this->assertTrue($first['requires_hr_disposition']);
    }

    public function test_year_end_preview_rejects_implicit_or_inconsistent_inputs(): void
    {
        $service = app(AnnualLeaveYearEndPreview::class);

        $this->expectException(InvalidArgumentException::class);
        $service->previewLateReconciliation(7005, 2025, 4, 0, 10, 'contains spaces');
    }

    public function test_year_end_previews_are_unavailable_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        try {
            app(AnnualLeaveYearEndPreview::class)->previewCloseTransfer(7006, 2026, 4, 0, 0, 10);
            $this->fail('Annual year-end previews must remain unavailable outside local development and testing.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
