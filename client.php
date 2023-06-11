<?php

require_once "vendor/autoload.php";

// 调用客户端
$client = new \Te\Client('tcp://127.0.0.1:12345');

// 注册connect回调事件，连接成功后调用函数执行该函数
$client->on("connect", function (\Te\Client $client) {
    // 连接成功后调用发送方法（并没有在select监听到可写事件里写，先简单写）
    $client->write2socket('hello');
});

// 注册失败事件，因为也有失败的时候（因为要知道错误原因，所以传2、3参数）
$client->on("error", function (\Te\Client $client, $errno, $errstr) {
    // 展示错误信息
    echo "errno: " . $errno . "errstr: " . $errstr;
});

// 注册关闭事件
$client->on("close", function (\Te\Client $client) {
    echo "服务器断开我的连接了\n";
});

// 接收消息事件
$client->on("receive", function (\Te\Client $client, $msg) {
    echo "recv from server: " . $msg . "\n";
    // 客户端接收到消息后，继续向服务端发送消息，然后服务端又会回客户端，无限循环
    $client->write2socket('i am client');
});

// 执行程序
$client->Start();