<?php

use Zanzara\Context;
use Zanzara\Telegram\Type\Response\TelegramException;
use \Database\DatabaseConnection as DB;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class BotHandler
{

    /**
     * логируем активность
     * @param $string
     * @param $user_id
     * @param $clear
     * @return bool
     */
    public static function writeLogFile($data, $user_id, $clear = false)
    {
        $log_base_path = LOG_PATH . 'chat_logs/';

        $log_user_folder = $log_base_path . $user_id;
        $log_year_folder = $log_user_folder . '/' . date("Y");
        $log_month_folder = $log_year_folder . '/' . date("m");

        if (!file_exists($log_base_path)) mkdir($log_base_path, 0755, true);
        if (!file_exists($log_user_folder)) mkdir($log_user_folder, 0755, true);
        if (!file_exists($log_year_folder)) mkdir($log_year_folder, 0755, true);
        if (!file_exists($log_month_folder)) mkdir($log_month_folder, 0755, true);

        $log_path = $log_month_folder . '/';
        $log_file = $log_path . $user_id . "_messages_" . date('Y-m-d') . ".log";

        $now = date("Y-m-d H:i:s");
        if ($clear == false) {
            file_put_contents($log_file, $now . " " . print_r($data, true) . "\r\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, '');
            file_put_contents($log_file, $now . " " . print_r($data, true) . "\r\n", FILE_APPEND);
        }
    }

    /**
     * @param Context $ctx
     * @return array
     */
    public static function getTgUserData(Context $ctx)
    {
        $user = [];
        $user['id'] = $ctx->getEffectiveUser()->getId();
        $user['userName'] = $ctx->getEffectiveUser()->getUsername();
        $user['firstName'] = $ctx->getEffectiveUser()->getFirstName();
        $user['lastName'] = $ctx->getEffectiveUser()->getLastName();
        $user['isBot'] = $ctx->getEffectiveUser()->isBot() ? 1 : 0;
        if ($ctx->getMessage()) {
            $user['last_message_time'] = $ctx->getMessage()->getDate();
        } elseif ($ctx->getCallbackQuery()) {
            $user['last_message_time'] = $ctx->getCallbackQuery()->getMessage()->getDate();
        }

        return $user;
    }

    /**
     * @param $messageObj
     * @return array
     */
    public static function getTgMessageData($messageObj)
    {
        $message = [];
        $message['date'] = $messageObj->getDate();
        $message['id'] = $messageObj->getMessageId();
        $message['text'] = 'текста нет';
        if ($messageObj->getText()) {
            $message['text'] = $messageObj->getText();
        } elseif ($messageObj->getCaption()) {
            $message['text'] = $messageObj->getCaption();
        } elseif ($messageObj->getContact()) {
            $message['text'] = $messageObj->getContact()->getPhoneNumber();
        }
        $message['type'] = '';
        if (!empty($messageObj->getEntities())) {
            $message['type'] = $messageObj->getEntities()[0]->getType();
        }
        $message['media_group_id'] = 0;
        if ($messageObj->getMediaGroupId()) {
            $message['media_group_id'] = $messageObj->getMediaGroupId();
        }
        $message['photo'] = '';
        if ($messageObj->getPhoto()) {
            $message['photo'] = $messageObj->getPhoto();
        }

        return $message;
    }

    /**
     * имя (кириллица/латиница без спецсимволов и цифр)
     * @param Context $ctx
     * @return array
     */
    public static function getName(Context $ctx)
    {
        if ($ctx->getMessage() && $ctx->getMessage()->getText() && !is_numeric($ctx->getMessage()->getText())) { //тут какая-то проверка
            $message = $ctx->getMessage()->getText();
            if (preg_match('/^[a-zA-Zа-яА-Я\s\-—]{1,128}$/u', $message)) {
                return ['status' => true, 'text' => ''];
            } else {
                return ['status' => false, 'text' => 'Текст может быть до 128 символов и содержать кириллицу, латиницу, пробелы и дефис. Попробуйте еще раз или начните сначала - /start'];
            }
        } else {
            return ['status' => false, 'text' => 'Введите ваше имя и фамилию или начните сначала - /start'];
        }
    }

    /**
     * текст любой
     * @param Context $ctx
     * @return array
     */
    public static function getAnyText(Context $ctx)
    {
        if ($ctx->getMessage() && $ctx->getMessage()->getText()) {
            $message = $ctx->getMessage()->getText();
            return ['status' => true, 'text' => ''];
        } else {
            return ['status' => false, 'text' => 'Введите ответ или начните сначала - /start'];
        }
    }

    /**
     * текст кириллица и цифры
     * @param Context $ctx
     * @return array
     */
    public static function getRusText(Context $ctx)
    {
        if ($ctx->getMessage() && $ctx->getMessage()->getText()) {
            $message = $ctx->getMessage()->getText();
            if (preg_match('/^[а-яА-Я0-9\s\-—.,]+$/u', $message)) {
                return ['status' => true, 'text' => ''];
            } else {
                return ['status' => false, 'text' => 'Текст может содержать кириллицу, цифры, точки, запятые, пробелы и дефис. Попробуйте еще раз или начните сначала - /start'];
            }
        } else {
            return ['status' => false, 'text' => 'Введите ответ или начните сначала - /start'];
        }
    }

    /**
     * значение кнопки или команда старт
     * @param Context $ctx
     * @return array
     */
    public static function getBtnValue(Context $ctx)
    {
        if ($ctx->getCallbackQuery() || ($ctx->getMessage() && $ctx->getMessage()->getText() === '/start')) {
            return ['status' => true, 'text' => ''];
        } else {
            return ['status' => false, 'text' => 'Нажмите кнопку или начните сначала - /start'];
        }
    }

    /**
     * валидация номера телефона
     * @param Context $ctx
     * @return array
     */
    public static function getPhone(Context $ctx)
    {
        if ($ctx->getMessage()) {
            if ($ctx->getMessage()->getText()) {
                $phone = $ctx->getMessage()->getText();
                if (preg_match('/^\+?\d{10,20}$/sD', preg_replace('/[^\+0-9]/', '', $phone))) {
                    $phone = preg_replace('/[^\+0-9]/', '', $phone);
                    return ['status' => true, 'text' => ''];
                } else {
                    return ['status' => false, 'text' => 'Проверьте корректность ввода номера. Пример: 8 (915) 461-00-99'];
                }
            } elseif ($ctx->getMessage()->getContact()) {
                // $contact = $ctx->getMessage()->getContact()->getPhoneNumber();
                return ['status' => true, 'text' => ''];
            } else {
                return ['status' => false, 'text' => 'Проверьте корректность ввода номера. Пример: 8 (915) 461-00-99'];
            }
        } else {
            return ['status' => false, 'text' => 'Проверьте корректность ввода номера. Пример: 8 (915) 461-00-99'];
        }
    }

    /**
     * валидация электронной почты
     * @param Context $ctx
     * @return array
     */
    public static function getEmail(Context $ctx)
    {
        if ($ctx->getMessage()) {
            if ($ctx->getMessage()->getText()) {
                $email = $ctx->getMessage()->getText();
                if (function_exists('filter_var') && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return ['status' => true, 'text' => ''];
                } else {
                    return ['status' => false, 'text' => 'Проверьте корректность ввода адреса и попробуйте еще раз.'];
                }
            } else {
                return ['status' => false, 'text' => 'Проверьте корректность ввода адреса и попробуйте еще раз.'];
            }
        } else {
            return ['status' => false, 'text' => 'Проверьте корректность ввода адреса и попробуйте еще раз.'];
        }
    }

    /**
     * валидация номера телефона или почты
     * @param Context $ctx
     * @return array
     */
    public static function getPhoneOrEmail(Context $ctx)
    {
        if ($ctx->getMessage() && $ctx->getMessage()->getText()) {
            $answer = $ctx->getMessage()->getText();
            if (preg_match('/^\+?\d?9{1}\d{9}$/sD', (preg_replace('/[^\+0-9]/', '', $answer)))) {
                // $answer = preg_replace('/[^\+0-9]/', '', $answer);
                return ['status' => true, 'text' => ''];
            } else if (function_exists('filter_var') && filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                return ['status' => true, 'text' => ''];
            } else {
                return ['status' => false, 'text' => 'Данные введены некорректно. Попробуйте ещё раз.'];
            }
        } else {
            return ['status' => false, 'text' => 'Напишите телефон или электронную почту или начните сначала - /start'];
        }
    }

    /**
     * значение кнопки или команда старт
     * @param Context $ctx
     * @return array
     */
    public static function getFeedbackNum(Context $ctx)
    {
        global $db;

        $kb = [
            'reply_markup' =>
            ['inline_keyboard' => [
                [['callback_data' => 'num_lost', 'text' => 'Я не помню номер']],
            ], 'resize_keyboard' => true]
        ];

        if ($ctx->getCallbackQuery() && $ctx->getCallbackQuery()->getData() == 'num_lost') {
            return ['status' => true, 'text' => ''];
        }

        if ($ctx->getMessage()) {
            if ($ctx->getMessage()->getText()) {
                $num = $ctx->getMessage()->getText();
                if (preg_match('/^\d{10}$/', $num)) {
                    if ($db->getFeedbackByNumber($num)) {
                        $db->setReportUser($num);
                        return ['status' => true, 'text' => ''];
                    } else {
                        return ['status' => false, 'text' => 'Обращения с таким номером не существует. Проверьте корректность ввода номера и попробуйте еще раз.', 'kb' => $kb];
                    }
                } else {
                    return ['status' => false, 'text' => 'Проверьте корректность ввода номера обращения и попробуйте еще раз.', 'kb' => $kb];
                }
            } else {
                return ['status' => false, 'text' => 'Проверьте корректность ввода номера обращения и попробуйте еще раз.', 'kb' => $kb];
            }
        } else {
            return ['status' => false, 'text' => 'Проверьте корректность ввода номера обращения и попробуйте еще раз.', 'kb' => $kb];
        }
    }

    /**
     * общая функция загрузки картинки
     * @param $data
     * @return mixed
     */
    public static function savePhoto($data)
    {
        $file_id = $data[count($data) - 1]->getFileId(); // берем последнюю картинку в массиве (оригинальный размер)
        $file_path = self::getPhotoPath($file_id); // получаем путь до картинки 

        return self::copyPhoto($file_path); // копируем картинку на сервер
    }

    /**
     * функция получения местонахождения файла
     * @param $file_id
     * @return string
     */
    public static function getPhotoPath($file_id)
    {
        $url = "https://api.telegram.org/bot" . TOKEN . "/getFile?file_id=$file_id";
        $data = json_decode(file_get_contents($url), true);

        return $data['result']['file_path'];
    }

    /** 
     * копируем фото к себе
     * @param $file_path
     * @return string
     */
    public static function copyPhoto($file_path)
    {
        $file_from_tgrm = "https://api.telegram.org/file/bot" . TOKEN . "/" . $file_path; // ссылка на файл в телеграме

        $tmp = explode('.', $file_path); // достаем расширение файла
        $ext = end($tmp);
        $file_name = date('Y_m_d_H_i_s') . "." . $ext;

        $photo_path = "upload/img/" . $file_name;
        copy($file_from_tgrm, $photo_path); // копируем файл на сервер

        return $photo_path; //возвращаем адрес картинки на сервере
    }

    /**
     * запись лога в csv
     * @param $data
     * @return mixed
     */
    public static function saveCsv($data)
    {
        global $logger;

        $csv_folder = LOG_PATH . "/csv";
        if (!file_exists($csv_folder)) mkdir($csv_folder, 0755, true);

        if ($data) {
            $csv_file = $csv_folder . '/archive.csv';
            if (file_exists($csv_file)) {
                $id = count(file($csv_file, FILE_SKIP_EMPTY_LINES)) + 1;
            } else {
                $id = 1;
            }

            $data_fixed = '';
            foreach ($data['data'] as $item) {
                $data_fixed .= $item['title'] . ': ' . $item['text'] . ' | ';
            }

            $csv_arr = array(
                $id,
                $data['number'],
                $data['user_id'],
                $data['time'],
                $data_fixed
            );
            $file = fopen($csv_file, 'a+');
            if (fputcsv($file, $csv_arr)) {
                $logger->info('CSV success');
            } else {
                $logger->error('CSV unsuccess');
            }
            @fclose($file);
        }
    }

    /** 
     * получаем города
     * @return array
     */
    public static function getCities()
    {
        if ($regionJson = file_get_contents(REGION_JSON_PATH)) {
            return json_decode($regionJson, TRUE);
        }
    }

    /** 
     * собираем города в кнопки
     * @return array
     */
    public static function getCitiesKeyboard()
    {
        $regionList = self::getCities();

        $keyboard = [
            'reply_markup' =>
            ['inline_keyboard' => [], 'resize_keyboard' => true]
        ];

        foreach ($regionList['REGION'] as $key => $region) {

            $button = [
                'callback_data' => $region['NAME'],
                'text' => $region['NAME']
            ];

            $buttons_row[] = $button;

            if (count($buttons_row) == 2) { // по 2 кнопки в ряду и хватит
                $keyboard['reply_markup']['inline_keyboard'][] = $buttons_row;
                $buttons_row = [];
            }
            if (count($regionList['REGION']) % 2 !== 0 && count($regionList['REGION']) - 1 === $key) { // если регионов нечетное количество, рисуем последнюю кнопку одну в ряду
                $keyboard['reply_markup']['inline_keyboard'][] = $buttons_row;
                $buttons_row = [];
            }
        }

        if (!empty($keyboard['reply_markup']['inline_keyboard'])) {
            return $keyboard;
        } else {
            return [];
        }
    }

    /** 
     * функция пересылки обращения на email
     */
    public static function sendEmail($answers, $to_bosses = false)
    {
        global $logger;

        $feedback_num = $answers['number'];

        /* Passing TRUE to the constructor enables exceptions. */
        $mail = new PHPMailer(TRUE);
        $mailText = 'ОБРАЩЕНИЕ ИЗ ТЕЛЕГРАМ БОТА' . ($answers["is_duplicate"] ? ' (ПОВТОРНОЕ)' : '')
            . '<br />Номер обращения: ' . $feedback_num
            . '<br />id пользователя: ' . $answers["user_id"]
            . '<br />Дата: ' . $answers["time"];
        if (is_array($answers["data"]) && count($answers["data"])) {
            foreach ($answers["data"] as $attr) {
                $mailText .= '<br />' . $attr['title'] . ': ' . $attr['text'];
            }
        }
        if ($to_bosses && !$answers["is_duplicate"]) {
            $mailText = "Добрый день,<br /> Уведомляем вас, что ответственный специалист не связался в течение суток по заявке $feedback_num"
                . "<br />Данные заявки:"
                . '<br />Номер обращения: ' . $feedback_num
                . '<br />id пользователя: ' . $answers["user_id"]
                . '<br />Дата: ' . $answers["time"];
            if (!empty($answers["data"])) {
                $mailText .= '<br />' . $answers['data'];
            }
        }

        try {
            $mail->setFrom(MAIL_DATA['from'], '');
            $emails_to = MAIL_DATA['to'];
            if ($to_bosses && !$answers["is_duplicate"]) {
                $emails_to = MAIL_DATA['to_bosses'];
            }
            if (count($emails_to)) {
                foreach ($emails_to as $email) {
                    $mail->addAddress($email);
                }
            }

            $mail->Subject = 'ОБРАЩЕНИЕ ИЗ ТЕЛЕГРАМ БОТА #' . $feedback_num . ($answers["is_duplicate"] ? ' (ПОВТОРНОЕ)' : '');
            if ($to_bosses && !$answers["is_duplicate"]) {
                $mail->Subject = "Не было связи по заявке от HR бота $feedback_num";
            }

            $mail->isHTML(TRUE);
            $mail->CharSet = MAIL_DATA['charset'];
            $mail->Body = $mailText;

            /* SMTP parameters. */
            $mail->isSMTP();
            $mail->Host = MAIL_DATA['host'];
            $mail->SMTPAuth = MAIL_DATA['SMTPAuth'];
            $mail->SMTPSecure = MAIL_DATA['SMTPSecure'];
            $mail->Username = MAIL_DATA['username'];
            $mail->Password = MAIL_DATA['pass'];
            $mail->Port = MAIL_DATA['port'];

            $mail->send();

            $logger->info("Email was sent successfully - $feedback_num");
        } catch (Exception $e) {
            /* PHPMailer exception. */
            $logger->error($e->errorMessage());
        } catch (\Exception $e) {
            /* PHP exception (note the backslash to select the global namespace Exception class). */
            $logger->error($e->getMessage());
        }
    }
}
