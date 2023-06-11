<?php


namespace Te\Protocols;


interface Protocol
{
    // 获取数据长度（知道长度才能截取）
    public function Len($data);

    // 用于打包数据（封包）
    public function Encode($data = '');

    // 拆包
    public function Decode($data = '');

    // 消息长度
    public function MsgLen($data = '');
}