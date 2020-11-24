var coveragePlugin = ActiveBuild.UiPlugin.extend({
    id:              'build-php_unit-coverage-chart',
    css:             'col-xs-12',
    title:           Lang.get('coverage'),
    lastData:        null,
    displayOnUpdate: false,
    rendered:        false,
    chartData:       null,

    register: function () {
        var self  = this;
        var query = ActiveBuild.registerQuery('php_unit-coverage', -1, {num_builds: 10, key: 'coverage', plugin: 'php_unit'});

        $(window).on('php_unit-coverage', function (data) {
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
        var container = $('<div id="php_unit-coverage" style="width: 100%; height: 300px"></div>');

        container.append('<canvas id="php_unit-coverage-chart" style="width: 100%; height: 300px"></canvas>');

        $(document).on('shown.bs.tab', function () {
            $('#build-php_unit-coverage-chart').hide();
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
                    label:       Lang.get('classes'),
                    strokeColor: "#555299",
                    pointColor:  "#555299",
                    data:        []
            },
                {
                    label:       Lang.get('methods'),
                    strokeColor: "#00A65A",
                    pointColor:  "#00A65A",
                    data:        []
            },
                {
                    label:       Lang.get('lines'),
                    strokeColor: "#8AA4AF",
                    pointColor:  "#8AA4AF",
                    data:        []
            }
            ]
        };

        for (var i in builds) {
            self.chartData.labels.push(Lang.get('build') + ' ' + builds[i].build_id);
            self.chartData.datasets[0].data.push(builds[i].value.classes);
            self.chartData.datasets[1].data.push(builds[i].value.methods);
            self.chartData.datasets[2].data.push(builds[i].value.lines);
        }

        self.drawChart();
    },

    drawChart: function () {
        var self = this;

        if ($('#information').hasClass('active') && self.chartData && self.lastData) {
            $('#build-php_unit-coverage-chart').show();

            var ctx = $("#php_unit-coverage-chart").get(0).getContext("2d");
            var chart = new Chart(ctx, {
                responsive: true
            });

            Chart.defaults.global.responsive = true;

            chart.Line(self.chartData, {
                scaleOverride :       true,
                scaleSteps :          10,
                scaleStepWidth :      10,
                scaleStartValue :     0,
                datasetFill:          false,
                multiTooltipTemplate: "<%=datasetLabel%>: <%= value %>"
            });
        }
    }
});

ActiveBuild.registerPlugin(new coveragePlugin());
