<?php

namespace HughCube\Laravel\Knight\Tests\Traits;

use HughCube\Laravel\Knight\Contracts\Support\HasTimeRange;
use HughCube\Laravel\Knight\Tests\TestCase;
use HughCube\Laravel\Knight\Traits\HasTimeRangeTrait;
use Illuminate\Support\Carbon;

class HasTimeRangeTraitTest extends TestCase
{
    // ==================== isStarted Tests ====================

    public function testIsStartedReturnsTrueWhenStartedAtIsInThePast()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->subHour(), null);
        $this->assertTrue($obj->isStarted());
    }

    public function testIsStartedReturnsTrueWhenStartedAtIsNow()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));
        $obj = new TestTimeRangeObject(Carbon::now(), null);
        $this->assertTrue($obj->isStarted());
        Carbon::setTestNow();
    }

    public function testIsStartedReturnsFalseWhenStartedAtIsInTheFuture()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->addHour(), null);
        $this->assertFalse($obj->isStarted());
    }

    public function testIsStartedReturnsTrueWhenStartedAtIsNull()
    {
        $obj = new TestTimeRangeObject(null, null);
        $this->assertTrue($obj->isStarted());
    }

    public function testIsStartedReturnsTrueWhenStartedAtIsYesterday()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->subDay(), null);
        $this->assertTrue($obj->isStarted());
    }

    public function testIsStartedReturnsFalseWhenStartedAtIsTomorrow()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->addDay(), null);
        $this->assertFalse($obj->isStarted());
    }

    public function testIsStartedReturnsTrueWhenStartedAtIsOneSecondAgo()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->subSecond(), null);
        $this->assertTrue($obj->isStarted());
    }

    public function testIsStartedReturnsFalseWhenStartedAtIsOneSecondLater()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->addSecond(), null);
        $this->assertFalse($obj->isStarted());
    }

    // ==================== isEnded Tests ====================

    public function testIsEndedReturnsTrueWhenEndedAtIsInThePast()
    {
        $obj = new TestTimeRangeObject(null, Carbon::now()->subHour());
        $this->assertTrue($obj->isEnded());
    }

    public function testIsEndedReturnsTrueWhenEndedAtIsNow()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));
        $obj = new TestTimeRangeObject(null, Carbon::now());
        $this->assertTrue($obj->isEnded());
        Carbon::setTestNow();
    }

    public function testIsEndedReturnsFalseWhenEndedAtIsInTheFuture()
    {
        $obj = new TestTimeRangeObject(null, Carbon::now()->addHour());
        $this->assertFalse($obj->isEnded());
    }

    public function testIsEndedReturnsFalseWhenEndedAtIsNull()
    {
        $obj = new TestTimeRangeObject(null, null);
        $this->assertFalse($obj->isEnded());
    }

    public function testIsEndedReturnsTrueWhenEndedAtIsYesterday()
    {
        $obj = new TestTimeRangeObject(null, Carbon::now()->subDay());
        $this->assertTrue($obj->isEnded());
    }

    public function testIsEndedReturnsFalseWhenEndedAtIsTomorrow()
    {
        $obj = new TestTimeRangeObject(null, Carbon::now()->addDay());
        $this->assertFalse($obj->isEnded());
    }

    // ==================== isInProgress Tests ====================

    public function testIsInProgressReturnsTrueWhenStartedAndNotEnded()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->subHour(), Carbon::now()->addHour());
        $this->assertTrue($obj->isInProgress());
    }

    public function testIsInProgressReturnsFalseWhenNotStarted()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->addHour(), Carbon::now()->addHours(2));
        $this->assertFalse($obj->isInProgress());
    }

    public function testIsInProgressReturnsFalseWhenAlreadyEnded()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->subHours(2), Carbon::now()->subHour());
        $this->assertFalse($obj->isInProgress());
    }

    public function testIsInProgressReturnsTrueWhenBothNull()
    {
        $obj = new TestTimeRangeObject(null, null);
        $this->assertTrue($obj->isInProgress());
    }

    public function testIsInProgressReturnsTrueWhenStartedAndEndedAtIsNull()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->subHour(), null);
        $this->assertTrue($obj->isInProgress());
    }

    public function testIsInProgressReturnsFalseWhenStartedAtIsNullAndEndedAtIsInThePast()
    {
        $obj = new TestTimeRangeObject(null, Carbon::now()->subHour());
        $this->assertFalse($obj->isInProgress());
    }

    public function testIsInProgressReturnsTrueWhenStartedAtIsNullAndEndedAtIsInTheFuture()
    {
        $obj = new TestTimeRangeObject(null, Carbon::now()->addHour());
        $this->assertTrue($obj->isInProgress());
    }

    public function testIsInProgressReturnsFalseWhenStartedAtEqualsEndedAtInThePast()
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 1, 12, 0, 0));
        $time = Carbon::create(2025, 1, 1, 12, 0, 0);
        $obj = new TestTimeRangeObject($time, $time->copy());
        $this->assertFalse($obj->isInProgress());
        Carbon::setTestNow();
    }

    // ==================== Combination Tests ====================

    public function testFullLifecycleBeforeStart()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 10, 0, 0));
        $obj = new TestTimeRangeObject(
            Carbon::create(2025, 1, 1, 12, 0, 0),
            Carbon::create(2025, 1, 1, 18, 0, 0)
        );

        $this->assertFalse($obj->isStarted());
        $this->assertFalse($obj->isEnded());
        $this->assertFalse($obj->isInProgress());
        Carbon::setTestNow();
    }

    public function testFullLifecycleDuringProgress()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 15, 0, 0));
        $obj = new TestTimeRangeObject(
            Carbon::create(2025, 1, 1, 12, 0, 0),
            Carbon::create(2025, 1, 1, 18, 0, 0)
        );

        $this->assertTrue($obj->isStarted());
        $this->assertFalse($obj->isEnded());
        $this->assertTrue($obj->isInProgress());
        Carbon::setTestNow();
    }

    public function testFullLifecycleAfterEnd()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 20, 0, 0));
        $obj = new TestTimeRangeObject(
            Carbon::create(2025, 1, 1, 12, 0, 0),
            Carbon::create(2025, 1, 1, 18, 0, 0)
        );

        $this->assertTrue($obj->isStarted());
        $this->assertTrue($obj->isEnded());
        $this->assertFalse($obj->isInProgress());
        Carbon::setTestNow();
    }

    public function testFullLifecycleAtStartBoundary()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));
        $obj = new TestTimeRangeObject(
            Carbon::create(2025, 1, 1, 12, 0, 0),
            Carbon::create(2025, 1, 1, 18, 0, 0)
        );

        $this->assertTrue($obj->isStarted());
        $this->assertFalse($obj->isEnded());
        $this->assertTrue($obj->isInProgress());
        Carbon::setTestNow();
    }

    public function testFullLifecycleAtEndBoundary()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 18, 0, 0));
        $obj = new TestTimeRangeObject(
            Carbon::create(2025, 1, 1, 12, 0, 0),
            Carbon::create(2025, 1, 1, 18, 0, 0)
        );

        $this->assertTrue($obj->isStarted());
        $this->assertTrue($obj->isEnded());
        $this->assertFalse($obj->isInProgress());
        Carbon::setTestNow();
    }

    // ==================== Open-ended Range Tests ====================

    public function testOpenEndedStartedInThePast()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->subDay(), null);

        $this->assertTrue($obj->isStarted());
        $this->assertFalse($obj->isEnded());
        $this->assertTrue($obj->isInProgress());
    }

    public function testOpenEndedNotYetStarted()
    {
        $obj = new TestTimeRangeObject(Carbon::now()->addDay(), null);

        $this->assertFalse($obj->isStarted());
        $this->assertFalse($obj->isEnded());
        $this->assertFalse($obj->isInProgress());
    }

    // ==================== Remaining Seconds Tests ====================

    public function testGetStartRemainingReturnsZeroWhenAlreadyStarted()
    {
        $now = Carbon::create(2025, 6, 1, 10, 0, 0);
        $obj = new TestTimeRangeObject($now->copy()->subMinute(), null);

        $this->assertSame(0, $obj->getStartRemaining($now));
    }

    public function testGetStartRemainingReturnsSecondsUntilStart()
    {
        $now = Carbon::create(2025, 6, 1, 10, 0, 0);
        $obj = new TestTimeRangeObject($now->copy()->addSeconds(30), null);

        $this->assertSame(30, $obj->getStartRemaining($now));
    }

    public function testGetEndRemainingReturnsZeroWhenAlreadyEnded()
    {
        $now = Carbon::create(2025, 6, 1, 10, 0, 0);
        $obj = new TestTimeRangeObject(null, $now->copy()->subSecond());

        $this->assertSame(0, $obj->getEndRemaining($now));
    }

    public function testGetEndRemainingReturnsSecondsUntilEnd()
    {
        $now = Carbon::create(2025, 6, 1, 10, 0, 0);
        $obj = new TestTimeRangeObject(null, $now->copy()->addSeconds(45));

        $this->assertSame(45, $obj->getEndRemaining($now));
    }

    // ==================== Interface Implementation Tests ====================

    public function testImplementsHasTimeRangeInterface()
    {
        $obj = new TestTimeRangeObject(null, null);
        $this->assertInstanceOf(HasTimeRange::class, $obj);
    }

    public function testGetStartedAtReturnsCorrectValue()
    {
        $time = Carbon::create(2025, 6, 15, 10, 30, 0);
        $obj = new TestTimeRangeObject($time, null);
        $this->assertSame($time, $obj->getStartedAt());
    }

    public function testGetEndedAtReturnsCorrectValue()
    {
        $time = Carbon::create(2025, 6, 15, 18, 30, 0);
        $obj = new TestTimeRangeObject(null, $time);
        $this->assertSame($time, $obj->getEndedAt());
    }

    public function testGetStartedAtReturnsNullWhenNotSet()
    {
        $obj = new TestTimeRangeObject(null, null);
        $this->assertNull($obj->getStartedAt());
    }

    public function testGetEndedAtReturnsNullWhenNotSet()
    {
        $obj = new TestTimeRangeObject(null, null);
        $this->assertNull($obj->getEndedAt());
    }
}

/**
 * 测试用的时间范围对象（非 Model，验证 trait 的通用性）.
 */
class TestTimeRangeObject implements HasTimeRange
{
    use HasTimeRangeTrait;

    /**
     * @var Carbon|null
     */
    private $startedAt;

    /**
     * @var Carbon|null
     */
    private $endedAt;

    /**
     * @param  Carbon|null  $startedAt
     * @param  Carbon|null  $endedAt
     */
    public function __construct($startedAt, $endedAt)
    {
        $this->startedAt = $startedAt;
        $this->endedAt = $endedAt;
    }

    /**
     * @return Carbon|null
     */
    public function getStartedAt()
    {
        return $this->startedAt;
    }

    /**
     * @return Carbon|null
     */
    public function getEndedAt()
    {
        return $this->endedAt;
    }
}
