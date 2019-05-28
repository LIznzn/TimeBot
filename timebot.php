<?php

define('BOT_TOKEN', 'SECRET');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

function apiRequestWebhook($method, $parameters) {
    if (!is_string($method)) {
        error_log("Method name must be a string\n");
        return false;
    }

    if (!$parameters) {
        $parameters = array();
    } else if (!is_array($parameters)) {
        error_log("Parameters must be an array\n");
        return false;
    }

    $parameters["method"] = $method;

    header("Content-Type: application/json");
    echo json_encode($parameters);
    return true;
}

function exec_curl_request($handle) {
    $response = curl_exec($handle);

    if ($response === false) {
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        error_log("Curl returned error $errno: $error\n");
        curl_close($handle);
        return false;
    }

    $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
    curl_close($handle);

    if ($http_code >= 500) {
        // do not wat to DDOS server if something goes wrong
        sleep(10);
        return false;
    } else if ($http_code != 200) {
        $response = json_decode($response, true);
        error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
        if ($http_code == 401) {
            throw new Exception('Invalid access token provided');
        }
        return false;
    } else {
        $response = json_decode($response, true);
        if (isset($response['description'])) {
            error_log("Request was successfull: {$response['description']}\n");
        }
        $response = $response['result'];
    }

    return $response;
}

function apiRequest($method, $parameters) {
    if (!is_string($method)) {
        error_log("Method name must be a string\n");
        return false;
    }

    if (!$parameters) {
        $parameters = array();
    } else if (!is_array($parameters)) {
        error_log("Parameters must be an array\n");
        return false;
    }

    foreach ($parameters as $key => &$val) {
        // encoding to JSON array parameters, for example reply_markup
        if (!is_numeric($val) && !is_string($val)) {
            $val = json_encode($val);
        }
    }
    $url = API_URL . $method . '?' . http_build_query($parameters);

    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);

    return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
    if (!is_string($method)) {
        error_log("Method name must be a string\n");
        return false;
    }

    if (!$parameters) {
        $parameters = array();
    } else if (!is_array($parameters)) {
        error_log("Parameters must be an array\n");
        return false;
    }

    $parameters["method"] = $method;

    $handle = curl_init(API_URL);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

    return exec_curl_request($handle);
}

$aboutmsg = "Time Bot 由 @LIznzn 开发，用于快速判断群友时间，目前还在测试中。";
$timemsg = "你群时间：\n"
    .chibagettime("CST")."\n"
    .chibagettime("JST")."\n"
    .chibagettime("GMT")."\n"
    .chibagettime("CET")."\n"
    .chibagettime("EST")."\n"
    .chibagettime("PST")."\n";


function chibagettime($timezone) {
    date_default_timezone_set('UTC');
    if ($timezone === "CST") {
        $date=date_create(NULL,timezone_open("Asia/Shanghai"));
        return date_format($date,"M d H:i ")."🇨🇳";
    } else if ($timezone === "JST") {
        $date=date_create(NULL,timezone_open("Asia/Tokyo"));
        return date_format($date,"M d H:i ")."🇯🇵";
    } else if ($timezone === "GMT") {
        $date=date_create(NULL,timezone_open("Europe/London"));
        return date_format($date,"M d H:i ")."🇬🇧";
    } else if ($timezone === "CET") {
        $date=date_create(NULL,timezone_open("Europe/Berlin"));
        return date_format($date,"M d H:i ")."🇩🇪";
    } else if ($timezone === "EST") {
        $date=date_create(NULL,timezone_open("America/New_York"));
        return date_format($date,"M d H:i ")."🇺🇸NY";
    } else if ($timezone === "PST") {
        $date=date_create(NULL,timezone_open("America/Los_Angeles"));
        return date_format($date,"M d H:i ")."🇺🇸LA";
    }
}

function processMessage($message, $timemsg, $aboutmsg) {
    // process incoming message
    $message_id = $message['message_id'];
    $chat_id = $message['chat']['id'];

    if (isset($message['text'])) {
        // incoming text message
        $text = $message['text'];

        if ($text === "/about" or $text === "/about@chibatime_bot") {
                apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "text" => $aboutmsg));
        } else if ($text === "/time" or $text === "/time@chibatime_bot") {
                apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "text" => $timemsg));
        } else if (strpos($text, "/stop") === 0) {
            // stop now
        } else {
            //apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "couldn't understand what you said."));
        }
    }
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    // receive wrong update, must not happen
    echo 'OK';
    exit;
}

if (isset($update["message"])) {
    processMessage($update["message"], $timemsg, $aboutmsg);
}