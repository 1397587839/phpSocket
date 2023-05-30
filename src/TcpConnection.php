<?php

namespace Te;

class TcpConnection
{
    public $_sockfd;
    // 将ip和端口也存起来
    public $_clientIp;  // ip:port
    // 2. 构造函数增加 $server
    public $_server;

    public function __construct($sockfd, $clientIp, $server)
    {
        $this->_sockfd = $sockfd;
        $this->_clientIp = $clientIp;
        $this->_server = $server;
    }

    public function getSockfd()
    {
        return $this->_sockfd;
    }

    // 封装接收数据函数
    public function recv4socket()
    {
        // 也可以使用 stream_socket_recvfrom
        $data = fread($this->_sockfd, 1024);
        if ($data) {
            echo "接收到 " . (int)$this->_sockfd . " 客户端的数据：" . $data . "\n";

            // 5.
            /** @var Server $server */
            $server = $this->_server;
            $server->runEventCallBak('receive', [$data, $this]);
        }
    }

    public function write2socket($data)
    {
        $len = strlen($data);
        // 也可以使用 stream_socket_sendto
        $writeLen = fwrite($this->_sockfd, $data, $len);
        echo "send" . $writeLen . "字节\n";
    }
}