<?php

namespace Te;

use Te\Protocols\Stream;

class Client
{
    public $_mainSocket;
    public $_events = [];  // 注册回调的数组

    // 定义接收数据限制
    public $_readBufferSize = 102400;  // 最大接收数据量（100kb）
    public $_recvBufferSize = 1024 * 100;  // 表示当前的连接 接收缓冲区的大小
    public $_recvlen = 0;  // 表示当前连接目前接收到的字节数大小
    public $_recvBuffer = '';  // 接收缓冲区，保存全部接收到的数据

    // 设置全局变量，应用层的协议（目前是stream）
    public $_protocol;

    public $_localSocket;

    // 4. 发送缓冲区
    public $_sendLen = 0;
    public $_sendBuffer = '';
    public $_sendBufferSize = 1024 * 1000;  // 100兆
    public $_sendBufferFull = 0;  // 发送满了的次数

    public function __construct($localSocket)
    {
        // 初始化
        $this->_localSocket = $localSocket;

        // 刚构造时初始化协议，在这里写简单
        $this->_protocol = new Stream();
    }

    // 也是通过注册回调方式编写
    public function on($eventName, $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    // 编写运行回调函数代码
    public function runEventCallBack($eventName, $args = [])
    {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        } else {
            echo "not found {$eventName} event call";
        }
    }

    public function Start()
    {
        // 创建客户端
        // 该函数内部会调用connect函数
        $this->_mainSocket = stream_socket_client($this->_localSocket, $errno, $errstr);

        if (is_resource($this->_mainSocket)) {
            // 连接成功执行connect回调，向客户端发送消息（并没有放到下面的eventLoop里，先简单写）
            $this->runEventCallBack("connect", [$this]);

            // 连接成功开启事件循环
//            $this->EventLoop();
        } else {
            // 连接失败，调用失败回调
            $this->runEventCallBack("error", [$this, $errno, $errstr]);
        }
    }

    // event事件循环（select I/O）收发消息
    public function EventLoop()
    {
//        while (1) {
        if (is_resource($this->_mainSocket)) {
            $readFds = [$this->_mainSocket];
            // 6. 判断当需要发送数据时，才监听发送
            $writeFds = [];
            if ($this->isNeedSend()) {
                $writeFds = [$this->_mainSocket];
            }

            $exptFds = [$this->_mainSocket];

            $ret = stream_select($readFds, $writeFds, $exptFds, NULL, NULL);

            if ($ret <= 0 || $ret === false) {
                echo "事件循环出错\n";
//                break;
                return false;
            }

            if ($readFds) {
                // 有可读数据了，封装recv方法接收数据
                $this->recv4socket();
            }

            // 1. 开启可写捕捉
            if ($writeFds) {
                // 2. 封装send方法（使用发送缓冲区发送数据）
                $this->write2socket();
            }

            return true;
        } else {
            return false;
        }
    }

    //  实现关闭方法
    public function onClose()
    {
        // 关闭连接，执行事件回调
        fclose($this->_mainSocket);
        $this->runEventCallBack("close", [$this]);
        // 走到这程序就应该退出了
    }

    // 封装recv接收数据
    public function recv4socket()
    {
        $data = fread($this->_mainSocket, $this->_readBufferSize);

        if ($data === '' || $data === false) {
            // 代表服务端关闭连接（或者我们自己关闭了），调用我们的onClose回调
            if (feof($this->_mainSocket) || !is_resource($this->_mainSocket)) {
                $this->onClose();
            }
        } else {
            $this->_recvBuffer .= $data;
            $this->_recvlen += strlen($data);
        }

        if ($this->_recvlen > 0) {
            // 封装成handleMessage方法
            $this->handleMessage();
        }
    }

    public function handleMessage()
    {
        // 可能会有多条消息，所以要while循环
        while ($this->_protocol->Len($this->_recvBuffer)) {
            // 代码到这里已经知道最少有一条完整消息，获取消息长度（通过缓冲区中长度字段）
            $msgLen = $this->_protocol->MsgLen($this->_recvBuffer);

            // 从缓冲区截取一条消息（根据刚才获取到的长度精准截取，保证不会粘包，之前的逻辑保证不会少包）
            $oneMsg = substr($this->_recvBuffer, 0, $msgLen);
            $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);
            // 客户端也要处理
            $this->_recvlen -= $msgLen;

            // 解包，获取最终的消息
            $message = $this->_protocol->Decode($oneMsg);

            // 执行recv事件
            $this->runEventCallBack("receive", [$message]);
        }
    }

    // 3. 封装实现了缓冲区的发送数据
    public function send($data)
    {
        $len = strlen($data);
        if ($this->_sendLen + $len < $this->_sendBufferSize) {

            // 5. 并不是直接发送数据，而是先将数据放到发送缓冲区里
            $bin = $this->_protocol->Encode($data);
            $this->_sendBuffer .= $bin[1];
            $this->_sendLen += $bin[0];

        } else {
            $this->_sendBufferFull++;
            // 发送缓冲区满的回调
            $this->runEventCallBack('receiveBufferFull', [$this]);
        }
    }

    /**
     * 根据发送缓冲区判断是否需要发送数据
     * @return bool
     */
    public function isNeedSend(): bool
    {
        return $this->_sendLen > 0;
    }

    // 7. 修改发送数据接口，不需要打包了，因为send已经打包完了，直接从发送缓冲区取数据发送
    public function write2socket()
    {
        if ($this->isNeedSend()) {
            $writeLen = fwrite($this->_mainSocket, $this->_sendBuffer, $this->_sendLen);
            // 关闭发送打印
//         echo "我写了" . $writeLen . "字节\n";

            // fwrite在发送数据的时候会存在以下几种情况：
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
                $this->onClose();
            }
        }
    }
}