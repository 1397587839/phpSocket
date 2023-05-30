<?php

namespace Te;

use Exception;

class Server
{
    public $_mainSocket;
    public $_localSocket;  // 变成活值，是传入tcp还是udp
    static public $_connections = [];  // 存放连接socket

    public $_events = [];

    // 回调函数
    public function on($eventName, $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    // 变成活值，是传入tcp还是udp
    public function __construct($localSocket)
    {
        $this->_localSocket = $localSocket;
    }

    public function BindAndListen()
    {
        $option['socket']['backlog'] = 10;
        $context = stream_context_create($option);  // 内部是使用setsockopt这个函数

        $flag = STREAM_SERVER_LISTEN|STREAM_SERVER_BIND;
        $this->_mainSocket = stream_socket_server($this->_localSocket, $errno, $errStr, $flag, $context);

        // 判断是否创建成功
        if (!is_resource($this->_mainSocket)) {
            throw new Exception("server create fail: " . $errStr . "\n");
        }

        // 启动成功后简单打印下
        echo "listen on：" . $this->_localSocket . "\n";
    }

    public function Start()
    {
        $this->BindAndListen();
        $this->EventLoop();
    }

    // 封装I/O复用函数（事件循环）
    // 参数1-读文件描述符集合；参数2-写文件描述符集合；参数3-异常的集合；参数4-等待的时间（秒）；参数5-等待的时间（微秒）
    public function EventLoop()
    {
        while (1) {
            // 由于是监听套接字，我们只监听读事件
            // 走到这里证明while循环了一次，需要重新放入需要监听的套接字
            $readFds = [];  // 这里初始化，否则会有bug
            $readFds[] = $this->_mainSocket;
            $writeFds = [];
            $expFds = [];

            // 当我们在下面accept连接之后，就有了一个主动套接字，我们也要用select来监听这个主动套接字的数据收发事件
            if (!empty(static::$_connections)) {
                // 修改循环数组，sockfd调用conn类方法获取
                foreach (static::$_connections as $idx => $connection) {
                    /** @var TcpConnection $connection */
                    $sockFd = $connection->getSockfd();
                    $readFds[] = $sockFd;
                    $writeFds[] = $sockFd;
                }
            }

            // $this->_mainSocket 它是监听socket，我们只关注它的读事件（客户端连接）
            $ret = stream_select($readFds, $writeFds, $expFds, NULL, NULL);
            if ($ret === false) {
                throw new Exception("server select fail\n");
            }

            if ($readFds) {
                // 这里要改下代码，因为现在读事件发生不能确定是主动套接字还是被动套接字
                foreach ($readFds as $fd) {
                    if ($fd == $this->_mainSocket) {
                        // 开始接收客户端连接，这样就有了一个主动套接字，我们也要用select来监听这个主动套接字的数据收发事件
                        $this->Accept();
                    } else {
                        // 写到这里connect就已经封装好了，接下来要封装接收数据，将原直接fread的代码替换掉。
                        /** @var TcpConnection $connection */
                        $connection = static::$_connections[(int)$fd];
                        $connection->recv4socket();
                    }
                }
            }
        }
    }

    // 客户端断开，移除监听套接字
    public function onClientLeave($sockfd)
    {
        if (isset(static::$_connections[(int)$sockfd])) {
            unset(static::$_connections[(int)$sockfd]);
        }
    }

    // 改函数参数（调用地方都要改到）
    public function runEventCallBack($eventName, $args = [])
    {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            // 直接执行，因为是匿名函数可以直接调用，将当前对象传过去
            $this->_events[$eventName]($this, ...$args);
        }
    }

    // 封装accept
    public function Accept()
    {
        // 增加入参 $peerName，获取ip和端口
        $connfd = stream_socket_accept($this->_mainSocket, -1, $peerName);
        if (!is_resource($connfd)) {
            throw new Exception("server accept fail\n");
        }

        // new 封装的类，将套接字和端口传进去，并将返回值存入成员变量。
        // 传入 $server
        $connection = new TcpConnection($connfd, $peerName, $this);
        // 注意，因为此时存入的是类了，不是之前的套接字了，所以需要改上面循环这个数组的代码
        static::$_connections[(int)$connfd] = $connection;

        // 将这里拆分出一个方法
        $this->runEventCallBack('connect', [$connection]);
    }
}