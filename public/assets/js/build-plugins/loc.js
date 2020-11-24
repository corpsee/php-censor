var locPlugin = ActiveBuild.UiPlugin.extend({
    id:              'build-lines-chart',
    css:             'col-xs-12',
    title:           Lang.get('lines_of_code'),
    lastData:        null,
    displayOnUpdate: false,
    rendered:        false,
    chartData:       null,

    register: function () {
        var self  = this;
        var query = ActiveBuild.registerQuery('php_loc-data', -1, {num_builds: 10, key: 'data', plugin: 'php_loc'});

        $(window).on('php_loc-data', function (data) {
            self.onUpdate(data);
        });

        $(window).on('build-updated', function (data) {
            if (data.queryData && data.queryData.status > 1 && !self.rendered) {
                query();
            }
        });
    },

    render: function () {
        var self      = this;
        var container = $('<div id="php_loc-data" style="width: 100%; height: 300px"></div>');

        container.append('<canvas id="php_loc-data-chart" style="width: 100%; height: 300px"></canvas>');

        $(document).on('shown.bs.tab', function () {
            $('#build-lines-chart').hide();
            self.drawChart();
        });

        return container;
    },

    onUpdate: function (e) {
        this.lastData = e.queryData;
        this.displayChart();
    },

    displayChart: function () {
        var self      = this;
        var builds    = this.lastData;
        self.rendered = true;

        self.chartData = {
            labels:   [],
            datasets: [
            {
                    label:       Lang.get('lines'),
                    strokeColor: "#555299",
                    pointColor:  "#555299",
                    data:        []
            },
                {
                    label:       Lang.get('logical_lines'),
                    strokeColor: "#00A65A",
                    pointColor:  "#00A65A",
                    data:        []
            },
                {
                    label:       Lang.get('comment_lines'),
                    strokeColor: "#8AA4AF",
                    pointColor:  "#8AA4AF",
                    data:        []
            },
                {
                    label:       Lang.get('noncomment_lines'),
                    strokeColor: "#00A7D0",
                    pointColor:  "#00A7D0",
                    data:        []
            }
            ]
        };

        for (var i in builds) {
            self.chartData.labels.push(Lang.get('build') + ' ' + builds[i].build_id);
            self.chartData.datasets[0].data.push(builds[i].value.LOC);
            self.chartData.datasets[1].data.push(builds[i].value.LLOC);
            self.chartData.datasets[2].data.push(builds[i].value.CLOC);
            self.chartData.datasets[3].data.push(builds[i].value.NCLOC);
        }

        self.drawChart();
    },

    drawChart: function () {
        var self = this;

        if ($('#information').hasClass('active') && self.chartData && self.lastData) {
            $('#build-lines-chart').show();

            var ctx = $("#php_loc-data-chart").get(0).getContext("2d");
            var phpLocChart = new Chart(ctx, {"responsive": true});

            Chart.defaults.global.responsive = true;

            phpLocChart.Line(self.chartData, {
                datasetFill:          false,
                multiTooltipTemplate: "<%=datasetLabel%>: <%= value %>"
            });
        }
    }
});

ActiveBuild.registerPlugin(new locPlugin());
