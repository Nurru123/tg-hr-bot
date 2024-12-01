<?php
/* почти полная копия onMessage.php */

use Zanzara\Context;

defined('BOTPOLL') or die('No direct access');

$bot->onReplyToMessage(function (Context $ctx) {

    global $db, $logger, $kb_start;

    $chat_id = $ctx->getEffectiveChat()->getId();
    $chat_type = $ctx->getEffectiveChat()->getType();
    $messageObj = $ctx->getMessage();

    /* Выходим из всех групп, супергрупп, если это не админский (антиспам) */
    if ($messageObj && $messageObj->getNewChatMembers()) {
        $newChatMembers = $messageObj->getNewChatMembers();
        foreach ($newChatMembers as $newChatMember) {
            if ($newChatMember->getId() == BOT_USER_ID && $chat_type != "private" && $chat_id != ADMINS_CHAT) {
                $ctx->leaveChat($chat_id);
                $logger->info("Bot was added to the chat $chat_id and has left it.");
                exit;
            }
        }
    }

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

    /* Получаем данные о сообщении от Телеграма */
    $message = BotHandler::getTgMessageData($messageObj);
    $message['status'] = $db->getMessageStatus($message['id']);

    if ($user['isBot'] || $user['isBlocked']) { //если нам пишет бот или забаненый пользователь, ничего не делаем
        $logger->info("Other bot or banned user has tried to interact with the bot. Id: " . $user['id']);
        exit;
    } else {
        $db->saveMessage($user['id'], $chat_id, $message); //сохраняем сообщение в бд

        if ($user['status'] == '' && $message['text'] != "/start") {
            $db->setMessageStatus($message['id'], 'declined');
            $ctx->sendMessage('Запустите бота, чтобы начать - /start');
            exit;
        }

        if ($message['type'] != "bot_command") {

            /* получаем текущий шаг */
            $cur_step = $db->getStep($user['status']);
            if (!$cur_step) {
                $logger->error("Error with getting Step from DB");
                exit;
            } else {
                $next_step = $db->getStep($cur_step['next']); // следующий шаг
                $keyboard = (($next_step['keyboard']) ? unserialize($next_step['keyboard']) : []); //кнопки, если есть

                if ($user['status'] == 20 && !empty(BotHandler::getCitiesKeyboard())) { //если вопрос предыдущий вопросу про город, получаем список региональных партнеров и записываем их в клавиатуру
                    $keyboard = BotHandler::getCitiesKeyboard();
                }
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
                    $kb = $res['kb'] ?? [];
                    $db->setMessageStatus($message['id'], 'declined');
                    $ctx->sendMessage($res['text'], $kb); // выводим текст ошибки
                    exit;
                }
            }

            if ($message['media_group_id']) { // если медиагруппа, по-умолчанию все отклоняем
                $db->exitIfMediaGroupAnswered($user['id'], $message['media_group_id']);
                $db->setMessageStatus($message['id'], 'declined');
            }

            if ($user['status'] == 70 || $user['status'] == 75) { // шаги с загрузкой фото

                if ($message['photo'] && !$message['media_group_id']) {
                    $photo_path = BotHandler::savePhoto($message['photo']); // сохраняем картинку
                    $db->savePhotoPath($user['id'], $message['id'], $photo_path); // сохраняем на сервер путь
                    $message['text'] = BOT_URL . $photo_path;
                } else {
                    $db->setMessageStatus($message['id'], 'declined');
                    $kb = [
                        'reply_markup' =>
                        ['inline_keyboard' => [
                            [['callback_data' => 'skip', 'text' => 'Пропустить']],
                        ], 'resize_keyboard' => true]
                    ];
                    $ctx->sendMessage('Пожалуйста, приложите одно изображение или нажмите кнопку «Пропустить»', $kb);
                    exit;
                }
            }

            if ($user['status'] == 41 && $ctx->getMessage()->getContact()) { //если ждем шер контакта по кнопке
                $message['text'] = $ctx->getMessage()->getContact()->getPhoneNumber();
            }

            $db->saveAnswer($user['id'], $cur_step['title'], $message['date'], $message['text']); //сохраняем ответ в бд
            $db->setUserStatus($user['id'], $cur_step['next']); // назначаем следующий по порядку шаг

            // чтобы не спрашивать бесконечно телефон и почту
            if (($user['status'] == 41 && !empty($db->getAnswer($user['id'], 'title', 'Электронный адрес'))) || ($user['status'] == 45 && !empty($db->getAnswer($user['id'], 'title', 'Телефон')))) {
                $db->setUserStatus($user['id'], 70); // перекидыаем на конкретный шаг
                $next_step = $db->getStep(70);
                $keyboard = (($next_step['keyboard']) ? unserialize($next_step['keyboard']) : []);
            }

            if ($next_step && !$last_step) {

                $answers = [];

                $photos = $db->getPhotos($user['id']); // считаем кол-во сохраненных фоток
                if (count($photos) == 5) { // 5 и хватит
                    $db->setUserStatus($user['id'], 80); // перекидыаем на конкретный шаг
                    $next_step = $db->getStep(80);
                    $keyboard = (($next_step['keyboard']) ? unserialize($next_step['keyboard']) : []);
                }

                if ($next_step['next'] === 0) { // если предпоследний шаг

                    $answers = $db->getAllAnswers($user['id']); // получаем все ответы

                    if ($answers['is_duplicate'] && $next_step['step_id'] == 80) {
                        $db->setUserStatus($user['id'], 140);
                        $next_step = $db->getStep(140);
                        $keyboard = (($next_step['keyboard']) ? unserialize($next_step['keyboard']) : []);
                    } else {
                        $db->saveFeedback($answers); //сохраняем в бд
                        BotHandler::sendEmail($answers); // пуляем емейл
                        BotHandler::saveCsv($answers); // сохраняем в csv
                    }

                    $next_step['text'] = str_replace('%NUM%', $answers['number'], $next_step['text']); // подставляем номер обращения
                }

                /* выводим текст из шага, если не последнее сообщение */
                $ctx->sendMessage($next_step['text'], $keyboard);
            }

            if ($last_step) {
                $db->setMessageStatus($message['id'], 'declined');
                $ctx->sendMessage('Ваше обращение уже принято. Если хотите отправить ещё одно обращение, нажмите /start', $kb_start);
                exit;
            }
            
        } else if ($message['type'] == "bot_command" && $message['text'] != "/start") {

            $db->setMessageStatus($message['id'], 'declined');
            $ctx->sendMessage("Я не знаю такой команды. Знаю /start 😊", ['protect_content' => true]);
            exit;
        }
    }
});
