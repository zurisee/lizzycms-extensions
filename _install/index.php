<?php

define('LIZZY_SOURCE_URL', 'https://codeload.github.com/zurisee/lizzycms/zip/master');
define('LIZZY_ZIP_FILE', 'lizzy-master.zip');
define('LIZZY_MASTER_PATH', 'lizzycms-master');

if (!file_exists(LIZZY_ZIP_FILE)) {
	downloadLizzy();
}

if (!file_exists('_lizzy')) {
    unpackLizzy();
}

installLizzy();

header("Location: ./");
exit;


function unpackLizzy()
{
    $zip = new ZipArchive;
    $zip->open(LIZZY_ZIP_FILE);
    $zip->extractTo('./');
    $zip->close();
    rename(LIZZY_MASTER_PATH, '_lizzy');
    unlink(LIZZY_ZIP_FILE);
}



function installLizzy()
{
    copyFolder('_lizzy/_parent-folder/', '.');
    rename('htaccess','.htaccess');
}


function downloadLizzy()
{
	$fp = fopen(LIZZY_ZIP_FILE, 'w+');
	if($fp === false){
		throw new Exception('Could not open: ' . LIZZY_ZIP_FILE);
	}
	$ch = curl_init( LIZZY_SOURCE_URL );
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_exec($ch);
	if(curl_errno($ch)){
		throw new Exception(curl_error($ch));
	}
	$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	fclose($fp);

	if($statusCode !== 200){
		exit ("Status Code: " . $statusCode);
	}
}




function copyFolder( $source, $target ) {
    @mkdir( $target );
    $d = dir( $source );
    while ( FALSE !== ( $entry = $d->read() ) ) {
        if ( $entry == '.' || $entry == '..' ) {
            continue;
        }
        $Entry = $source . '/' . $entry;
        if ( is_dir( $Entry ) ) {
            copyFolder( $Entry, $target . '/' . $entry );
            continue;
        }
        copy( $Entry, $target . '/' . $entry );
    }
    $d->close();
}

