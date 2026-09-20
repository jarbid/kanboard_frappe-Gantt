<?php

namespace Kanboard\Plugin\FrappeGantt\Test;

// PHPUnit loads each test file before Kanboard's bootstrap registers the
// plugin autoloader, so the shared case has to be required explicitly.
require_once __DIR__.'/PluginTestCase.php';

use Kanboard\Helper\UrlHelper;

/**
 * The chart's whole configuration reaches the browser through one HTML
 * attribute, so the escaping on the way in and the browser's decoding on the
 * way out have to be exact inverses. When they are not, the endpoint URLs
 * arrive with their separators mangled, every request lands on a route that
 * does not exist, and Kanboard answers "Controller not found" with a 200 --
 * which looks like success from the browser.
 */
class ChartConfigTest extends PluginTestCase
{
    /**
     * Escape exactly the way Template/gantt/chart.php does.
     */
    private function toAttribute(array $config)
    {
        return htmlspecialchars(
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    /**
     * Decode the way a browser reads a data attribute.
     */
    private function fromAttribute($attribute)
    {
        return json_decode(html_entity_decode($attribute, ENT_QUOTES, 'UTF-8'), true);
    }

    public function testEndpointsSurviveTheAttributeRoundTrip()
    {
        $config = array(
            'bridge' => array(
                'endpoints' => array(
                    'dates' => '/?controller=TaskGanttController&action=save&project_id=1&plugin=FrappeGantt',
                    'progress' => '/?controller=TaskGanttController&action=saveProgress&project_id=1&plugin=FrappeGantt',
                ),
            ),
        );

        $decoded = $this->fromAttribute($this->toAttribute($config));

        $this->assertSame($config['bridge']['endpoints'], $decoded['bridge']['endpoints']);
    }

    /**
     * url->href() joins parameters with "&amp;" for pasting straight into
     * markup. Escaping that a second time for the attribute yields a literal
     * "&amp;" in the URL the browser requests, so only the first parameter
     * survives. The endpoints must therefore be built with url->to().
     */
    public function testUrlHelperToProducesUsableQueryStrings()
    {
        $helper = new UrlHelper($this->container);

        $to = $helper->to('TaskGanttController', 'save', array('project_id' => 1, 'plugin' => 'FrappeGantt'));
        $href = $helper->href('TaskGanttController', 'save', array('project_id' => 1, 'plugin' => 'FrappeGantt'));

        $this->assertStringNotContainsString('&amp;', $to);
        $this->assertStringContainsString('&amp;', $href, 'href() is expected to escape; that is why it must not be used here');

        // The query string each one leaves the browser with, after the
        // attribute escaping and the browser's decoding of it.
        $fromTo = $this->fromAttribute($this->toAttribute(array('u' => $to)))['u'];
        $fromHref = $this->fromAttribute($this->toAttribute(array('u' => $href)))['u'];

        parse_str(ltrim($fromTo, '/?'), $paramsTo);
        parse_str(ltrim($fromHref, '/?'), $paramsHref);

        $this->assertArrayHasKey('action', $paramsTo);
        $this->assertSame('save', $paramsTo['action']);
        $this->assertSame('FrappeGantt', $paramsTo['plugin']);

        // The bug: every parameter after the first arrives prefixed with "amp;".
        $this->assertArrayNotHasKey('action', $paramsHref);
        $this->assertArrayHasKey('amp;action', $paramsHref);
    }

    /**
     * Which helper the controllers use is not observable without driving a
     * full request, so it is asserted against the source. The choice is the
     * whole bug: href() here makes every save silently do nothing.
     */
    public function testControllersBuildEndpointsWithAPlainSeparator()
    {
        $controllers = array(
            __DIR__.'/../Controller/TaskGanttController.php',
            __DIR__.'/../Controller/ProjectGanttController.php',
        );

        foreach ($controllers as $file) {
            $source = file_get_contents($file);

            $pattern = "/'(dates|progress|dependency)' => [^\n]*->url->(\w+)\(/";

            if (! preg_match_all($pattern, $source, $matches)) {
                $this->fail(basename($file).' declares no chart endpoints');
            }

            foreach ($matches[2] as $index => $method) {
                $this->assertSame(
                    'to',
                    $method,
                    basename($file).": the '".$matches[1][$index]."' endpoint must use url->to(), not url->".$method.'()'
                );
            }
        }
    }

    /**
     * The formatters put URLs into the same payload, and the bridge navigates
     * to them with window.location. href() there sends the browser to a URL
     * containing a literal "&amp;", which lands on "Page not found".
     */
    public function testFormattersBuildUrlsWithAPlainSeparator()
    {
        $formatters = array(
            __DIR__.'/../Formatter/TaskGanttFormatter.php',
            __DIR__.'/../Formatter/ProjectGanttFormatter.php',
        );

        foreach ($formatters as $file) {
            $source = file_get_contents($file);

            $this->assertStringNotContainsString(
                'url->href(',
                $source,
                basename($file).': URLs in the chart payload must use url->to()'
            );

            $this->assertStringContainsString(
                'url->to(',
                $source,
                basename($file).' is expected to build at least one URL'
            );
        }
    }

    /**
     * A task title containing markup must still survive the round trip, since
     * the labels are pre-escaped for the library and therefore already carry
     * entities when they are put into the attribute.
     */
    public function testPreEscapedLabelsSurviveTheAttributeRoundTrip()
    {
        $name = htmlspecialchars('#1 <img src=x> & "quoted"', ENT_QUOTES, 'UTF-8', false);
        $config = array('tasks' => array(array('id' => 'task-1', 'name' => $name)));

        $decoded = $this->fromAttribute($this->toAttribute($config));

        $this->assertSame($name, $decoded['tasks'][0]['name']);
    }
}
