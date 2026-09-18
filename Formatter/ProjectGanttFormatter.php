<?php

namespace Kanboard\Plugin\FrappeGantt\Formatter;

use Kanboard\Core\Filter\FormatterInterface;
use Kanboard\Formatter\BaseFormatter;

/**
 * Turn Kanboard projects into Frappe Gantt bar definitions.
 *
 * Progress is the share of the project's tasks that are closed, which is the
 * only meaningful completion signal a project carries.
 */
class ProjectGanttFormatter extends BaseFormatter implements FormatterInterface
{
    /**
     * @return array
     */
    public function format()
    {
        $projects = $this->query->findAll();

        if (empty($projects)) {
            return array();
        }

        $colors = $this->colorModel->getDefaultColors();
        $color_ids = array_keys($colors);
        $bars = array();
        $index = 0;

        foreach ($projects as $project) {
            $has_start = ! empty($project['start_date']);
            $has_end = ! empty($project['end_date']);

            $start = $has_start ? strtotime($project['start_date']) : time();
            $end = $has_end ? strtotime($project['end_date']) : $start;

            if ($end < $start) {
                $end = $start;
            }

            // Cycle through the palette so adjacent projects stay legible.
            $color_id = $color_ids[$index % count($color_ids)];
            $color = $this->colorModel->getColorProperties($color_id);
            $index++;

            // custom_class must stay a single token: the library passes it to
            // classList.add(). State modifiers are applied by the bridge.
            $classes = array();

            if (! $has_start || ! $has_end) {
                $classes[] = 'kb-gantt-undated';
            }

            $bars[] = array(
                'id' => 'project-'.$project['id'],
                // Written into the label with innerHTML by the library, so it
                // has to arrive already escaped.
                'name' => htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8', false),
                'start' => date('Y-m-d', $start),
                'end' => date('Y-m-d', $end),
                'progress' => $this->getProgress($project['id']),
                'dependencies' => array(),
                'custom_class' => 'kb-gantt-project',
                'color' => $color['background'],
                'color_progress' => $color['border'],
                'description' => '',
                'kb' => array(
                    'type' => 'project',
                    'classes' => $classes,
                    'project_id' => (int) $project['id'],
                    'title' => $project['name'],
                    'url' => $this->helper->url->href('ProjectViewController', 'show', array(
                        'project_id' => $project['id'],
                    )),
                    'board_url' => $this->helper->url->href('BoardViewController', 'show', array(
                        'project_id' => $project['id'],
                    )),
                    'gantt_url' => $this->helper->url->href('TaskGanttController', 'show', array(
                        'project_id' => $project['id'],
                        'plugin' => 'FrappeGantt',
                    )),
                    'has_start' => $has_start,
                    'has_due' => $has_end,
                    'editable' => true,
                ),
            );
        }

        return $bars;
    }

    /**
     * Share of closed tasks in a project, as a percentage.
     *
     * @param  int $project_id
     * @return float
     */
    private function getProgress($project_id)
    {
        $total = $this->taskFinderModel->countByProjectId($project_id);

        if ($total === 0) {
            return 0.0;
        }

        $open = $this->taskFinderModel->countByProjectId($project_id, array(\Kanboard\Model\TaskModel::STATUS_OPEN));

        return round((($total - $open) * 100) / $total, 1);
    }
}
