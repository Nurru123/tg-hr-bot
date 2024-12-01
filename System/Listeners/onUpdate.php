<?php

use Zanzara\Context;

defined('BOTPOLL') or die('No direct access');

$bot->onUpdate(function (Context $ctx) {

    global $db, $logger;

    $chat_id = $ctx->getEffectiveChat()->getId();
    $chat_type = $ctx->getEffectiveChat()->getType();

    /* Если это не приватный чат, ничего не делаем */
    if ($chat_type != 'private') {
        $logger->DEBUG("Bot has tried to proccess smth in the group chat: $chat_id.");
        exit;
    }

    /* Получаем данные о пользователе от Телеграма */
    $user = BotHandler::getTgUserData($ctx);

    /* Если юзер новый, добавляем в базу */
    $db->createOrUpdateUser($user);
    $user['isBlocked'] = $db->getUserIsBlocked($user['id']);
    $user['status'] = $db->getUserStatus($user['id']);

    /* проверяем кол-во сообщений от юзера за последнюю минуту */
    $countUserMessages = $db->getMessagesForMin($user['id']);
    if ($countUserMessages && $countUserMessages > 20) { // блокируем, если больше 20 (медиагруппа считается за 1)
        $ctx->sendMessage("Вы были заблокированы, тк отправили слишком много сообщений за минуту");
        $db->setUserIsBlocked($user['id'], 1);
        $logger->warning("User has been blocked. id: " . $user['id']); //делаем пометку в лог
    }

    /* записываем сообщения в лог */
    $data = [];
    if ($ctx->getMessage() !== null) {
        $res = (array) $ctx->getMessage();
        $data['MESSAGE'] = array_filter($res);
    }
    if ($ctx->getCallbackQuery() !== null) {
        $res = (array) $ctx->getCallbackQuery()->getMessage();
        $data['CALLBACK_QUERY'] = array_filter($res);
        $data['CALLBACK_QUERY_DATA'] = $ctx->getCallbackQuery()->getData();
    }
    if (!empty($data)) {
        BotHandler::writeLogFile($data, $user['id']);
    }
});