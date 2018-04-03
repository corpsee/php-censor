PHPCensor.widgets.allProjects = {
    interval: null,

    init: function () {
        $(document).ready(function () {
            PHPCensor.widgets.allProjects.load();
        });
    },

    load: function() {
        $.ajax({
            url: APP_URL + 'widget-all-projects',

            success: function (data) {
                $(('#widget-all_projects-container')).html(data);
                PHPCensor.widgets.allProjects.interval = setInterval(PHPCensor.widgets.allProjects.update, 10000);
            },

            error: PHPCensor.handleFailedAjax
        });
    },

    update: function () {
        $('.project-box').each(function (index) {
            var projectId = this.id.substring(12);
            var url = 

            $.ajax({
                url: APP_URL + 'widget-all-projects/update/' + projectId,

                success: function (data) {
                    $(('#project-box-' + projectId)).html(data);
                },

                error: PHPCensor.handleFailedAjax
            });

            //Let's build another mechanism for web notification since
            //the above feature is tightly coupled to the view.
            $.ajax({
                url: APP_URL + 'widget-all-projects/webNotificationUpdate/' + projectId,

                success: function (data) {
                    PHPCensor.showWebNotification(data);
                },

                error: PHPCensor.handleFailedAjax
            });
        });
    }
};

PHPCensor.widgets.allProjects.init();
