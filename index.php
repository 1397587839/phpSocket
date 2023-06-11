<?php

require_once "vendor/autoload.php";

// tcp  connect/recvive/close
// udp  packet/close
// http request
// ws   open/message/close
// mqtt connect/subscribe/unsubscribe/publish/close

try {
    // 由于用 : 拆分，所以把tcp后面的 // 拿掉
    $server = new \Te\Server('stream:127.0.0.1:12345');

    $server->on("connect", function (\Te\Server $server, \Te\TcpConnection $connection) {
        echo "有客户端连接了\n";
    });

    // 注册事件
    $server->on("receive", function (\Te\Server $server, $msg, \Te\TcpConnection $connection) {
//        echo "接收到 " . (int)$connection->_sockfd . " 客户端的数据：" . $msg . "\n";

        // 改为调用send
        $connection->send('i am server');
    });

    // 关闭事件
    $server->on("close", function (\Te\Server $server, $msg, \Te\TcpConnection $connection) {
        echo "客户端断开连接了" . "\n";
    });

    // 简化入口文件代码，将listen和event代码放入service的方法中
    $server->Start();
} catch (Exception $e) {
    echo $e->getMessage();
}