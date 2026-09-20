<?php

namespace Kanboard\Plugin\FrappeGantt\Test;

// PHPUnit loads each test file before Kanboard's bootstrap registers the
// plugin autoloader, so the shared case has to be required explicitly.
require_once __DIR__.'/PluginTestCase.php';

use Kanboard\Core\Security\Role;
use Kanboard\Plugin\FrappeGantt\Plugin;

/**
 * The dependency endpoint writes real task links, so who may reach it and
 * how it is routed are part of its contract.
 */
class DependencyEndpointTest extends PluginTestCase
{
    /**
     * Kanboard core gates task links at PROJECT_MEMBER, through
     * TaskInternalLinkController and the task link API procedures. Editing a
     * dependency from the chart is the same act, so it is gated the same way:
     * anything stricter means someone who can add a link on the task page
     * cannot add it on the chart.
     */
    public function testDependencyEditingIsGatedAtProjectMember()
    {
        $plugin = new Plugin($this->container);
        $plugin->initialize();

        $map = $this->container['projectAccessMap'];

        $roles = $map->getRoles('TaskGanttController', 'saveDependency');

        $this->assertContains(
            Role::PROJECT_MEMBER,
            $roles,
            'a project member must be able to edit dependencies, as they can on the task view'
        );

        $this->assertNotContains(
            Role::PROJECT_VIEWER,
            $roles,
            'a viewer must not be able to edit dependencies'
        );
    }

    /**
     * The chart moves dates at the same role, so the two must not disagree.
     */
    public function testDatesAndDependenciesShareTheSameRole()
    {
        $plugin = new Plugin($this->container);
        $plugin->initialize();

        $map = $this->container['projectAccessMap'];

        $expected = $map->getRoles('TaskGanttController', 'save');

        foreach (array('saveProgress', 'saveDependency') as $action) {
            $this->assertSame(
                $expected,
                $map->getRoles('TaskGanttController', $action),
                $action.' must need the same role as moving dates'
            );
        }
    }
}
