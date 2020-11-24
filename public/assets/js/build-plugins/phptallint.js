var phptalPlugin = ActiveBuild.UiPlugin.extend({
    id:       'build-php_tal_lint',
    css:      'col-xs-12',
    title:    'PHPTAL Lint',
    lastData: null,
    rendered: false,

    register: function () {
        var self  = this;
        var query = ActiveBuild.registerQuery('php_tal_lint-data', -1, {key: 'data', plugin: 'php_tal_lint'});

        $(window).on('php_tal_lint-data', function (data) {
            self.onUpdate(data);
        });

        $(window).on('build-updated', function () {
            if (!self.rendered) {
                query();
            }
        });
    },

    render: function () {
        return $('<table class="table table-hover" id="php_tal_lint-data">' +
            '<thead>' +
            '<tr>' +
            '   <th>' + Lang.get('file') + '</th>' +
            '   <th>' + Lang.get('line') + '</th>' +
            '   <th>' + Lang.get('message') + '</th>' +
            '</tr>' +
            '</thead><tbody></tbody></table>');
    },

    onUpdate: function (e) {
        if (!e.queryData) {
            $('#build-php_tal_lint').hide();
            return;
        }

        this.rendered = true;
        this.lastData = e.queryData;

        var errors = this.lastData[0].value;
        var tbody  = $('#php_tal_lint-data tbody');

        tbody.empty();

        if (errors.length == 0) {
            $('#build-php_tal_lint').hide();
            return;
        }

        for (var i in errors) {
            var file = errors[i].file;

            if (ActiveBuild.fileLinkTemplate) {
                var fileLink = ActiveBuild.fileLinkTemplate.replace('{FILE}', file);
                fileLink     = fileLink.replace('{LINE}', errors[i].line);

                file = '<a target="_blank" href="' + fileLink + '">' + file + '</a>';
            }

            var row = $('<tr>' +
                '<td>' + file + '</td>' +
                '<td>' + errors[i].line + '</td>' +
                '<td>' + errors[i].message + '</td></tr>');

            if (errors[i].type == 'error') {
                row.addClass('danger');
            }

            tbody.append(row);
        }

        $('#build-php_tal_lint').show();
    }
});

ActiveBuild.registerPlugin(new phptalPlugin());
