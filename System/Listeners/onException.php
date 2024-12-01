<?php

use Zanzara\Context;

defined('BOTPOLL') or die('No direct access');

/* Ловим ошибки в лог */
$bot->onException(function (Context $ctx, $exception) {
    
    global $logger;

    echo $exception;
    $logger->error($exception);
});