<?php
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME']??'')==='bootstrap.php') { http_response_code(404); exit; }
define('AV7_BOOTSTRAP',true);
ini_set('display_errors','0');
require __DIR__.'/security.php';
require __DIR__.'/store.php';
require __DIR__.'/view.php';
av7_headers();
try {
    if (is_file(dirname(__DIR__).'/PAUSED.flag')) throw new Av7Error(503,'This isolated authentication lab has been paused. Earlier stages are unchanged.');
    $GLOBALS['av7_config']=require dirname(__DIR__).'/config.php';
    av7_request_guard($GLOBALS['av7_config']);
    av7_prepare($GLOBALS['av7_config']);
    av7_start_session($GLOBALS['av7_config']);
} catch (Av7Error $e) { av7_fail($e->status,$e->getMessage()); }
catch (Throwable $e) { av7_fail(503,'The isolated authentication lab is unavailable. No production data is connected.'); }
