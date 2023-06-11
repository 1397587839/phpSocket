<?php


namespace Te\Protocols;


class Stream implements Protocol
{
    // 用来检测消息是否完整
    public function Len($data)
    {
        // 实现Len方法，判断是不是一个完整的包，供接收端拆包
        if (strlen($data) < 4) {
            // 目前接收到的数据包不足一个包
            return false;
        }

        $tmp = unpack("NtotalLen", $data);
        if (strlen($data) < $tmp['totalLen']) {
            // 目前接收到的数据包不足一个包
            return false;
        }

        return true;
    }

    // 用于打包数据（封包）
    public function Encode($data = '')
    {
        // 实现封包方法
        // 数据包总长度为$data长度+6（4个字节长度+2字节cmd）
        $totalLen = strlen($data) + 6;
        // 将总长度和命令字段封包，N是4字节，n是2字节。（$data不需要封包，有点不理解，难道传进来的已经是二进制了吗）
        $bin = pack("Nn", $totalLen, '1') . $data;
        return [$totalLen, $bin];
    }

    // 拆包
    public function Decode($data = '')
    {
        // 不要最前面4字节的长度，要2字节的cmd和后面的载荷。
        $cmd = substr($data, 4, 2);
        $msg = substr($data, 6);
        return $msg;
    }

    // 返回一条消息的长度
    public function MsgLen($data = '')
    {
        $tmp = unpack("Nlength", $data);
        return $tmp['length'];
    }
}