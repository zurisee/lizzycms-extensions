<?php

define('SYSTEM_PATH', '../../../');
define('PATH_TO_APP_ROOT', SYSTEM_PATH.'../');
define('URL_FILE', PATH_TO_APP_ROOT.'.#cache/curr_slide.url');


//session_start();

if ($el = get_post_data('el')) {
//if ($el = getUrlArg('el', true)) {
    session_start();
    $_SESSION['lizzy']['slideshow-el'] = $el;
    session_write_close();
    exit('ok');
}

if (!isset($_POST['last'])) {
    exit('nok: timestamp missing');
}
$lastUpdate = intval($_POST['last']);
if ($lastUpdate == 0) { // initialization:
    $lastUpdate = time();
}

for ($i=0; $i<40000; $i++) {
    clearstatcache();
    $fTime = filemtime(URL_FILE);

    if ($fTime > $lastUpdate) {
        $url = trim(file_get_contents(URL_FILE));
        exit("load: $url [$fTime]");
    }
    usleep(250);
}
//sleep(5);
exit("ok [$lastUpdate]");
//$out = 'ok';
//exit($out.' ['.time().']');


//if (isset($_GET['save'])) {
//    echo $backend->saveNewData($_POST);
//    exit;
//}


//exit( $backend->getData() );


