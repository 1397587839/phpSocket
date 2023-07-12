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

    //  定义各种限制
    public $_recvBufferSize = 1024 * 100;   // 100kb 表示当前连接的接收缓冲区大小（单位字节）
    public $_recvLen = 0;                   // 表示当前连接目前接收到的字节数大小
    public $_recvBufferFull = 0;            // 表示当前连接接收的字节数是否超出缓冲区（计超出的次数）
    public $_recvBuffer = '';               // 接收缓冲区，用来存储所有接收的数据

    // 接收数据有限制，发送数据也要有限制
    public $_sendLen = 0;
    public $_sendBuffer = '';
    public $_sendBufferSize = 1024 * 1000;  // 100兆
    public $_sendBufferFull = 0;  // 发送满了的次数

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
        //  校验接收总数据大小
        if ($this->_recvLen < $this->_recvBufferSize) {
            // 也可以使用 stream_socket_recvfrom
            // 将接收缓冲区大小变成活值
            $data = fread($this->_sockfd, $this->_readBufferSize);

            // 实现关闭逻辑，调用封装的Close方法，里面调用onClose回调函数
            if ($data === '' || $data === false) {
                if (feof($this->_sockfd) || !is_resource($this->_sockfd)) {
                    $this->Close();
                }
            } else {
                // 接收到数据
                // 把接收到的数据放到接收缓冲区里
                $this->_recvBuffer .= $data;
                $this->_recvLen += strlen($data);

                // 接收消息数增加
                /** @var Server $server */
                $server = $this->_server;
                $server->onRecv();
            }

        } else {
            // 这里可以加recv缓冲区满的事件（如果需要的话）
            $this->_recvBufferFull++;
        }

        // 超出缓冲区也要处理数据，所以将该代码从里面改放到这里
        //  拆包
        if ($this->_recvLen > 0) {
            $this->handleMessage();
        }
    }

    public function handleMessage()
    {
        // 拆包
        /** @var Server $server */
        $server = $this->_server;

        // 处理接收消息方法，判断，如果有应用层协议，就拆包，否则就不处理全部接收，也不封包
        if (is_object($server->_protocol) && $server->_protocol != null) {
            while ($server->_protocol->Len($this->_recvBuffer)) {
                // 代码到这里已经知道最少有一条完整消息，获取消息长度（通过缓冲区中长度字段）
                $msgLen = $server->_protocol->MsgLen($this->_recvBuffer);

                // 从缓冲区截取一条消息（根据刚才获取到的长度精准截取，保证不会粘包，之前的逻辑保证不会少包）
                $oneMsg = substr($this->_recvBuffer, 0, $msgLen);
                $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);

                // 拆包成功后除了数据要从缓冲区截取后，recvLen也要减少对应长度
                $this->_recvLen -= $msgLen;

                // 接收消息增加
                $server->onMsg();

                // 解包，获取最终的消息
                $message = $server->_protocol->Decode($oneMsg);

                // 执行recv事件
                $server->runEventCallBack("receive", [$message, $this]);
            }
        } else {
            // 拆包成功后除了数据要从缓冲区截取后，recvLen也要减少对应长度
            $this->_recvLen = 0;

            $message = $this->_recvBuffer;
            $this->_recvBuffer = '';
            // 执行recv事件
            $server->runEventCallBack("receive", [$message, $this]);
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

    // 封装一个解决少包的发送数据，send
    public function send($data)
    {
        /** @var Server $server */
        $server = $this->_server;

        $len = strlen($data);
        if ($this->_sendLen + $len < $this->_sendBufferSize) {

            // 处理发送消息方法
            if (is_object($server->_protocol) && $server->_protocol != null) {
                $bin = $server->_protocol->Encode($data);
                $this->_sendBuffer .= $bin[1];
                $this->_sendLen += $bin[0];
            } else {
                $this->_sendBuffer .= $data;
                $this->_sendLen += $len;
            }

        } else {
            $this->_sendBufferFull++;
            // 回调
            $server->runEventCallBack('receiveBufferFull', [$this]);
        }

        // fwrite在发送数据的时候会存在以下几种情况：
        $writeLen = fwrite($this->_sockfd, $this->_sendBuffer, $this->_sendLen);
        if ($writeLen == $this->_sendLen) {
            // 第一种情况：完整发送；

            // 发送成功后，也要将 发送缓冲区 和 发送字节数 清空
            $this->_sendBuffer = '';
            $this->_sendLen -= $writeLen;

            return true;
        } else if ($writeLen > 0) {
            // 第二种情况：只发送一半
            // 只是做了简单的处理，减掉了对应长度的缓冲区和发送的字节
            $this->_sendBuffer = substr($this->_sendBuffer, $writeLen);
            $this->_sendLen -= $writeLen;
            // 并没有将后面的继续发送，后面会讲代码进行完善
        } else {
            // 第三种情况：对端关闭
            $this->Close();
        }
    }

    public function write2socket($data)
    {
        /** @var Server $server */
        $server = $this->_server;
        // 调用打包方法
        $bin = $server->_protocol->Encode($data);

        $writeLen = fwrite($this->_sockfd, $bin[1], $bin[0]);
        echo "我写了" . $writeLen . "字节\n";
    }
}