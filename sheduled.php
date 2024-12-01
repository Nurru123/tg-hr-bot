<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use \Monolog\Formatter\LineFormatter;
use Zanzara\Zanzara;
use Zanzara\Context;
use Zanzara\Config;
use Zanzara\Message;
use Zanzara\Contact;
use Zanzara\User;
use Zanzara\Telegram\Type\Response\TelegramException;
use Zanzara\Telegram\Type\Webhook\WebhookInfo;

use \Database\DatabaseConnection as DB;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

defined('BOTPOLL') or define('BOTPOLL', TRUE);

define('POLLDIR', str_replace('\\', '/', realpath(__DIR__) . DIRECTORY_SEPARATOR));

if (file_exists(POLLDIR . 'config.php')) {
    include POLLDIR . 'config.php';
    // include POLLDIR . 'steps.php';
} else {
    die('No configuration file systems! Create the settings.');
}

require SYSTEM . 'Startup.php';
require SYSTEM . 'libs/vendor/autoload.php';

$log_year_folder = LOG_PATH . '/' . date("Y");
$log_month_folder = $log_year_folder . '/' . date("m");

if (!file_exists($log_year_folder)) mkdir($log_year_folder, 0755, true);
if (!file_exists($log_month_folder)) mkdir($log_month_folder, 0755, true);

$log_path = $log_month_folder . '/';
$log_file = $log_path . "log_" . date('Y-m-d') . ".log";

$logger = new Logger('logger'); // инициализация логгирования
$debugHandler = new StreamHandler($log_file, Logger::DEBUG);
$formatter = new LineFormatter(null, null, false, true);
$debugHandler->setFormatter($formatter);
$logger->pushHandler($debugHandler);

$config = new Config();
$config->setLogger($logger);
$config->setParseMode(Config::PARSE_MODE_HTML);
$config->setUpdateMode(Config::WEBHOOK_MODE);

$bot = new Zanzara(TOKEN, $config);
$telegram = $bot->getTelegram();

$db = new DB();

// $telegram->sendMessage('3 минуты', ['chat_id' => '241387392']);

$feedbacks = $db->getFeedbacks(24, true);

if ($feedbacks && count($feedbacks)) {

    foreach ($feedbacks as $feedback) {

        $feedback_num = $feedback['number'];
        $bot_user_name = BOT_USER_NAME;
        $opt = [
            'chat_id' => $feedback['user_id'],
            'reply_markup' =>
            ['inline_keyboard' => [
                [
                    ['callback_data' => "yes-$feedback_num", 'text' => 'Да'],
                    ['callback_data' => "no-$feedback_num", 'text' => 'Нет']
                ],
            ], 'resize_keyboard' => true]
        ];

        $db->setReportAuto($feedback_num); // устанавливаем флаг, что была связь от бота

        $telegram->sendMessage("Добрый день! Подскажите, с вами уже связались по вашему обращению номер $feedback_num в бот $bot_user_name?", $opt);
    }

    $logger->info("Found " . count($feedbacks) . " unresolved feedbacks (auto report)");
} else {
    $logger->info("Non of unresolved feedbacks were found (auto report)");
}

$bot->run();
