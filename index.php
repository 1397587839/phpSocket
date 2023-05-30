<?php

require_once "vendor/autoload.php";

// tcp  connect/recvive/close
// udp  packet/close
// http request
// ws   open/message/close
// mqtt connect/subscribe/unsubscribe/publish/close

try {
    $server = new \Te\Server('tcp://127.0.0.1:12345');

    $server->on("connect", function (\Te\Server $server, \Te\TcpConnection $connection) {
        echo "有客户端连接了\n";
    });

    // 1. 注册事件
    $server->on("receive", function (\Te\Server $server, $msg, \Te\TcpConnection $connection) {
        echo "recv from client: " . $msg . "\n";

        // 7(最终). 将响应改到这里
        $connection->write2socket('i am server');
    });

    $server->BindAndListen();
    $server->EventLoop();
} catch (Exception $e) {
    echo $e->getMessage();
}