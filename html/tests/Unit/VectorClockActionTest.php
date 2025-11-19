<?php

namespace Tests\Unit;

use App\Actions\Event\ProcessSingleEventAction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VectorClockActionTest extends TestCase
{
    #[Test]
    public function it_merges_two_vector_clocks_by_taking_maximum_values()
    {
        $clockA = ['device1' => 5, 'device2' => 3];
        $clockB = ['device1' => 2, 'device2' => 7, 'device3' => 1];

        $result = ProcessSingleEventAction::mergeVectorClocks($clockA, $clockB);

        $expected = ['device1' => 5, 'device2' => 7, 'device3' => 1];
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_handles_empty_clocks_in_merge()
    {
        $clockA = [];
        $clockB = ['device1' => 3];

        $result = ProcessSingleEventAction::mergeVectorClocks($clockA, $clockB);

        $this->assertEquals(['device1' => 3], $result);
    }

    #[Test]
    public function it_returns_happened_before_when_first_clock_is_strictly_less()
    {
        $clockA = ['device1' => 2, 'device2' => 1];
        $clockB = ['device1' => 3, 'device2' => 2];

        $result = ProcessSingleEventAction::compareVectorClocks($clockA, $clockB);

        $this->assertEquals('Happened-Before', $result);
    }

    #[Test]
    public function it_returns_happened_after_when_second_clock_is_strictly_less()
    {
        $clockA = ['device1' => 3, 'device2' => 2];
        $clockB = ['device1' => 2, 'device2' => 1];

        $result = ProcessSingleEventAction::compareVectorClocks($clockA, $clockB);

        $this->assertEquals('Happened-After', $result);
    }

    #[Test]
    public function it_returns_concurrent_when_clocks_are_not_comparable()
    {
        $clockA = ['device1' => 2, 'device2' => 1];
        $clockB = ['device1' => 1, 'device2' => 2];

        $result = ProcessSingleEventAction::compareVectorClocks($clockA, $clockB);

        $this->assertEquals('Concurrent', $result);
    }

    #[Test]
    public function it_handles_identical_clocks_as_concurrent()
    {
        $clockA = ['device1' => 2, 'device2' => 3];
        $clockB = ['device1' => 2, 'device2' => 3];

        $result = ProcessSingleEventAction::compareVectorClocks($clockA, $clockB);

        $this->assertEquals('Concurrent', $result);
    }
}
