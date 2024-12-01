<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use \Monolog\Formatter\LineFormatter;
use Zanzara\Zanzara;
use Zanzara\Context;
use Zanzara\Config;
use Zanzara\Message;
use Zanzara\Contact;
use Zanzara\Telegram\Type\Response\TelegramException;
use Zanzara\Telegram\Type\Webhook\WebhookInfo;

use \Database\DatabaseConnection as DB;

defined('BOTPOLL') or define('BOTPOLL', TRUE);

define('POLLDIR', str_replace('\\', '/', realpath(__DIR__) . DIRECTORY_SEPARATOR));

if (file_exists(POLLDIR . 'config.php')) {
    include POLLDIR . 'config.php';
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

$telegram->getWebhookInfo()->then(
    function (WebhookInfo $webhookInfo) use ($telegram) {
        if (!$webhookInfo->getUrl()) {
            $telegram->setWebhook(WEBHOOK_URL)->then(
                function ($true) {
                    echo 'Webhook set successfully';
                },
                function (TelegramException $e) {
                    $error = $e->getErrorCode() . ' ' . $e->getDescription();
                    echo "Webhook error: " . $error;
                }
            );
        }
    }
);

$db = new DB();

/* держу слушатели отдельно для красивости (список в конфиге) */
foreach ($listeners as $listener) {
    if (file_exists(SYSTEM . 'Listeners/' . $listener)) {
        include SYSTEM . 'Listeners/' . $listener;
    } else {
        $logger->error("Listener $listener not found!");
        die();
    }
}

$bot->run();
