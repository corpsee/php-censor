var phpunitPlugin = ActiveBuild.UiPlugin.extend({
    id:              'build-php_unit-errors',
    css:             'col-xs-12',
    title:           Lang.get('php_unit'),
    lastData:        null,
    displayOnUpdate: false,
    rendered:        false,
    statusMap:       {
        success: 'ok',
        failed:  'remove',
        error:   'warning-sign',
        todo:    'info-sign',
        skipped: 'exclamation-sign'
    },

    register: function () {
        var self = this;
        var query = ActiveBuild.registerQuery('php_unit-data', -1, {key: 'data', plugin: 'php_unit'});

        $(window).on('php_unit-data', function (data) {
            self.onUpdate(data);
        });

        $(window).on('build-updated', function () {
            if (!self.rendered) {
                self.displayOnUpdate = true;
                query();
            }
        });
    },

    render: function () {
        return $('<table class="table table-hover" id="php_unit-data">' +
            '<thead>' +
            '<tr>' +
            '<th>' + Lang.get('status') + '</th>' +
            '<th>' + Lang.get('test_message') + '</th>' +
            '<th>' + Lang.get('trace') + '</th>' +
            '</tr>' +
            '</thead><tbody></tbody></table>');
    },

    onUpdate: function (e) {
        if (!e.queryData) {
            $('#build-php_unit-errors').hide();
            return;
        }

        this.rendered = true;
        this.lastData = e.queryData;

        var tests = this.lastData[0].value;
        var thead = $('#php_unit-data thead tr');
        var tbody = $('#php_unit-data tbody');

        thead.empty().append('<th>' + Lang.get('status') + '</th><th>' + Lang.get('test_message') + '</th><th>' + Lang.get('trace') + '</th>');
        tbody.empty();

        if (tests.length == 0) {
            $('#build-php_unit-errors').hide();

            return;
        }

        var counts = {success: 0, failed: 0, error: 0, skipped: 0, todo: 0}, total = 0;

        for (var i in tests) {
            var severity = tests[i].severity || (tests[i].pass ? 'success' : 'failed');
            if ('fail' === severity) {
                severity = 'failed';
            }

            var label = ('success' == severity) ? 'success' : (
                ('error' == severity || 'failed' == severity) ? 'danger' : 'warning'
            );

            var status        = $('<td><span class="label label-' + label + '">' + Lang.get(severity) + '</span></td>'),
                content       = $('<td></td>'),
                trace         = $('<td></td>'),
                message       = $('<div class="visible-line-breaks"></div>').appendTo(content),
                trace_message = $('<div class="visible-line-breaks"></div>').appendTo(trace);

            if (tests[i].message) {
                message.text(tests[i].message);
            } else if (tests[i].test && tests[i].suite) {
                message.text(tests[i].suite + '::' + tests[i].test);
            } else {
                message.html('<i>' + Lang.get('test_no_message') + '</i>');
            }

            if (tests[i].data) {
                content.append('<div>' + this.repr(tests[i].data) + '</div>');
            }

            if (tests[i].trace && tests[i].trace.length) {
                trace_message.append(tests[i].trace);
            }

            $('<tr></tr>').append(status).append(content).append(trace).appendTo(tbody);

            counts[severity]++;
            total++;
        }

        $('#build-php_unit-errors').show();
    },

    repr: function (data) {
        switch (typeof(data)) {
            case 'boolean':
                return '<span class="boolean">' + (data ? 'true' : 'false') + '</span>';
            case 'string':
                return '<span class="string">"' + data + '"</span>';
            case 'undefined':
            case null:
                return '<span class="null">null</span>';
            case 'object':
                var rows = [];
                if (data instanceof Array) {
                    for (var i in data) {
                        rows.push('<tr><td colspan="3">' + this.repr(data[i]) + ',</td></tr>');
                    }
                } else {
                    for (var key in data) {
                        rows.push(
                            '<tr>' +
                            '<td>' + this.repr(key) + '</td>' +
                            '<td>=&gt;</td>' +
                            '<td>' + this.repr(data[key]) + ',</td>' +
                            '</tr>'
                        );
                    }
                }
                return '<table>' +
                    '<tr><th colspan="3">array(</th></tr>' +
                    rows.join('') +
                    '<tr><th colspan="3">)</th></tr>' +
                    '</table>';
        }
        return '???';
    },

    buildTrace: function (trace) {
        var list = '<ol reversed>';

        trace.forEach(function (line) {
            list += '<li>' + line + '</li>';
        });
        list += '</ol>';

        return list;
    }
});

ActiveBuild.registerPlugin(new phpunitPlugin());
