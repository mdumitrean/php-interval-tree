<?php declare(strict_types = 1);
use Danon\IntervalTree\IntervalTree;
use PHPUnit\Framework\TestCase;

final class IntervalTreeTest extends TestCase
{
    public function testCanBeCreatedWithEmptyConstructor(): void
    {
        $this->assertInstanceOf(
            IntervalTree::class,
            new IntervalTree
        );
    }

    public function testFindExclusiveIntervalsIntersection(): void
    {
        $intervals = [[6, 8], [1, 4], [2, 3], [5, 12], [1, 1], [3, 5], [5, 7], [9, 15], [6, 18], [22, 344]];
        $tree = new IntervalTree();
        for ($i = 0; $i < count($intervals); $i++) {
            $tree->insert($intervals[$i], $i);
        }

        $nodesInRange = $tree->iterateExclusiveIntersections([2, 3]);
        $intersectedIntervalIndexes = [];
        foreach ($nodesInRange as $node) {
            $intersectedIntervalIndexes[] = $node->getValue();
        }

        $this->assertEquals($intersectedIntervalIndexes, [2]);
    }

    public function testFindAllIntervalsIntersection(): void
    {
        $intervals = [[6, 8], [1, 4], [2, 3], [5, 12], [1, 1], [3, 5], [5, 7], [9, 15], [6, 18], [22, 344]];
        $tree = new IntervalTree();
        for ($i = 0; $i < count($intervals); $i++) {
            $tree->insert($intervals[$i], $i);
        }

        $nodesInRange = $tree->iterateIntersections([2, 3]);
        $intersectedIntervalIndexes = [];
        foreach ($nodesInRange as $node) {
            $intersectedIntervalIndexes[] = $node->getValue();
        }

        $this->assertEquals($intersectedIntervalIndexes, [1, 2, 5]);
    }

    public function testHasIntersection(): void
    {
        $intervals = [[0, 0], [6, 8], [1, 4], [2, 3], [5, 12], [1, 1], [3, 5], [5, 7]];
        $tree = new IntervalTree();
        for ($i = 0; $i < count($intervals); $i++) {
            $tree->insert($intervals[$i], $i);
        }

        $this->assertTrue($tree->hasIntersection([2, 3]));
        $this->assertTrue($tree->hasIntersection([0, 1]));
        $this->assertTrue($tree->hasIntersection([0, 12]));
        $this->assertTrue($tree->hasIntersection([0, 0]));
        $this->assertFalse($tree->hasIntersection([13, 14]));
    }

    public function testCountIntersections(): void
    {
        $intervals = [[6, 8], [1, 4], [2, 3], [5, 12], [1, 1], [3, 5], [5, 7]];
        $tree = new IntervalTree();
        for ($i = 0; $i < count($intervals); $i++) {
            $tree->insert($intervals[$i], $i);
        }

        $this->assertEquals($tree->countIntersections([2, 3]), 3);
        $this->assertEquals($tree->countIntersections([13, 14]), 0);
        $this->assertEquals($tree->countIntersections([0, 1]), 2);
    }

    public function testGetKeys(): void
    {
        $intervals = [[6, 8], [1, 4], [2, 3], [5, 12], [1, 1], [3, 5], [5, 7]];
        $tree = new IntervalTree();
        for ($i = 0; $i < count($intervals); $i++) {
            $tree->insert($intervals[$i], $i);
        }

        $this->assertEquals($tree->getKeys(), [[1, 1], [1, 4], [2, 3], [3, 5], [5, 7], [5, 12], [6, 8]]);
    }

    public function testInsertManyIntervals(): void
    {
        $tree = new IntervalTree();
        for ($i = 0; $i < 250; $i++) {
            $low = rand(1, 250);
            $high = $low + rand(1, 100);
            $tree->insert([$low, $high], $i);
        }

        $this->assertEquals(count($tree->getKeys()), 250);
    }
}
