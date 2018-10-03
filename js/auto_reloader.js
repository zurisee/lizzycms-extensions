/*
    auto_reloader.js

    checks server for changes and initiates reload if necessary
 */


var backend = systemPath + '_reload_server.php';
var loadTime = Math.floor(Date.now() / 1000);

monitor();

function monitor()
{
    $.ajax({
        url: backend + "?" + loadTime,
        cache: false,
        success: function (str) {
            console.log('monitoring response: ' + str);
            if (str == 'reload') {
                lzyReload();
            } else {
                console.log('auto-reload: restart monitor');
                setTimeout( monitor, 2000);
            }
        }
    });

}