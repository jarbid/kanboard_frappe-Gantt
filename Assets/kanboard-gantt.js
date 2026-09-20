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
    var SIDEBAR_WIDTH_PREFIX = 'kb-frappe-gantt:sidebar-width:';
    var SIDEBAR_FIELDS_PREFIX = 'kb-frappe-gantt:sidebar-fields:';

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

            // Kanboard answers a request it cannot route with a plain text
            // error and a 200, so the status alone does not tell us the save
            // happened. Every real response from the plugin is JSON.
            return response.text().then(function (body) {
                var payload;

                try {
                    payload = JSON.parse(body);
                } catch (e) {
                    throw new Error('Unexpected response: ' + body.slice(0, 120));
                }

                return payload;
            });
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

                    // The task now has both dates, so it is no longer undated.
                    // The class has to come off the bar and out of kb.classes,
                    // otherwise the next re-render puts it straight back.
                    kb.has_start = true;
                    kb.has_due = true;
                    dropStateClass(container, task, 'kb-gantt-undated');

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

    /* ----------------------------------------------------- the task column */

    /**
     * Fields the task column can show. The task id and its title are always
     * present, so they are not listed here.
     */
    var SIDEBAR_FIELDS = ['assignee', 'start', 'due', 'progress', 'category', 'swimlane', 'column'];

    /**
     * Label for one optional field, reusing the labels the popup already has.
     */
    function fieldLabel(field, labels) {
        switch (field) {
            case 'start': return labels.start_date;
            case 'due': return labels.due_date;
            default: return labels[field] || field;
        }
    }

    /**
     * Value of one optional field for a bar.
     */
    function fieldValue(task, field, labels) {
        var kb = task.kb || {};

        switch (field) {
            case 'assignee': return kb.assignee || '';
            case 'start': return kb.has_start === false ? labels.not_defined : task.start;
            case 'due': return kb.has_due === false ? labels.not_defined : task.end;
            case 'progress': return Math.round(task.progress || 0) + '%';
            case 'category': return kb.category || '';
            case 'swimlane': return kb.swimlane || '';
            case 'column': return kb.column || '';
        }

        return '';
    }

    /**
     * Build one row of the task column.
     *
     * The row is sized to the library's own geometry rather than measured
     * from the DOM: a bar sits at
     *     header_height + padding / 2 + index * (bar_height + padding)
     * so a row of bar_height with a padding gap lines up with it exactly, at
     * any zoom level and after any re-render.
     */
    function buildSidebarRow(task, fields, labels, geometry) {
        var kb = task.kb || {};
        var row = document.createElement('div');

        row.className = 'kb-gantt-side-row';
        row.style.height = geometry.bar_height + 'px';
        row.style.marginBottom = geometry.padding + 'px';
        row.setAttribute('data-id', task.id);

        var name = document.createElement('div');
        name.className = 'kb-gantt-side-name';

        if (kb.type === 'subtask') {
            name.classList.add('kb-gantt-side-subtask');
        }

        if (kb.type === 'task' && kb.task_id) {
            var id = document.createElement('span');
            id.className = 'kb-gantt-side-id';
            id.textContent = '#' + kb.task_id;
            name.appendChild(id);
        }

        if (kb.type === 'project') {
            // The original Kanboard chart put the board and gantt of each
            // project within reach here; keep that.
            [['open_board', 'board_url', 'th'], ['open_gantt', 'gantt_url', 'sliders']].forEach(function (entry) {
                if (!kb[entry[1]]) {
                    return;
                }

                var shortcut = document.createElement('a');
                shortcut.className = 'kb-gantt-side-icon';
                shortcut.href = kb[entry[1]];
                shortcut.title = labels[entry[0]] || '';
                shortcut.innerHTML = '<i class="fa fa-' + entry[2] + '" aria-hidden="true"></i>';
                name.appendChild(shortcut);
            });
        }

        var title = kb.url ? document.createElement('a') : document.createElement('span');

        if (kb.url) {
            title.href = kb.url;
        }

        title.className = 'kb-gantt-side-title';
        title.textContent = kb.title || task.name || '';
        title.title = kb.title || '';
        name.appendChild(title);
        row.appendChild(name);

        fields.forEach(function (field) {
            var cell = document.createElement('div');
            cell.className = 'kb-gantt-side-cell kb-gantt-side-' + field;
            cell.textContent = fieldValue(task, field, labels);
            cell.title = cell.textContent;
            row.appendChild(cell);
        });

        return row;
    }

    /**
     * Menu for choosing which optional fields the column shows.
     */
    function buildFieldPicker(head, state, labels, onChange) {
        var toggle = document.createElement('button');

        toggle.type = 'button';
        toggle.className = 'kb-gantt-side-toggle';
        toggle.textContent = labels.columns;

        var menu = document.createElement('div');
        menu.className = 'kb-gantt-side-menu';
        menu.hidden = true;

        SIDEBAR_FIELDS.forEach(function (field) {
            var item = document.createElement('label');
            var box = document.createElement('input');

            box.type = 'checkbox';
            box.checked = state.fields.indexOf(field) !== -1;

            box.addEventListener('change', function () {
                var index = state.fields.indexOf(field);

                if (box.checked && index === -1) {
                    state.fields.push(field);
                } else if (!box.checked && index !== -1) {
                    state.fields.splice(index, 1);
                }

                // Keep a stable order so the columns do not jump around.
                state.fields.sort(function (a, b) {
                    return SIDEBAR_FIELDS.indexOf(a) - SIDEBAR_FIELDS.indexOf(b);
                });

                onChange();
            });

            item.appendChild(box);
            item.appendChild(document.createTextNode(' ' + fieldLabel(field, labels)));
            menu.appendChild(item);
        });

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            menu.hidden = !menu.hidden;
        });

        document.addEventListener('click', function (event) {
            if (!menu.hidden && !menu.contains(event.target) && event.target !== toggle) {
                menu.hidden = true;
            }
        });

        head.appendChild(toggle);
        head.appendChild(menu);
    }

    /**
     * Let the divider between the column and the chart be dragged.
     */
    function attachSidebarResizer(resizer, sidebar, state, persist) {
        var dragging = false;
        var startX = 0;
        var startWidth = 0;

        resizer.addEventListener('mousedown', function (event) {
            dragging = true;
            startX = event.clientX;
            startWidth = sidebar.offsetWidth;
            document.body.classList.add('kb-gantt-resizing');
            event.preventDefault();
        });

        document.addEventListener('mousemove', function (event) {
            if (!dragging) {
                return;
            }

            var width = Math.min(760, Math.max(120, startWidth + (event.clientX - startX)));
            state.width = width;
            sidebar.style.width = width + 'px';
        });

        document.addEventListener('mouseup', function () {
            if (!dragging) {
                return;
            }

            dragging = false;
            document.body.classList.remove('kb-gantt-resizing');
            persist();
        });
    }

    /**
     * Render the task column beside the chart and keep the two aligned.
     */
    function buildSidebar(container, chart, config, scope) {
        var bridge = config.bridge || {};
        var labels = bridge.labels || {};
        var geometry = bridge.row_geometry || { bar_height: 30, padding: 18 };

        if (!bridge.left_column) {
            return;
        }

        var widthKey = SIDEBAR_WIDTH_PREFIX + scope;
        var fieldsKey = SIDEBAR_FIELDS_PREFIX + scope;
        var storedFields = readStorage(fieldsKey);
        var storedWidth = parseInt(readStorage(widthKey), 10);

        var state = {
            width: storedWidth > 0 ? storedWidth : (bridge.left_column_width || 260),
            fields: (storedFields ? storedFields.split(',') : (bridge.left_column_fields || []))
                .filter(function (field) {
                    return SIDEBAR_FIELDS.indexOf(field) !== -1;
                })
        };

        function persist() {
            writeStorage(widthKey, String(state.width));
            writeStorage(fieldsKey, state.fields.join(','));
        }

        var sidebar = document.createElement('div');
        sidebar.className = 'kb-gantt-side';
        sidebar.style.width = state.width + 'px';

        var head = document.createElement('div');
        head.className = 'kb-gantt-side-head';

        var body = document.createElement('div');
        body.className = 'kb-gantt-side-body';

        var rows = document.createElement('div');
        rows.className = 'kb-gantt-side-rows';

        body.appendChild(rows);
        sidebar.appendChild(head);
        sidebar.appendChild(body);

        var resizer = document.createElement('div');
        resizer.className = 'kb-gantt-side-resizer';

        var target = container.querySelector('.kb-gantt-target') || container;
        container.classList.add('kb-gantt-has-side');
        target.parentNode.insertBefore(sidebar, target);
        target.parentNode.insertBefore(resizer, target);

        function paint() {
            // The header is as tall as the chart's own, so row zero starts on
            // the same line as the first bar.
            head.style.height = chart.config.header_height + 'px';
            rows.style.paddingTop = (geometry.padding / 2) + 'px';
            rows.innerHTML = '';

            chart.tasks.forEach(function (task) {
                rows.appendChild(buildSidebarRow(task, state.fields, labels, geometry));
            });

            matchChartHeight();
        }

        /**
         * Give the column's body exactly the height of the chart's scrolling
         * area.
         *
         * Without this the column is as tall as its own rows, so it has
         * nothing to scroll: when container_height makes the chart scroll
         * internally, setting its scrollTop does nothing and the two panes
         * drift apart by the whole scroll distance. When the chart is set to
         * grow instead, this resolves to the full height and the page scrolls
         * both panes together, which is also what we want.
         */
        function matchChartHeight() {
            if (!chart.$container) {
                return;
            }

            body.style.height = chart.$container.clientHeight + 'px';
        }

        function refresh() {
            paint();
            persist();
        }

        buildFieldPicker(head, state, labels, refresh);
        attachSidebarResizer(resizer, sidebar, state, persist);
        paint();

        // Keep the two panes on the same line while either one scrolls.
        var scroller = chart.$container;
        var syncing = false;

        function mirror(from, to) {
            if (syncing) {
                return;
            }

            syncing = true;
            to.scrollTop = from.scrollTop;
            syncing = false;
        }

        scroller.addEventListener('scroll', function () { mirror(scroller, body); });
        body.addEventListener('scroll', function () { mirror(body, scroller); });

        // The chart reflows with the window, so the column has to be
        // re-measured against it.
        window.addEventListener('resize', matchChartHeight);

        // Inserting the column narrowed the chart after it had already placed
        // its initial horizontal scroll, which leaves it looking at the wrong
        // dates. Ask it to scroll again now the width is final.
        if (typeof chart.set_scroll_position === 'function') {
            chart.set_scroll_position(chart.options.scroll_to || 'today');
        }

        // Hovering either side highlights the other.
        rows.addEventListener('mouseover', function (event) {
            var row = event.target.closest('.kb-gantt-side-row');

            if (!row) {
                return;
            }

            var bar = container.querySelector('.bar-wrapper[data-id="' + row.getAttribute('data-id') + '"]');

            if (bar) {
                bar.classList.add('kb-gantt-hover');
            }
        });

        rows.addEventListener('mouseout', function () {
            var hovered = container.querySelector('.bar-wrapper.kb-gantt-hover');

            if (hovered) {
                hovered.classList.remove('kb-gantt-hover');
            }
        });

        // A re-render rebuilds the bars and can change the header height, so
        // the column is repainted alongside it.
        var render = chart.render.bind(chart);

        chart.render = function () {
            render();
            paint();
        };

        return paint;
    }

    /* -------------------------------------------------------- zoom controls */

    /* View modes from finest to coarsest, with the days each column covers.
       The library orders its own list by preference, not granularity, so
       zooming needs its own order. */
    var ZOOM_ORDER = ['Hour', 'Quarter Day', 'Half Day', 'Day', 'Week', 'Month', 'Year'];
    var STEP_DAYS = {
        'Hour': 1 / 24,
        'Quarter Day': 0.25,
        'Half Day': 0.5,
        'Day': 1,
        'Week': 7,
        'Month': 30,
        'Year': 365
    };

    /**
     * Enabled view modes, finest first.
     */
    function zoomableModes(chart) {
        return (chart.options.view_modes || [])
            .slice()
            .filter(function (mode) {
                return ZOOM_ORDER.indexOf(mode.name) !== -1;
            })
            .sort(function (a, b) {
                return ZOOM_ORDER.indexOf(a.name) - ZOOM_ORDER.indexOf(b.name);
            });
    }

    /**
     * The window the chart has to cover: its earliest start, its latest end,
     * and the number of days between them.
     */
    function taskWindow(tasks) {
        var min = null;
        var max = null;

        tasks.forEach(function (task) {
            var start = new Date(task.start);
            var end = new Date(task.end);

            if (isNaN(start) || isNaN(end)) {
                return;
            }

            if (min === null || start < min) { min = start; }
            if (max === null || end > max) { max = end; }
        });

        if (min === null) {
            return null;
        }

        return { start: min, end: max, days: Math.max(1, (max - min) / 86400000) };
    }

    /**
     * Step one view mode finer or coarser.
     */
    function stepZoom(chart, direction, onChange) {
        var modes = zoomableModes(chart);
        var current = modes.findIndex(function (mode) {
            return mode.name === chart.config.view_mode.name;
        });

        if (current === -1) {
            return;
        }

        var next = modes[current + direction];

        if (!next) {
            return;
        }

        chart.change_view_mode(next.name, true);
        onChange(next.name);
    }

    /**
     * Pick the finest view mode whose full span still fits the chart.
     */
    function fitZoom(chart, tasks, onChange) {
        var modes = zoomableModes(chart);
        var window_ = taskWindow(tasks);
        var available = chart.$container.clientWidth;

        if (!window_ || !available) {
            return;
        }

        for (var i = 0; i < modes.length; i++) {
            var mode = modes[i];
            var columnWidth = mode.column_width || chart.options.column_width || 45;
            var width = (window_.days / STEP_DAYS[mode.name]) * columnWidth;

            if (width <= available || i === modes.length - 1) {
                chart.change_view_mode(mode.name, true);
                onChange(mode.name);

                // Choosing the mode is only half of it: the chart is still
                // looking wherever it was, which after a zoom is rarely the
                // plan. Put the earliest task at the left edge.
                scrollToWindowStart(chart, window_);
                return;
            }
        }
    }

    /**
     * Scroll so the plan starts at the left edge, a little before the first
     * task so its bar is not flush against the frame.
     */
    function scrollToWindowStart(chart, window_) {
        var start = new Date(window_.start.getTime() - 86400000);
        var iso = start.getFullYear() + '-' +
            ('0' + (start.getMonth() + 1)).slice(-2) + '-' +
            ('0' + start.getDate()).slice(-2);

        // The chart re-renders on a view mode change, so the scroll is set
        // once that has settled.
        window.setTimeout(function () {
            try {
                chart.set_scroll_position(iso);
            } catch (e) {
                chart.set_scroll_position('start');
            }
        }, 0);
    }

    /**
     * Add zoom buttons beside the library's own header controls.
     */
    function buildZoomControls(chart, container, config, storageKey) {
        var labels = (config.bridge || {}).labels || {};

        if (zoomableModes(chart).length < 2) {
            return;
        }

        function remember(name) {
            writeStorage(storageKey, name);
            syncViewModeSelect(container, name);
        }

        /**
         * Changing the view mode re-renders the chart, which rebuilds the
         * header these buttons live in, so they are put back after every
         * render rather than only once.
         */
        function inject() {
            var header = container.querySelector('.side-header');

            if (!header || header.querySelector('.kb-gantt-zoom')) {
                return;
            }

            function button(text, title, handler) {
                var el = document.createElement('button');

                el.type = 'button';
                el.className = 'kb-gantt-zoom';
                el.textContent = text;
                el.title = title;
                el.addEventListener('click', handler);
                header.prepend(el);
            }

            // Prepended, so these are added in reverse of their visual order.
            button('\u29C9', labels.zoom_fit || 'Fit', function () {
                fitZoom(chart, config.tasks, remember);
            });
            button('+', labels.zoom_in || 'Zoom in', function () {
                stepZoom(chart, -1, remember);
            });
            button('\u2212', labels.zoom_out || 'Zoom out', function () {
                stepZoom(chart, 1, remember);
            });
        }

        var render = chart.render.bind(chart);

        chart.render = function () {
            render();
            inject();
        };

        inject();
    }

    /**
     * Keep the library's own mode dropdown in step when we change the mode
     * for it.
     */
    function syncViewModeSelect(container, name) {
        var select = container.querySelector('.viewmode-select');

        if (select) {
            select.value = name;
        }
    }

    /* ---------------------------------------------------------- milestones */

    /**
     * Draw milestones as diamonds.
     *
     * Kanboard marks a task as a milestone when it is linked with "is a
     * milestone of". A milestone is a point in time rather than a span, and
     * every Gantt convention draws it as a diamond, so the bar is replaced
     * with one sitting on the task's start date.
     */
    function decorateMilestones(container, tasks) {
        tasks.forEach(function (task) {
            var classes = (task.kb && task.kb.classes) || [];

            if (classes.indexOf('kb-gantt-milestone') === -1) {
                return;
            }

            var wrapper = container.querySelector(
                '.bar-wrapper[data-id="' + String(task.id).replace(/"/g, '\\"') + '"]'
            );

            if (!wrapper || wrapper.querySelector('.kb-gantt-diamond')) {
                return;
            }

            var bar = wrapper.querySelector('.bar');

            if (!bar) {
                return;
            }

            var x = parseFloat(bar.getAttribute('x'));
            var y = parseFloat(bar.getAttribute('y'));
            var height = parseFloat(bar.getAttribute('height'));
            var side = height * 0.62;
            var cx = x;
            var cy = y + height / 2;

            var diamond = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            diamond.setAttribute('class', 'kb-gantt-diamond');
            diamond.setAttribute('x', cx - side / 2);
            diamond.setAttribute('y', cy - side / 2);
            diamond.setAttribute('width', side);
            diamond.setAttribute('height', side);
            diamond.setAttribute('transform', 'rotate(45 ' + cx + ' ' + cy + ')');

            if (bar.style.fill) {
                diamond.style.fill = bar.style.fill;
            }

            bar.parentNode.appendChild(diamond);
        });
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
     * Remove a state modifier from a bar and from the task it came from, so
     * that re-renders do not restore it.
     */
    function dropStateClass(container, task, name) {
        var classes = (task.kb && task.kb.classes) || [];
        var index = classes.indexOf(name);

        if (index !== -1) {
            classes.splice(index, 1);
        }

        var wrapper = container.querySelector(
            '.bar-wrapper[data-id="' + String(task.id).replace(/"/g, '\\"') + '"]'
        );

        if (wrapper) {
            wrapper.classList.remove(name);
        }
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
            decorateMilestones(container, tasks);
        };

        var updateTask = chart.update_task.bind(chart);

        chart.update_task = function (id, details) {
            var result = updateTask(id, details);
            applyStateClasses(container, tasks);
            decorateMilestones(container, tasks);
            return result;
        };

        applyStateClasses(container, tasks);
        decorateMilestones(container, tasks);
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
            buildSidebar(container, chart, config, container.getAttribute('data-gantt-scope') || 'default');
            buildZoomControls(chart, container, config, storageKey);

            // "fit" is ours, not the library's: frame the whole plan instead
            // of a fixed point in time.
            if (config.bridge && config.bridge.scroll_to_fit) {
                fitZoom(chart, config.tasks, function (name) {
                    syncViewModeSelect(container, name);
                });
                chart.set_scroll_position('start');
            }
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
