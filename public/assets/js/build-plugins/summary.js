var SummaryPlugin = ActiveBuild.UiPlugin.extend({
    id:            'build-summary',
    css:           'col-xs-12',
    title:         Lang.get('build-summary'),
    statusLabels:  [
        Lang.get('pending'),
        Lang.get('running'),
        Lang.get('success'),
        Lang.get('failed'),
        Lang.get('failed_allowed')
    ],
    statusClasses: [
        'info',
        'warning',
        'success',
        'danger',
        'danger'
    ],

    register: function () {
        var self  = this;
        var query = ActiveBuild.registerQuery('build-summary', 5, {key: 'summary'});

        $(window).on('build-summary', function (data) {
            self.onUpdate(data);
        });

        $(window).on('build-updated', function () {
            query();
        });
    },

    render: function () {
        return $(
            '<table class="table table-hover" id="build-summary">' +
            '<thead><tr>' +
            '<th>' + Lang.get('stage') + '</th>' +
            '<th>' + Lang.get('plugin') + '</th>' +
            '<th>' + Lang.get('status') + '</th>' +
            '<th class="text-right">' + Lang.get('duration') + ' (' + Lang.get('seconds') + ')</th>' +
            '</tr></thead><tbody></tbody></table>'
        );
    },

    onUpdate: function (e) {
        if (!e.queryData) {
            $('#build-summary').hide();
            return;
        }

        var tbody = $('#build-summary tbody'),
            summary = e.queryData[0].value;

        tbody.empty();

        for (var stage in summary) {
            for (var plugin in summary[stage]) {
                var data     = summary[stage][plugin],
                    duration = data.started ? ((data.ended || Math.floor(Date.now() / 1000)) - data.started) : '-';

                var pluginName = Lang.get(plugin);
                if (0 < data.errors) {
                    pluginName = '<a href="' + window.APP_URL + 'build/view/' + ActiveBuild.buildId + '?plugin=' + plugin + '#errors">' + Lang.get(plugin) + '</a>';
                }
                tbody.append(
                    '<tr>' +
                    '<td>' + Lang.get('stage_' + stage) + '</td>' +
                    '<td>' + pluginName + '</td>' +
                    '<td><span  class="label label-' + this.statusClasses[data.status] + '">' +
                    this.statusLabels[data.status] +
                    '</span></td>' +
                    '<td class="text-right">' + duration + '</td>' +
                    '</tr>'
                );
            }
        }

        $('#build-summary').show();
    }
});

ActiveBuild.registerPlugin(new SummaryPlugin());
