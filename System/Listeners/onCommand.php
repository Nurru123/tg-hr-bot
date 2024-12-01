<?php

use Zanzara\Context;

defined('BOTPOLL') or die('No direct access');

$bot->onCommand('start', function (Context $ctx) {

    global $db;
    global $logger;

    $messageObj = $ctx->getMessage();

    /* Получаем данные о пользователе от Телеграма */
    $user = BotHandler::getTgUserData($ctx);

    /* Если юзер новый, добавляем в базу */
    $db->createOrUpdateUser($user);
    $user['isBlocked'] = $db->getUserIsBlocked($user['id']);
    $user['status'] = 0; // задаем строго, тк это самое начало

    /* Получаем данные о сообщении от Телеграма */
    $message = BotHandler::getTgMessageData($messageObj);
    $message['status'] = $db->getMessageStatus($message['id']);

    if ($user['isBot'] || $user['isBlocked']) { //если нам пишет бот или забаненый пользователь, ничего не делаем
        $logger->info("Other bot or banned user has tried to interact with the bot. Id: " . $user['id']);
        exit;
    } else {
        /* получаем текущий шаг */
        $cur_step = $db->getStep($user['status']);
        if (!$cur_step) {
            $logger->error("Error with getting Step from DB");
            exit;
        }
        $last_step = $cur_step['next'] === 0 ? true : false;

        /* находим функцию-обработчик, привязанную к текущему шагу */
        $obj = new BotHandler();
        $callback = [];
        if ($cur_step['callback'] && is_callable(array($obj, $cur_step['callback']))) { // находим функцию-обработчик, привязанную к текущему шагу
            $callback = array($obj, $cur_step['callback']);
        }

        if (!empty($callback)) { // выполняем привязанный к шагу коллбэк
            $res = call_user_func($callback, $ctx);
            if (!$res['status']) {
                $db->setMessageStatus($message['id'], 'declined');
                $ctx->sendMessage($res['text']); // выводим текст ошибки
                exit;
            }
        }

        $db->deleteAllAnswers($user['id']); // удаляем все предыдущие сохраненные ответы
        $db->setUserStatus($user['id'], 0); // задаем строго, тк это самое начало

        if (!$last_step) { // выводим текст из шага, если не последнее сообщение
            $ctx->sendMessage($cur_step['text'], ($cur_step['keyboard'] ? unserialize($cur_step['keyboard']) : []), ['protect_content' => true, 'link_preview_options' => ['is_disabled' => true]]);
        }
    }
});