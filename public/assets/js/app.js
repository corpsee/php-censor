var PHPCensor = {
    intervals: {},
    widgets: {},
    webNotifiedBuilds: [],
    /*
        @var  STATUS  Refer to \PHPCensor\Model\Build.php constants.
        TODO: Transfer this variable to Build JS class so
        Build JS itself can use it as well.
    */
    STATUS: [
        'Pending',
        'Running',
        'Success',
        'Failed'
    ],

    init: function () {
        $(document).ready(function () {
            // Update latest builds every 5 seconds:
            PHPCensor.getBuilds();
            PHPCensor.intervals.getBuilds = setInterval(PHPCensor.getBuilds, 5000);

            // Update latest project builds every 10 seconds:
            if (typeof PROJECT_ID != 'undefined') {
                PHPCensor.intervals.getProjectBuilds = setInterval(PHPCensor.getProjectBuilds, 10000);
            }
        });

        $(window).on('builds-updated', function (e, data) {
            PHPCensor.updateHeaderBuilds(data);
        });
    },

    /**
     * Shallow comparison that determines that the build
     * has been shown as at least once as a web notification.
     * Also adds the build to a list of shown web notifications
     * if it's not found in the list.
     * @param  object  build
     * @return boolean
     */
    isWebNotifiedBuild: function (build) {
        var o = PHPCensor.webNotifiedBuilds;
        for (var i = 0; i < o.length; i++) {
            var webNotifiedBuild = o[i];
            var b =
                webNotifiedBuild.projectTitle  === build.projectTitle &&
                webNotifiedBuild.branch        === build.branch &&
                webNotifiedBuild.status        === build.status &&
                webNotifiedBuild.datePerformed === build.datePerformed &&
                webNotifiedBuild.dateFinished  === build.dateFinished;
            if (b) {
                return true;
            }
        }
        /*
            It's impossible to remember or use all previously shown
            builds. So let's clear them out once they reach 1000.
            @var 1000  Estimated.
        */
        if (PHPCensor.webNotifiedBuilds.length > 1000) {
            PHPCensor.webNotifiedBuilds = [];
        }
        PHPCensor.webNotifiedBuilds.push(build);
        return false;
    },

    /**
     * Web notification.
     * Chrome doesn't allow insecure protocols.
     * Enable HTTPS even on localhost in order for
     * web notifications to work properly.
     * @param  object data  Contains an array of builds.
     * @return void
     */
    showWebNotification: function (data) {
        var pending = data.pending;
        var running = data.running;
        var success = data.success;
        var failed  = data.failed;
        var notification = null;

        //Determine which notification to show.
        //TODO: Refactor. Use foreach.
        if (pending && pending.count > 0) {
            notification = pending;
        }
        else if (running && running.count > 0) {
            notification = running;
        }
        else if (success && success.count > 0) {
            notification = success;
        }
        else if (failed && failed.count > 0) {
            notification = failed;
        }

        if (notification) {
            var msg = '';
            if (!Notify.needsPermission) {
                var items = notification.items;
                for (var item in items) {
                    var build         = items[item].build;
                    var projTitle     = build.project_title;
                    var branch        = build.branch;
                    var status        = PHPCensor.STATUS[build.status];
                    var datePerformed = build.date_performed;
                    var dateFinished  = build.date_finished;
                    var rn            = "\r\n";

                    var build = {
                        projectTitle: projTitle,
                        branch: branch,
                        status: status,
                        datePerformed: datePerformed,
                        dateFinished: dateFinished
                    };

                    //Ignore if the last displayed notification is
                    //similar to what we're again about to display.
                    if (!PHPCensor.isWebNotifiedBuild(build)) {
                        msg +=
                            'Project title: ' + projTitle  + rn +
                            'Git branch: '    + branch     + rn +
                            'Status: '  + status     + rn;

                        //Build details is empty during
                        //widget-all-projects-update.
                        if (datePerformed.length > 0) {
                            msg += datePerformed + rn;
                        }

                        if (dateFinished.length > 0) {
                            msg += dateFinished;
                        }

                        new Notify(
                            'PHP Censor Web Notification',
                            {body: msg}
                        ).show();
                    }

                }

            }
            else if (Notify.isSupported()) {
                Notify.requestPermission(null, function(){
                    msg = 'Web notifications permission ' +
                          'has been denied by the user.'
                    console.warn(msg);
                });
            }
        }
    },

    getBuilds: function () {
        $.ajax({
            url: APP_URL + 'build/ajax-queue',

            success: function (data) {
                $(window).trigger('builds-updated', [data]);
            },

            error: PHPCensor.handleFailedAjax
        });

        $.ajax({
            url: APP_URL + 'web-notifications/builds-queue',
            success: function (data) {
                PHPCensor.showWebNotification(data);
            },
            error: PHPCensor.handleFailedAjax
        });
    },

    getProjectBuilds: function () {
        $.ajax({
            url: APP_URL + 'project/ajax-builds/' + PROJECT_ID + '?branch=' + PROJECT_BRANCH + '&environment=' + PROJECT_ENVIRONMENT + '&per_page=' + PER_PAGE + '&page=' + PAGE,

            success: function (data) {
                $('#latest-builds').html(data);
            },

            error: PHPCensor.handleFailedAjax
        });
    },

    updateHeaderBuilds: function (data) {
        $('.app-pending-list').empty();
        $('.app-running-list').empty();

        if (!data.pending.count) {
            $('.app-pending').hide();
        } else {
            $('.app-pending').show();
            $('.app-pending .header').text(Lang.get('n_builds_pending', data.pending.count));

            $.each(data.pending.items, function (idx, build) {
                $('.app-pending-list').append(build.header_row);
            });
        }

        if (!data.running.count) {
            $('.app-running').hide();
        } else {
            $('.app-running').show();
            $('.app-running .header').text(Lang.get('n_builds_running', data.running.count));

            $.each(data.running.items, function (idx, build) {
                $('.app-running-list').append(build.header_row);
            });
        }

    },

    get: function (uri, success) {

        $.ajax({
            url: window.APP_URL + uri,

            success: function (data) {
                success();
            },

            error: PHPCensor.handleFailedAjax
        });
    },

    handleFailedAjax: function (xhr) {
        if (xhr.status == 401) {
            window.location.href = window.APP_URL + 'session/login';
        }
    }
};

PHPCensor.init();

function handleFailedAjax(xhr) {
    PHPCensor.handleFailedAjax(xhr);
}

/**
 * See https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Function/bind
 * for the details of code below
 */
if (!Function.prototype.bind) {
    Function.prototype.bind = function (oThis) {
        if (typeof this !== "function") {
            // closest thing possible to the ECMAScript 5 internal IsCallable function
            throw new TypeError("Function.prototype.bind - what is trying to be bound is not callable");
        }

        var aArgs = Array.prototype.slice.call(arguments, 1),
            fToBind = this,
            fNOP = function () {
            },
            fBound = function () {
                return fToBind.apply(this instanceof fNOP && oThis
                    ? this
                    : oThis,
                    aArgs.concat(Array.prototype.slice.call(arguments)));
            };

        fNOP.prototype = this.prototype;
        fBound.prototype = new fNOP();

        return fBound;
    };
}

/**
 * Used for delete buttons in the system, just to prevent accidental clicks.
 */
function confirmDelete(url, reloadAfter) {

    var dialog = new PHPCensorConfirmDialog({
        title: Lang.get('confirm_title'),
        message: Lang.get('confirm_message'),
        confirmBtnCaption: Lang.get('confirm_ok'),
        cancelBtnCaption: Lang.get('confirm_cancel'),
        /*
         confirm-btn click handler
         */
        confirmed: function (e) {
            var dialog = this;
            e.preventDefault();

            /*
             Call delete URL
             */
            $.ajax({
                url: url,
                success: function (data) {
                    if (reloadAfter) {
                        dialog.onClose = function () {
                            window.location.reload();
                        };
                    }

                    dialog.showStatusMessage(Lang.get('confirm_success'), 500);
                },
                error: function (data) {
                    dialog.showStatusMessage(Lang.get('confirm_failed') + data.statusText);

                    if (data.status == 401) {
                        handleFailedAjax(data);
                    }
                }
            });
        }
    });

    dialog.show();
    return dialog;
}

/**
 * PHPCensorConfirmDialog constructor options object
 * @type {{message: string, title: string, confirmBtnCaption: string, cancelBtnCaption: string, confirmed: Function}}
 */
var PHPCensorConfirmDialogOptions = {
    message: 'Are you sure?',
    title: 'Confirmation',
    confirmBtnCaption: 'Ok',
    cancelBtnCaption: 'Cancel',
    confirmed: function (e) {
        this.close();
    }
};

var PHPCensorConfirmDialog = Class.extend({
    /**
     * @private
     * @var {bool} Determines whether the dialog has been confirmed
     */
    confirmed: false,

    /**
     * @param {PHPCensorConfirmDialogOptions} options
     */
    init: function (options) {

        options = options ? $.extend(PHPCensorConfirmDialogOptions, options) : PHPCensorConfirmDialogOptions;

        if (!$('#confirm-dialog').length) {
            /*
             Add the dialog html to a page on first use. No need to have it there before first use.
             */
            $('body').append(
                '<div class="modal fade" id="confirm-dialog">'
                + '<div class="modal-dialog">'
                + '<div class="modal-content">'
                + '<div class="modal-header">'
                + '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>'
                + '<h4 class="modal-title"></h4>'
                + '</div>'
                + '<div class="modal-body">'
                + '<p></p>'
                + '</div>'
                + '<div class="modal-footer">'
                + '<button id="confirm-cancel" type="button" class="btn btn-default pull-left" data-dismiss="modal">Cancel</button>'
                + '<button id="confirm-ok" type="button" class="btn btn-danger"></button>'
                + '</div>'
                + '</div>'
                + '</div>'
                + '</div>'
            );
        }

        /*
         Define dialog controls
         */
        this.$dialog = $('#confirm-dialog');
        this.$cancelBtn = this.$dialog.find('#confirm-cancel');
        this.$confirmBtn = this.$dialog.find('#confirm-ok');
        this.$title = this.$dialog.find('h4.modal-title');
        this.$body = this.$dialog.find('div.modal-body');

        /*
         Initialize its values
         */
        this.$title.html(options.title ? options.title : PHPCensorConfirmDialogOptions.title);
        this.$body.html(options.message ? options.message : PHPCensorConfirmDialogOptions.message);
        this.$confirmBtn.html(
            options.confirmBtnCaption ? options.confirmBtnCaption : PHPCensorConfirmDialogOptions.confirmBtnCaption
        );

        this.$cancelBtn.html(
            options.cancelBtnCaption ? options.cancelBtnCaption : PHPCensorConfirmDialogOptions.cancelBtnCaption
        );

        /*
         Events
         */
        this.confirmBtnClick = options.confirmed;

        /*
         Re-bind handlers
         */
        this.$confirmBtn.unbind('click');
        this.$confirmBtn.click(this.onConfirm.bind(this));

        this.$confirmBtn.unbind('hidden.bs.modal');

        /*
         Bind the close event of the dialog to the set of onClose* methods
         */
        this.$dialog.on('hidden.bs.modal', function () {
            this.onClose()
        }.bind(this));
        this.$dialog.on('hidden.bs.modal', function () {
            if (this.confirmed) {
                this.onCloseConfirmed();
            } else {
                this.onCloseCanceled();
            }
        }.bind(this));

        /*
         Restore state if was changed previously
         */
        this.$cancelBtn.show();
        this.$confirmBtn.show();
        this.confirmed = false;
    },

    /**
     * Show dialog
     */
    show: function () {
        this.$dialog.modal('show');
    },

    /**
     * Hide dialog
     */
    close: function () {
        this.$dialog.modal('hide');
    },

    onConfirm: function (e) {
        this.confirmed = true;
        $(this).attr('disabled', 'disabled');
        this.confirmBtnClick(e);
    },

    /**
     * Called only when confirmed dialog was closed
     */
    onCloseConfirmed: function () {
    },

    /**
     * Called only when canceled dialog was closed
     */
    onCloseCanceled: function () {
    },

    /**
     * Called always when the dialog was closed
     */
    onClose: function () {
    },

    showStatusMessage: function (message, closeTimeout) {
        this.$confirmBtn.hide();
        this.$cancelBtn.hide();

        /*
         Status message
         */
        this.$body.html(message);

        if (closeTimeout) {
            window.setTimeout(function () {
                /*
                 Hide the dialog
                 */
                this.close();
            }.bind(this), closeTimeout);
        }
    }
});

/**
 * Used to initialise the project form:
 */
function setupProjectForm() {
    $('.github-container').hide();

    $('#element-reference').change(function () {
        var el = $(this);
        var val = el.val();
        var type = $('#element-type').val();
        var acceptable = {
            'github': {
                'ssh': /git\@github\.com\:([a-zA-Z0-9_\-]+\/[a-zA-Z0-9_\-]+)\.git/,
                'git': /git\:\/\/github.com\/([a-zA-Z0-9_\-]+\/[a-zA-Z0-9_\-]+)\.git/,
                'http': /https\:\/\/github\.com\/([a-zA-Z0-9_\-]+\/[a-zA-Z0-9_\-]+)(\.git)?/
            },
            'bitbucket': {
                'ssh': /git\@bitbucket\.org\:([a-zA-Z0-9_\-]+\/[a-zA-Z0-9_\-]+)\.git/,
                'http': /https\:\/\/[a-zA-Z0-9_\-]+\@bitbucket.org\/([a-zA-Z0-9_\-]+\/[a-zA-Z0-9_\-]+)\.git/,
                'anon': /https\:\/\/bitbucket.org\/([a-zA-Z0-9_\-]+\/[a-zA-Z0-9_\-]+)(\.git)?/
            }

        };

        if (acceptable[type] !== undefined) {
            for (var i in acceptable[type]) {
                if (val.match(acceptable[type][i])) {
                    el.val(val.replace(acceptable[type][i], '$1'));
                }
            }
        }
    });

    $('#element-type').change(function () {
        if ($(this).val() == 'github') {
            $('#loading').show();

            $.ajax({
                dataType: "json",
                url: window.APP_URL + 'project/ajax-github-repositories',
                success: function (data) {
                    $('#loading').hide();

                    if (data && data.repos) {
                        $('#element-github').empty();

                        for (var i in data.repos) {
                            var name = data.repos[i];
                            $('#element-github').append($('<option></option>').text(name).val(name));
                        }

                        $('.github-container').slideDown();
                    }
                },
                error: handleFailedAjax
            });
        } else {
            $('.github-container').slideUp();
        }
        $('#element-reference').trigger('change');
    });

    $('#element-github').change(function () {
        var val = $('#element-github').val();

        if (val != 'choose') {
            $('#element-type').val('github');
            $('#element-reference').val(val);

            $('label[for=element-reference]').hide();
            $('label[for=element-type]').hide();
            $('#element-reference').hide();
            $('#element-token').val(window.github_token);
            $('#element-title').val(val);
        } else {
            $('label[for=element-reference]').show();
            $('label[for=element-type]').show();
            $('#element-reference').show();
            $('#element-type').show();
            $('#element-reference').val('');
            $('#element-token').val('');
        }
    });
}

var Lang = {
    get: function () {
        var args = Array.prototype.slice.call(arguments);
        ;
        var string = args.shift();

        if (STRINGS[string]) {
            args.unshift(STRINGS[string]);
            return sprintf.apply(sprintf[0], args);
        }

        return string;
    }
};
