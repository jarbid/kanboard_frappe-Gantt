/*
 * Frappe Gantt bridge for Kanboard.
 *
 * Kanboard serves this file as a plain script and its Content-Security-Policy
 * forbids inline JavaScript, so every setting arrives in a data attribute on
 * the chart container. This file turns that payload into a fully configured
 * Frappe Gantt instance and wires all of the library's callbacks back to the
 * Kanboard endpoints.
 */
(function () {
    'use strict';

    var STORAGE_PREFIX = 'kb-frappe-gantt:view-mode:';

    /* --------------------------------------------------------------- utils */

    function toISODate(value) {
        var date = value instanceof Date ? value : new Date(value);

        if (isNaN(date.getTime())) {
            return null;
        }

        // Local calendar date: the server stores midnight in its own timezone,
        // so shifting through UTC here would move bars by a day.
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');

        return date.getFullYear() + '-' + month + '-' + day;
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function readStorage(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function writeStorage(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (e) {
            /* Private browsing or blocked storage: view mode just won't stick. */
        }
    }

    /* ------------------------------------------------------------- feedback */

    function notify(container, message, isError) {
        var el = container.querySelector('.kb-gantt-toast');

        if (!el) {
            el = document.createElement('div');
            el.className = 'kb-gantt-toast';
            container.appendChild(el);
        }

        el.textContent = message;
        el.classList.toggle('kb-gantt-toast-error', !!isError);
        el.classList.add('kb-gantt-toast-visible');

        window.clearTimeout(el._timer);
        el._timer = window.setTimeout(function () {
            el.classList.remove('kb-gantt-toast-visible');
        }, isError ? 6000 : 2000);
    }

    /* ---------------------------------------------------------------- posts */

    function post(url, payload, token) {
        return window.fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(Object.assign({ csrf_token: token }, payload))
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response;
        });
    }

    /* -------------------------------------------------------------- options */

    /**
     * Rebuild the option values that cannot survive JSON: the weekend test,
     * the holiday map and the ignored-period list.
     */
    function buildDynamicOptions(options, bridge) {
        var weekendDays = bridge.weekend_days || [];

        options.is_weekend = function (date) {
            return weekendDays.indexOf(date.getDay()) !== -1;
        };

        // Holidays: an object keyed by CSS color. Weekends and explicit dates
        // are kept under separate keys so they can be styled independently.
        var holidays = {};

        if (bridge.highlight_weekends) {
            holidays['var(--g-weekend-highlight-color)'] = 'weekend';
        }

        if (bridge.holidays && bridge.holidays.length) {
            // The library reads the "name" property for the holiday label.
            holidays[bridge.holiday_color] = bridge.holidays.map(function (entry) {
                return entry.label ? { date: entry.date, name: entry.label } : entry.date;
            });
        }

        options.holidays = holidays;

        // Ignored periods: a mixed array of a predicate and plain date strings.
        var ignore = [];

        if (bridge.ignore_weekends) {
            ignore.push(options.is_weekend);
        }

        (bridge.ignore_dates || []).forEach(function (date) {
            ignore.push(date);
        });

        options.ignore = ignore;

        return options;
    }

    /**
     * Restrict the selectable view modes and make sure the one we want to
     * start on comes first.
     *
     * The library forces view_mode to view_modes[0] whenever view_modes is
     * supplied, so ordering is the only way to honour both settings.
     */
    function buildViewModes(options, bridge, storageKey) {
        var enabled = (bridge.enabled_view_modes || []).slice();

        if (!enabled.length) {
            return;
        }

        var preferred = readStorage(storageKey);

        if (!preferred || enabled.indexOf(preferred) === -1) {
            preferred = options.view_mode;
        }

        if (enabled.indexOf(preferred) === -1) {
            preferred = enabled[0];
        }

        options.view_modes = [preferred].concat(enabled.filter(function (name) {
            return name !== preferred;
        }));

        options.view_mode = preferred;
    }

    /* ---------------------------------------------------------------- popup */

    function buildPopup(bridge) {
        var labels = bridge.labels;

        return function (ctx) {
            var task = ctx.task;
            var kb = task.kb || {};

            ctx.set_title(escapeHtml(kb.title || task.name));
            ctx.set_subtitle(task.description ? escapeHtml(task.description) : '');

            var rows = [];

            function row(label, value) {
                if (value !== '' && value !== null && value !== undefined) {
                    rows.push('<strong>' + escapeHtml(label) + ':</strong> ' + escapeHtml(value));
                }
            }

            if (kb.type === 'subtask') {
                row(labels.subtask, kb.title);
                row(labels.assignee, kb.assignee);
            } else {
                row(labels.start_date, kb.has_start === false ? labels.not_defined : toISODate(task._start));
                row(labels.due_date, kb.has_due === false ? labels.not_defined : toISODate(task._end));
                row(labels.assignee, kb.assignee);
                row(labels.column, kb.column);
                row(labels.swimlane, kb.swimlane);
                row(labels.category, kb.category);
                row(labels.project, kb.project);
            }

            if (kb.time_estimated) {
                row(labels.time_estimated, kb.time_estimated + ' ' + labels.hours);
            }

            if (kb.time_spent) {
                row(labels.time_spent, kb.time_spent + ' ' + labels.hours);
            }

            var duration = task.actual_duration;

            if (duration) {
                rows.push(
                    '<strong>' + escapeHtml(labels.duration) + ':</strong> ' +
                    duration + ' ' + escapeHtml(duration === 1 ? labels.day : labels.days) +
                    (task.ignored_duration ? ' (+' + task.ignored_duration + ')' : '')
                );
            }

            rows.push(
                '<strong>' + escapeHtml(labels.progress) + ':</strong> ' +
                Math.round(task.progress) + '%'
            );

            if (kb.has_start === false && kb.has_due === false) {
                rows.push('<em>' + escapeHtml(labels.no_dates) + '</em>');
            }

            ctx.set_details(rows.join('<br/>'));

            if (kb.url) {
                ctx.add_action(escapeHtml(labels.open_task), function () {
                    window.location.href = kb.url;
                });
            }

            if (kb.board_url) {
                ctx.add_action(escapeHtml(labels.open_board), function () {
                    window.location.href = kb.board_url;
                });
            }

            if (kb.gantt_url) {
                ctx.add_action(escapeHtml(labels.open_gantt), function () {
                    window.location.href = kb.gantt_url;
                });
            }
        };
    }

    /* --------------------------------------------------------------- events */

    function attachHandlers(options, config, container, storageKey) {
        var bridge = config.bridge;
        var labels = bridge.labels;
        var endpoints = bridge.endpoints || {};
        var progressTimers = {};
        var dateTimers = {};

        // Remember each bar's server-side dates so a rejected or forbidden
        // change can be rolled back in place.
        var original = {};

        config.tasks.forEach(function (task) {
            original[task.id] = { start: task.start, end: task.end };
        });

        options.on_view_change = function (mode) {
            writeStorage(storageKey, mode && mode.name ? mode.name : String(mode));
        };

        var openOn = bridge.open_task_on;

        function open(task) {
            var kb = task.kb || {};

            if (kb.url) {
                window.location.href = kb.url;
            }
        }

        if (openOn === 'click') {
            options.on_click = open;
        } else if (openOn === 'double_click') {
            options.on_double_click = open;
        }

        if (!bridge.editable) {
            return options;
        }

        options.on_date_change = function (task, start, end) {
            var kb = task.kb || {};

            if (kb.editable === false) {
                // Subtasks have no dates of their own: put the bar back.
                this.update_task(task.id, original[task.id]);
                notify(container, labels.readonly_subtask, true);
                return;
            }

            if (!endpoints.dates) {
                return;
            }

            var payload = {
                id: kb.type === 'project' ? kb.project_id : kb.task_id,
                start: toISODate(start),
                end: toISODate(end)
            };

            var previous = original[task.id];

            if (previous && previous.start === payload.start && previous.end === payload.end) {
                return;
            }

            var self = this;

            // The library reports every intermediate position while a bar is
            // being dragged, so only the position it settles on is saved.
            window.clearTimeout(dateTimers[task.id]);
            dateTimers[task.id] = window.setTimeout(function () {
                post(endpoints.dates, payload, bridge.csrf_token).then(function () {
                    original[task.id] = { start: payload.start, end: payload.end };
                    // Both endpoints are now set, so drop the "undated" styling.
                    kb.has_start = true;
                    kb.has_due = true;
                    notify(container, labels.saved, false);
                }).catch(function () {
                    self.update_task(task.id, original[task.id]);
                    notify(container, labels.save_error, true);
                });
            }, 250);
        };

        options.on_progress_change = function (task, progress) {
            var kb = task.kb || {};

            if (kb.editable === false || !endpoints.progress) {
                return;
            }

            // Dragging fires repeatedly; only the final value is worth saving.
            window.clearTimeout(progressTimers[task.id]);
            progressTimers[task.id] = window.setTimeout(function () {
                post(endpoints.progress, {
                    id: kb.task_id,
                    progress: Math.round(progress)
                }, bridge.csrf_token).then(function () {
                    notify(container, labels.saved, false);
                }).catch(function () {
                    notify(container, labels.save_error, true);
                });
            }, 250);
        };

        return options;
    }

    /* ------------------------------------------------------- state classes */

    /**
     * Apply the state modifier classes (undated, closed, milestone) to the
     * rendered bars.
     *
     * The library assigns task.custom_class through classList.add(), which
     * rejects multi-token strings, so those extra classes cannot be shipped
     * as part of it and are applied here instead.
     */
    function applyStateClasses(container, tasks) {
        tasks.forEach(function (task) {
            var classes = (task.kb && task.kb.classes) || [];

            if (!classes.length) {
                return;
            }

            var wrapper = container.querySelector(
                '.bar-wrapper[data-id="' + String(task.id).replace(/"/g, '\\"') + '"]'
            );

            if (wrapper) {
                classes.forEach(function (name) {
                    wrapper.classList.add(name);
                });
            }
        });
    }

    /**
     * Re-apply the state classes after every re-render, since the library
     * rebuilds the bar elements from scratch when the view mode changes or a
     * task is updated.
     */
    function keepStateClasses(chart, container, tasks) {
        var render = chart.render.bind(chart);

        chart.render = function () {
            render();
            applyStateClasses(container, tasks);
        };

        var updateTask = chart.update_task.bind(chart);

        chart.update_task = function (id, details) {
            var result = updateTask(id, details);
            applyStateClasses(container, tasks);
            return result;
        };

        applyStateClasses(container, tasks);
    }

    /* ----------------------------------------------------------------- init */

    function render(container) {
        var raw = container.getAttribute('data-gantt-config');

        if (!raw) {
            return;
        }

        var config;

        try {
            config = JSON.parse(raw);
        } catch (e) {
            window.console && console.error('Frappe Gantt: invalid configuration', e);
            return;
        }

        if (typeof window.Gantt !== 'function') {
            window.console && console.error('Frappe Gantt: library not loaded');
            return;
        }

        if (!config.tasks || !config.tasks.length) {
            return;
        }

        var storageKey = STORAGE_PREFIX + (container.getAttribute('data-gantt-scope') || 'default');
        var options = config.options || {};

        buildDynamicOptions(options, config.bridge);
        buildViewModes(options, config.bridge, storageKey);
        options.popup = buildPopup(config.bridge);
        attachHandlers(options, config, container, storageKey);

        var target = container.querySelector('.kb-gantt-target') || container;

        try {
            var chart = new window.Gantt(target, config.tasks, options);
            container._gantt = chart;
            keepStateClasses(chart, container, config.tasks);
        } catch (e) {
            window.console && console.error('Frappe Gantt: unable to render the chart', e);
        }
    }

    function init() {
        var containers = document.querySelectorAll('.kb-gantt-chart');

        for (var i = 0; i < containers.length; i++) {
            if (!containers[i]._gantt) {
                render(containers[i]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Kanboard swaps page content over Ajax, so re-run after each navigation.
    document.addEventListener('ajaxComplete', init);
})();
