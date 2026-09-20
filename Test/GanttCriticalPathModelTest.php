<?php

namespace Kanboard\Plugin\FrappeGantt\Test;

// PHPUnit loads each test file before Kanboard's bootstrap registers the
// plugin autoloader, so the shared case has to be required explicitly.
require_once __DIR__.'/PluginTestCase.php';

use Kanboard\Plugin\FrappeGantt\Model\GanttCriticalPathModel;

class GanttCriticalPathModelTest extends PluginTestCase
{
    private function node($duration, array $predecessors = array())
    {
        return array('duration' => $duration, 'predecessors' => $predecessors);
    }

    private function compute(array $nodes)
    {
        $critical = GanttCriticalPathModel::compute($nodes);
        sort($critical);

        return $critical;
    }

    public function testEmptyGraph()
    {
        $this->assertSame(array(), GanttCriticalPathModel::compute(array()));
    }

    /**
     * With nothing linking them, every task decides the end on its own, so
     * only the longest has no float.
     */
    public function testUnlinkedTasks()
    {
        $this->assertSame(array(2), $this->compute(array(
            1 => $this->node(3),
            2 => $this->node(7),
            3 => $this->node(5),
        )));
    }

    public function testSingleChainIsEntirelyCritical()
    {
        $this->assertSame(array(1, 2, 3), $this->compute(array(
            1 => $this->node(2),
            2 => $this->node(3, array(1)),
            3 => $this->node(4, array(2)),
        )));
    }

    /**
     * Two parallel branches: the longer one is critical, the shorter has
     * float and is not.
     */
    public function testLongerBranchWins()
    {
        //        2 (6)
        //  1 (1)        4 (1)
        //        3 (2)
        $critical = $this->compute(array(
            1 => $this->node(1),
            2 => $this->node(6, array(1)),
            3 => $this->node(2, array(1)),
            4 => $this->node(1, array(2, 3)),
        ));

        $this->assertSame(array(1, 2, 4), $critical);
        $this->assertNotContains(3, $critical);
    }

    /**
     * Equal-length branches are both critical: delaying either one moves the
     * end.
     */
    public function testEqualBranchesAreBothCritical()
    {
        $this->assertSame(array(1, 2, 3, 4), $this->compute(array(
            1 => $this->node(1),
            2 => $this->node(4, array(1)),
            3 => $this->node(4, array(1)),
            4 => $this->node(1, array(2, 3)),
        )));
    }

    /**
     * Kanboard lets links form a cycle. A cycle has no longest chain, so
     * reporting nothing is the only honest answer.
     */
    public function testCycleYieldsNothing()
    {
        $this->assertSame(array(), GanttCriticalPathModel::compute(array(
            1 => $this->node(2, array(3)),
            2 => $this->node(3, array(1)),
            3 => $this->node(4, array(2)),
        )));
    }

    public function testCycleAnywhereInTheGraphYieldsNothing()
    {
        $this->assertSame(array(), GanttCriticalPathModel::compute(array(
            1 => $this->node(2),
            2 => $this->node(3, array(1)),
            3 => $this->node(1, array(4)),
            4 => $this->node(1, array(3)),
        )));
    }

    /**
     * A link can point at a task the chart is not showing, for instance in
     * another project or filtered out. Those must not break the passes.
     */
    public function testPredecessorsOutsideTheGraphAreIgnored()
    {
        $this->assertSame(array(1, 2), $this->compute(array(
            1 => $this->node(2, array(99)),
            2 => $this->node(3, array(1, 98)),
        )));
    }

    public function testSelfReferenceIsIgnored()
    {
        $this->assertSame(array(1), $this->compute(array(
            1 => $this->node(2, array(1)),
        )));
    }

    public function testZeroDurationMilestoneOnTheChainIsCritical()
    {
        $critical = $this->compute(array(
            1 => $this->node(3),
            2 => $this->node(0, array(1)),
            3 => $this->node(2, array(2)),
        ));

        $this->assertSame(array(1, 2, 3), $critical);
    }

    public function testNegativeDurationIsTreatedAsZero()
    {
        $critical = $this->compute(array(
            1 => $this->node(-5),
            2 => $this->node(4),
        ));

        $this->assertSame(array(2), $critical);
    }
}
