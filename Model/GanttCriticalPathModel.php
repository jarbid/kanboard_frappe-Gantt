<?php

namespace Kanboard\Plugin\FrappeGantt\Model;

/**
 * Critical path calculation.
 *
 * Works on the dependency graph alone: durations and the links between
 * tasks, anchored at zero rather than at the calendar. What it reports is
 * the chain whose length decides how long the whole plan takes, which is
 * what a critical path means, independently of where the bars currently sit.
 *
 * Pure computation, so it takes no container and can be tested on its own.
 */
class GanttCriticalPathModel
{
    /**
     * Tasks whose total float is zero.
     *
     * @param  array $nodes id => array('duration' => float, 'predecessors' => int[])
     * @return int[]
     */
    public static function compute(array $nodes)
    {
        $nodes = self::clean($nodes);

        if (empty($nodes)) {
            return array();
        }

        $successors = self::successors($nodes);
        $order = self::topologicalOrder($nodes, $successors);

        if ($order === null) {
            // The graph has a cycle, so no chain through it has a meaningful
            // length. Reporting an arbitrary subset would be worse than
            // reporting nothing.
            return array();
        }

        $earliest_start = array();
        $earliest_finish = array();

        foreach ($order as $id) {
            $start = 0.0;

            foreach ($nodes[$id]['predecessors'] as $predecessor) {
                $start = max($start, $earliest_finish[$predecessor]);
            }

            $earliest_start[$id] = $start;
            $earliest_finish[$id] = $start + $nodes[$id]['duration'];
        }

        $end = max($earliest_finish);

        $latest_finish = array();
        $latest_start = array();

        foreach (array_reverse($order) as $id) {
            $finish = $end;

            foreach ($successors[$id] as $successor) {
                $finish = min($finish, $latest_start[$successor]);
            }

            $latest_finish[$id] = $finish;
            $latest_start[$id] = $finish - $nodes[$id]['duration'];
        }

        $critical = array();

        foreach ($nodes as $id => $node) {
            // Floating point durations, so compare with a tolerance rather
            // than for equality.
            if (abs($latest_start[$id] - $earliest_start[$id]) < 0.000001) {
                $critical[] = $id;
            }
        }

        return $critical;
    }

    /**
     * Drop predecessors that are not themselves in the graph, so a link to a
     * task outside the chart cannot break the passes.
     *
     * @param  array $nodes
     * @return array
     */
    private static function clean(array $nodes)
    {
        $cleaned = array();

        foreach ($nodes as $id => $node) {
            $predecessors = array();

            foreach ($node['predecessors'] as $predecessor) {
                if ($predecessor !== $id && isset($nodes[$predecessor])) {
                    $predecessors[] = $predecessor;
                }
            }

            $cleaned[$id] = array(
                'duration' => max(0.0, (float) $node['duration']),
                'predecessors' => array_values(array_unique($predecessors)),
            );
        }

        return $cleaned;
    }

    /**
     * @param  array $nodes
     * @return array id => int[]
     */
    private static function successors(array $nodes)
    {
        $successors = array();

        foreach (array_keys($nodes) as $id) {
            $successors[$id] = array();
        }

        foreach ($nodes as $id => $node) {
            foreach ($node['predecessors'] as $predecessor) {
                $successors[$predecessor][] = $id;
            }
        }

        return $successors;
    }

    /**
     * Kahn's algorithm.
     *
     * @param  array $nodes
     * @param  array $successors
     * @return int[]|null null when the graph has a cycle
     */
    private static function topologicalOrder(array $nodes, array $successors)
    {
        $remaining = array();
        $queue = array();

        foreach ($nodes as $id => $node) {
            $remaining[$id] = count($node['predecessors']);

            if ($remaining[$id] === 0) {
                $queue[] = $id;
            }
        }

        $order = array();

        while (! empty($queue)) {
            $id = array_shift($queue);
            $order[] = $id;

            foreach ($successors[$id] as $successor) {
                $remaining[$successor]--;

                if ($remaining[$successor] === 0) {
                    $queue[] = $successor;
                }
            }
        }

        return count($order) === count($nodes) ? $order : null;
    }
}
