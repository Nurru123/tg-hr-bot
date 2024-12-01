<?php

namespace Database;

/**
 *
 * @package Database
 */
class DatabaseConnection
{

    /**
     * @var
     */
    public $db;

    /**
     *  Construct
     */
    public function __construct()
    {

        $this->db = new \Database\DatabaseAdapter(
            DATABASE['driver'],
            DATABASE['host'],
            DATABASE['user'],
            DATABASE['pass'],
            DATABASE['name'],
            DATABASE['port']
        );
    }

    /**
     * @param $table_name
     * @return array
     */
    public function getLastId($table_name)
    {
        $table_name = $this->db->escape($table_name);

        $query = "SELECT MAX(id) AS last_id FROM `$table_name`";

        $result = $this->db->query($query);

        return $result->row['last_id'];
    }

    /**
     * @param $user_id
     * @return array
     */
    public function getUser($user_id)
    {
        $user_id = $this->db->escape($user_id);

        $query = "SELECT * FROM users WHERE user_id = '$user_id'";

        $result = $this->db->query($query);

        return $result->row;
    }

    /**
     * @param $user
     * @return bool
     */
    public function createUser($user)
    {
        $user_id = $this->db->escape($user['id']);
        $userName = $this->db->escape($user['userName']);
        $first_name = $this->db->escape($user['firstName']);
        $lastName = $this->db->escape($user['lastName']);
        $first_message_time = $last_message_time = $this->db->escape($user['last_message_time']);
        $is_bot = $this->db->escape($user['isBot']);

        $query = "INSERT INTO users (user_id, user_name, first_name, last_name, first_message_time, last_message_time, is_bot) VALUES ('$user_id', '$userName', '$first_name', '$lastName', '$first_message_time', '$last_message_time', '$is_bot')";

        $affectedRowsNumber = $this->db->execute($query, false);

        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user
     * @return bool
     */
    public function updateUser($user)
    {
        $user_id = $this->db->escape($user['id']);
        $userName = $this->db->escape($user['userName']);
        $firstName = $this->db->escape($user['firstName']);
        $lastName = $this->db->escape($user['lastName']);
        $lastMessageTime = $this->db->escape($user['last_message_time']);

        $query = "UPDATE users SET user_name = '$userName', first_name = '$firstName', last_name = '$lastName', last_message_time = '$lastMessageTime' WHERE user_id = '$user_id'";

        $affectedRowsNumber = $this->db->execute($query, false);

        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user_id
     * @return bool
     */
    public function isUserNew($user_id)
    {
        $user_id = $this->db->escape($user_id);
        $result = self::getUser($user_id);

        if (!$result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user
     */
    public function createOrUpdateUser($user)
    {
        if (self::isUserNew($user['id'])) {
            self::createUser($user);
        } else {
            self::updateUser($user);
        }
    }

    /**
     * @param $user_id
     * @return int | null
     */
    public function getUserIsBlocked($user_id)
    {
        $user_id = $this->db->escape($user_id);

        $query = "SELECT is_blocked FROM users WHERE user_id = '$user_id'";

        $result = $this->db->query($query);

        if (!empty($result->row)) {
            return $result->row['is_blocked'];
        } else {
            return null;
        }
    }

    /**
     * @param $user_id
     * @param $str
     * @return bool
     */
    public function setUserIsBlocked($user_id, $str)
    {
        $user_id = $this->db->escape($user_id);
        $str = $this->db->escape($str);

        $query = "UPDATE users SET is_blocked = '$str' WHERE user_id = '$user_id'";

        $affectedRowsNumber = $this->db->execute($query, false);
        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user_id
     * @return string | null
     */
    public function getUserStatus($user_id)
    {
        $user_id = $this->db->escape($user_id);

        $query = "SELECT `status` FROM users WHERE user_id = '$user_id'";
        $result = $this->db->query($query);

        if (!empty($result->row)) {
            return $result->row['status'];
        } else {
            return null;
        }
    }

    /**
     * @param $user_id
     * @param $str
     * @return bool
     */
    public function setUserStatus($user_id, $str)
    {
        $user_id = $this->db->escape($user_id);
        $str = $this->db->escape($str);

        $query = "UPDATE users SET `status` = '$str' WHERE user_id = '$user_id'";

        $affectedRowsNumber = $this->db->execute($query, false);
        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $message_id
     * @return string | null
     */
    public function getMessageStatus($message_id)
    {
        $message_id = $this->db->escape($message_id);

        $query = "SELECT `status` FROM messages WHERE message_id = '$message_id'";
        $result = $this->db->query($query);

        if (!empty($result->row)) {
            return $result->row['status'];
        } else {
            return null;
        }
    }

    /**
     * @param $message_id
     * @param $str
     * @return bool
     */
    public function setMessageStatus($message_id, $str)
    {
        $message_id = $this->db->escape($message_id);
        $str = $this->db->escape($str);

        $query = "UPDATE messages SET `status` = '$str' WHERE message_id = '$message_id'";

        $affectedRowsNumber = $this->db->execute($query, false);
        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user_id
     * @return int | null
     */
    public function getLastMessageTime($user_id)
    {
        $user_id = $this->db->escape($user_id);

        $query = "SELECT last_message_time FROM users WHERE user_id = '$user_id'";
        $result = $this->db->query($query);

        if (!empty($result->row)) {
            return $result->row['last_message_time'];
        } else {
            return null;
        }
    }

    /**
     * считаем количество сообщений пользователя за минуту
     * @param $user_id
     * @return int | null
     */
    public function getMessagesForMin($user_id)
    {
        $user_id = $this->db->escape($user_id);

        $now = time();

        $query = "SELECT media_group_id FROM messages WHERE user_id = '$user_id' AND '$now' - time < 60";
        $result = $this->db->query($query);

        if (!empty($result->rows)) {
            $res = array();
            $uniqueValues = 0;
            foreach ($result->rows as $value) {
                $res[] = $value['media_group_id'];
            }
            foreach (array_count_values($res) as $key => $value) {
                if ($key == 0) { //если это одиночное сообщение
                    $uniqueValues += $value;
                } else { //медиагруппу считаем за 1 сообщение
                    $uniqueValues += 1;
                }
            }
            return $uniqueValues;
        } else {
            return null;
        }
    }

    /**
     * @param $user_id
     * @param $chat_id
     * @param $message
     * @param $photo_path
     * @return bool
     */
    public function saveMessage($user_id, $chat_id, $message, $photo_path = '')
    {
        $user_id = $this->db->escape($user_id);
        $chat_id = $this->db->escape($chat_id);
        $message_date = $this->db->escape($message['date']);
        $message_id = $this->db->escape($message['id']);
        $media_group_id = $this->db->escape($message['media_group_id']);
        $text = $this->db->escape($message['text']);
        $photo_path = $this->db->escape($photo_path);
        $status = $this->db->escape($message['status']);

        $query = "INSERT INTO messages (user_id, chat_id, `time`, message_id, media_group_id, `text`, photo_path, `status`) VALUES ('$user_id', '$chat_id', '$message_date', '$message_id', '$media_group_id', '$text', '$photo_path', '$status')";

        $affectedRowsNumber = $this->db->execute($query, false);
        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user_id
     * @param $message_id
     * @param $photo_path
     * @return bool
     */
    public function savePhotoPath($user_id, $message_id, $photo_path)
    {
        $user_id = $this->db->escape($user_id);
        $photo_path = $this->db->escape($photo_path);

        $query = "UPDATE messages SET photo_path = '$photo_path' WHERE user_id = '$user_id' AND message_id = '$message_id'";

        $affectedRowsNumber = $this->db->execute($query, false);
        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user_id
     * @param $media_group_id
     * @return array
     */
    public function getMediaGroup($user_id, $media_group_id)
    {
        $user_id = $this->db->escape($user_id);
        $media_group_id = $this->db->escape($media_group_id);

        $query = "SELECT message_id FROM messages WHERE user_id = '$user_id' AND media_group_id = '$media_group_id'";
        $result = $this->db->query($query);

        $finalRes = [];
        foreach ($result->rows as $row) {
            $finalRes[] = $row["message_id"];
        }
        sort($finalRes);
        return $finalRes;
    }

    /**
     * @param $user_id
     * @param $media_group_id
     * 
     */
    public function exitIfMediaGroupAnswered($user_id, $media_group_id)
    {
        $message_ids = self::getMediaGroup($user_id, $media_group_id);
        $first_message_status = self::getMessageStatus($message_ids[0]);
        if ($first_message_status) {
            foreach ($message_ids as $message_id) {
                self::setMessageStatus($message_id, $first_message_status);
            }
            exit;
        }
    }

    /**
     * @param $step_id
     * @return array
     */
    public function getStep($step_id)
    {
        $step_id = $this->db->escape($step_id);

        $query = "SELECT * FROM steps WHERE step_id = '$step_id'";

        $result = $this->db->query($query);
        return $result->row;
    }

    /**
     * @param $user_id
     * @return array
     */
    public function getPhotos($user_id)
    {
        $user_id = $this->db->escape($user_id);

        $query = "SELECT `text` FROM answers WHERE user_id = '$user_id' AND title = 'Фото'";

        $result = $this->db->query($query);

        $finalRes = [];
        foreach ($result->rows as $row) {
            $finalRes[] = $row["text"];
        }
        return $finalRes;
    }

    /**
     * @param $user_id
     * @param $step_title
     * @param $message_date
     * @param $message_text
     * @return bool
     */
    public function saveAnswer($user_id, $step_title, $message_date, $message_text)
    {
        $user_id = $this->db->escape($user_id);
        $time = $this->db->escape($message_date);
        $step_title = $this->db->escape($step_title);
        $text = $this->db->escape($message_text);

        $query = "INSERT INTO answers (user_id, `time`, title, `text`) VALUES ('$user_id', '$time', '$step_title', '$text')";

        $affectedRowsNumber = $this->db->execute($query, false);
        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user_id
     * @param $column
     * @param $value
     * @return int | null
     */
    public function getAnswer($user_id, $column, $value)
    {
        $user_id = $this->db->escape($user_id);
        $column = $this->db->escape($column);
        $value = $this->db->escape($value);

        $query = "SELECT `text` FROM answers WHERE user_id = '$user_id' AND $column = '$value'";

        $result = $this->db->query($query);

        if (!empty($result->row)) {
            return $result->row['text'];
        } else {
            return null;
        }
    }

    /**
     * @param $user_id
     * @return array
     */
    public function getAllAnswers($user_id)
    {
        $user_id = $this->db->escape($user_id);

        $query = "SELECT * FROM answers WHERE user_id = '$user_id'";

        $result = $this->db->query($query);

        $data = $result->rows;

        usort($data, function ($a, $b) {
            return $a['id'] > $b['id'];
        });

        $is_duplicate = 0;
        
        $last_feedback_id = self::getLastId('feedbacks');
        $cur_id = str_pad(($last_feedback_id + 1), 4, '0', STR_PAD_LEFT);
        $num = date('Ym') . $cur_id;
        
        if (!in_array('Первичное обращение', array_column($data, 'title'))) {

            $data[] = [
                'title' => 'Первичное обращение',
                'text' => ''
            ];

            // //ищем все обращения за 48 часов по id пользователя
            // $interval = '48';
            // $prev_feedbacks = self::getFeedbackByUser($user_id, $interval);
            // $prev_feedbacks_list = '';
            // if ($prev_feedbacks) {
            //     if (empty($prev_feedbacks)) {
            //         $prev_feedbacks_list = 'обращения не найдены';
            //     } else {
            //         foreach ($prev_feedbacks as $item) {
            //             $prev_feedbacks_list .= '<br />' . $item['number'] . ' - ' . date('Y-m-d H:i:s', $item['time']);
            //         }
            //     }
                
            //     $data[] = [
            //         'title' => 'Все обращения пользователя за ' . $interval . ' часов',
            //         'text' => $prev_feedbacks_list
            //     ];
            // }
        }

        foreach ($data as &$item) {
            if ($item['title'] == 'Тип обращения' && $item['text'] == 'Со мной никто не связался') {
                $is_duplicate = 1;
            }
            if ($is_duplicate && $item['title'] == 'Первичное обращение') {
                if (empty($item['text'])) {
                    $item['text'] = 'не помню';
                } else {
                    $orig_feedback = self::getFeedbackByNumber($item['text']);
                    $data[] = [
                        'title' => 'Содержание',
                        'text' => 'Дата: ' . date('Y-m-d H:i:s', $orig_feedback['time']) . ' | ' . $orig_feedback['answers']
                    ];
                }
            }
        }
        unset($item);

        $finalRes = [
            'number' => $num,
            'is_duplicate' => $is_duplicate,
            'user_id' => $user_id,
            'time' => date("Y-m-d H:i:s"),
            'data' => $data
        ];

        return $finalRes;
    }

    /**
     * @param $user_id
     * @return array
     */
    public function deleteAllAnswers($user_id)
    {
        $user_id = $this->db->escape($user_id);

        $query = "DELETE FROM answers WHERE user_id='$user_id'";

        $affectedRowsNumber = $this->db->execute($query, false);
        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $data
     * @return bool
     */
    public function saveFeedback($data)
    {
        global $logger;

        $number = $this->db->escape($data['number']);
        $user_id = $this->db->escape($data['user_id']);
        $time = $this->db->escape(strtotime($data['time']));
        $is_duplicate = $data['is_duplicate'];

        $data_fixed = '';
        foreach ($data['data'] as $item) {
            $data_fixed .= $item['title'] . ': ' . $item['text'] . ' | ';
        }

        $query = "INSERT INTO feedbacks (`number`, user_id, `time`, answers, is_duplicate) VALUES ('$number', '$user_id', '$time', '$data_fixed', '$is_duplicate')";

        $affectedRowsNumber = $this->db->execute($query, false);
        if ($affectedRowsNumber > 0) {
            $logger->info("Feedback saved to DB - $number");
            return true;
        } else {
            $logger->error("Feedback was not saved to DB - $number");
            return false;
        }
    }

    /**
     * @param $interval //кол-во часов
     * @param $report_auto //false - не учитывать, true - не было связи от бота
     * @return array
     */
    public function getFeedbacks($interval = 0, $report_auto = false)
    {
        $interval = $this->db->escape($interval);

        $query = "SELECT * FROM feedbacks";

        if ($interval > 0 && $report_auto === false) {
            $query .= " WHERE `time` >= UNIX_TIMESTAMP(NOW() - INTERVAL '$interval' HOUR)";
        }

        if ($interval <= 0 && $report_auto === true) {
            $query .= " WHERE report_auto = '0' AND is_duplicate = '0'";
        }

        if ($interval > 0 && $report_auto === true) {
            $query .= " WHERE report_auto = '0' AND `time` >= DATE_ADD(NOW(), INTERVAL '$interval' HOUR) AND `time` <= DATE_ADD(NOW(), INTERVAL '$interval' + 1 HOUR) AND is_duplicate = '0'";
        }

        $result = $this->db->query($query);

        if (!empty($result->rows)) {
            return $result->rows;
        } else {
            return false;
        }
    }

    /**
     * @param $number //номер обращения
     * @return array
     */
    public function getFeedbackByNumber($number)
    {
        $number = $this->db->escape($number);

        $query = "SELECT * FROM feedbacks WHERE `number` = '$number' AND '$number' REGEXP '^[0-9]+$';";

        $result = $this->db->query($query);
        if (!empty($result->row)) {
            return $result->row;
        } else {
            return false;
        }
    }

    /**
     * @param $user_id
     * @param $interval //кол-во часов
     * @return array
     */
    public function getFeedbackByUser($user_id, $interval = 0)
    {
        $user_id = $this->db->escape($user_id);
        $interval = $this->db->escape($interval);

        $query = "SELECT * FROM feedbacks WHERE user_id = '$user_id'";

        if ($interval > 0) {
            $query .= " AND `time` >= UNIX_TIMESTAMP(NOW() - INTERVAL '$interval' HOUR)";
        }

        $result = $this->db->query($query);

        if (!empty($result->rows)) {
            return $result->rows;
        } else {
            return false;
        }
    }

    /**
     * @param $number
     * @return array
     */
    public function getReportUser($number)
    {
        $number = $this->db->escape($number);

        $query = "SELECT report_user FROM feedbacks WHERE `number` = '$number' AND '$number' REGEXP '^[0-9]+$';";

        $result = $this->db->query($query);
        if (!empty($result->row)) {
            return $result->row;
        } else {
            return false;
        }
    }

    /**
     * @param $number
     * @return bool
     */
    public function setReportUser($number)
    {
        $number = $this->db->escape($number);

        $query = "UPDATE feedbacks SET report_user = report_user + 1 WHERE `number` = '$number'";

        $affectedRowsNumber = $this->db->execute($query, false);
        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $number
     * @return array
     */
    public function getReportAuto($number)
    {
        $number = $this->db->escape($number);

        $query = "SELECT report_auto FROM feedbacks WHERE `number` = '$number' AND '$number' REGEXP '^[0-9]+$';";

        $result = $this->db->query($query);
        if (!empty($result->row)) {
            return $result->row;
        } else {
            return false;
        }
    }

    /**
     * @param $number
     * @return bool
     */
    public function setReportAuto($number)
    {
        $number = $this->db->escape($number);

        $query = "UPDATE feedbacks SET report_auto = report_auto + 1 WHERE `number` = '$number'";

        $affectedRowsNumber = $this->db->execute($query, false);
        if ($affectedRowsNumber > 0) {
            return true;
        } else {
            return false;
        }
    }
}
