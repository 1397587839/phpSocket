<?php

// 该函数是根据format参数把args数据打包成二进制字符串（二进制：0101...）
$a = 300;  // 占用2字节

$bin = pack('nX', $a);  // n可以存储2字节，X回退一字节。

// 查看真实长度
echo "len: " . strlen($bin) . "\n";

// 查看数据（因为真实数据长度只有1了，所以只需1字节C接收就可以）
print_r(unpack('C', $bin));

