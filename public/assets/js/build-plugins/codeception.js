var codeceptionPlugin = ActiveBuild.UiPlugin.extend({
    id:              'build-codeception-errors',
    css:             'col-xs-12',
    title:           Lang.get('codeception'),
    lastData:        null,
    lastMeta:        null,
    displayOnUpdate: false,
    rendered:        false,

    register: function () {
        var self            = this;
        var query_data      = ActiveBuild.registerQuery('codeception-data', -1, {key: 'data', plugin: 'codeception'});
        var query_meta_data = ActiveBuild.registerQuery('codeception-meta', -1, {key: 'meta', plugin: 'codeception'});

        $(window).on('codeception-data', function (data) {
            self.onUpdateData(data);
        });

        $(window).on('codeception-meta', function (data) {
            self.onUpdateMeta(data);
        });

        $(window).on('build-updated', function () {
            if (!self.rendered) {
                self.displayOnUpdate = true;

                query_data();
                query_meta_data();
            }
        });
    },

    render: function () {
        return $('<table class="table table-hover" id="codeception-data">' +
            '<thead>' +
            '<tr><th>' + Lang.get('status') + '</th>' +
            '<th>' + Lang.get('codeception_suite') + '</th>' +
            '<th>' + Lang.get('codeception_feature') + '</th>' +
            '<th>' + Lang.get('file') + '</th>' +
            '<th>' + Lang.get('message') + '</th>' +
            '<th>' + Lang.get('codeception_time') + '</th></tr>' +
            '</thead><tbody></tbody><tfoot></tfoot></table>');
    },

    onUpdateData: function (e) {
        if (!e.queryData) {
            $('#build-codeception-errors').hide();
            return;
        }

        this.rendered = true;
        this.lastData = e.queryData;

        var tests = this.lastData[0].value;
        var tbody = $('#codeception-data tbody');

        tbody.empty();

        if (tests.length == 0) {
            $('#build-codeception-errors').hide();
            return;
        }

        for (var i in tests) {
            var rows = $('<tr>' +
                '<td>' + (tests[i].pass ? '<span class="label label-success">' + Lang.get('success') + '</span>' : '<span class="label label-danger">' + Lang.get('failed') + '</span>') + '</td>' +
                '<td>' + tests[i].suite + '</td>' +
                '<td>' + tests[i].feature + '</td>' +
                '<td>' + tests[i].file + '</td>' +
                '<td>' + ((tests[i].message) ? tests[i].message : '') + '</td>' +
                '<td>' + tests[i].time + '</td>' +
                '</tr>');

            tbody.append(rows);
        }

        $('#build-codeception-errors').show();
    },

    onUpdateMeta: function (e) {
        if (!e.queryData) {
            return;
        }

        $('#build-codeception-errors').show();
        $('#build-codeception-errors td').tooltip();

        this.lastMeta = e.queryData;

        var data  = this.lastMeta[0].value;
        var tfoot = $('#codeception-data tfoot');

        tfoot.empty();

        var row = $('<tr>' +
            '<td colspan="3">' +
            Lang.get('codeception_synopsis', data.tests, data.timetaken, data.failures) +
            '</td>' +
            '</tr>');

        tfoot.append(row);
    }
});

ActiveBuild.registerPlugin(new codeceptionPlugin());
