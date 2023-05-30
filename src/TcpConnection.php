<?php

namespace Te;

class TcpConnection
{
    public $_sockfd;
    // 将ip和端口也存起来
    public $_clientIp;  // ip:port
    // 构造函数增加 $server
    public $_server;
    public $_readBufferSize = 1024;  // 接收缓冲区默认值

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
        // 将接收缓冲区大小变成活值
        $data = fread($this->_sockfd, $this->_readBufferSize);

        // 实现关闭逻辑，调用封装的Close方法，里面调用onClose回调函数
        if ($data === '' || $data === false) {
            if (feof($this->_sockfd) || !is_resource($this->_sockfd)) {
                $this->Close();
            }
        }

        if ($data) {
            echo "接收到 " . (int)$this->_sockfd . " 客户端的数据：" . $data . "\n";

            /** @var Server $server */
            $server = $this->_server;
            $server->runEventCallBack('receive', [$data, $this]);
        }
    }

    public function Close()
    {
        if (is_resource($this->_sockfd)) {
            fclose($this->_sockfd);
        }
        /** @var Server $server */
        $server = $this->_server;
        $server->runEventCallBack('close', [$server, $this]);

        // 封装移除主动套接字方法，将数组中该客户端套接字移除
        $server->onClientLeave($this->_sockfd);
    }

    public function write2socket($data)
    {
        $len = strlen($data);
        // 也可以使用 stream_socket_sendto
        $writeLen = fwrite($this->_sockfd, $data, $len);
        echo "send" . $writeLen . "字节\n";
    }
}