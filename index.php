<?php

// error_reporting(0);

require __DIR__ . '/../vendor/autoload.php';

use Orhanerday\OpenAi\OpenAi;

header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Headers:*');
header('Content-type: text/event-stream');
header('Cache-Control: no-cache');

// $post_data = json_decode(file_get_contents('php://input'), true);

$req_id = $_GET['req_id'];
$question = $_GET['question']; // 获取 question 参数

if (empty($question) || empty($req_id)) {
  die('please input your question');
}

$rds = new Redis();
if ($rds->connect('127.0.0.1') === false) {
  die('something error 1');
}

$messages = $rds->get($req_id);
if ($messages !== false) {
  $messages = json_decode($messages, true);
} else {
  $messages = [];
}
$messages[] = [
  'role' => 'user',
  'content' => $question,
];

$openai_api_key = getenv('OPENAI_API_KEY'); // 替换为你的 OpenAI API Key
if ($openai_api_key === false) {
  die('something error 2');
}

$opts = [
  'model' => 'gpt-3.5-turbo',
  'messages' => $messages,
  'temperature' => 1.0,
  'frequency_penalty' => 0,
  'presence_penalty' => 0,
  'stream' => true
];
$open_ai = new OpenAi($openai_api_key);
$rsp_data = "";
$open_ai->chat($opts, function ($curl_info, $data) use (&$rsp_data) {
  if ($obj = json_decode($data) && $obj->error->message != "") {
      // TODO
  } else {
      echo $data;
      $clean = str_replace("data: ", "", $data);
      $arr = json_decode($clean, true);
      if ($data != "data: [DONE]\n\n" && isset($arr["choices"][0]["delta"]["content"])) {
          $rsp_data .= $arr["choices"][0]["delta"]["content"];
      }
  }

  echo PHP_EOL;
  ob_flush();
  flush();
  return strlen($data);
});

if (strlen($rsp_data) > 0) {
  $messages[] = [
    'role' => 'assistant',
    'content' => $rsp_data,
  ];
  $rds->set($req_id, json_encode($messages), 1000);
}

