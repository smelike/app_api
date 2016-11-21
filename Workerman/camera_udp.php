<?php

   	use Workerman\Worker;
    include './Workerman/Autoloader.php';
    date_default_timezone_set("Asia/Shanghai");
    $active_socket = [];

    $udp_worker = new Worker("udp://0.0.0.0:50002");
    $udp_worker->count = 1;

    $udp_worker->onWorkerStart = function ()
    {
    	$inner_text_worker = new Worker("Text://0.0.0.0:50003");
    	$inner_text_worker->onMessage = function ($connection, $data) {
            global $active_socket;
            include_once('./CommandPattern.php');

            echo "socket count:\n";
            var_dump($active_socket);
            echo "\n";
            $laravel_data = json_decode($data, true);
            echo "Laravel -> UDP\n";
            print_r($laravel_data);

            $commandPattern = new CommandPattern($laravel_data['command'], 'null', $laravel_data['payload']);
            $device_mac = $commandPattern->getDeviceMac();
            $arr_log['data'] = $data;
            $arr_log['time'] = date('Y-m-d H:i:s');
            $arr_log['return'] = "\r\n";
            file_put_contents("river_message_log", $data, FILE_APPEND);
            $send_socket = null;
            if (isset($active_socket[$device_mac])) {
                $send_socket = $active_socket[$device_mac];
            } else {
                // James to do 返回laravel错误信息
                file_put_contents("river_message_log", "\r\nNO MACHINE ONLINE", FILE_APPEND);
                return;
            }

            $arr_formatData = foramtControlCommand($laravel_data);
            echo "UDP->DEVICE-11:\n";
            print_r($arr_formatData);
            echo "\n";
            $arr_send_data = hex2bin(join("",$arr_formatData));
            $dret = $send_socket->send($arr_send_data);
            $connection->send(888888);
            slog("front_to_device_log", $arr_log);
            //if ($dret) {
            $sendto = $send_socket->getRemoteIp() . $send_socket->getRemotePort();
            $arr_device_log = array(
                "time" => date('Y-m-d H:i:s'),
                "sendto" => $sendto,
                "ret" => var_export($dret, true)
            );
            //slog("device_response_log", $arr_device_log);
            //}
    	};
        $inner_text_worker->listen();
    };

    $udp_worker->onMessage	= function ($connection, $data)
    {
        $ipport = $connection->getRemoteIp() . $connection->getRemotePort();
        $arr_receive = array(
            'title' => 'Device->UDP',
            'time' => date('Y-m-d H:i:s'),
            'ipport' => $ipport,
            'data' => bin2hex($data)
        );
        // udp 接收到的响应日志记录
        slog('udp_onmessage_log', $arr_receive);
        //  处理报文发起的请求
//        $ret = false;
        $ret = sendMessageByCurl($connection, $data);
        if ($ret)
        {
            echo "\n";
            $ret = $connection->send($ret);
            echo "发送给设备的返回值:";
            var_dump($ret);
        }
    };
    function foramtControlCommand($arr_laravel_data)
    {

        echo "defore controll";
        $arr_data = reportHead();
        var_dump($arr_laravel_data);
        $arr_data['ver_type_tkl'] = '40';
        $arr_data['code'] = '02';
        $arr_data['message_id'] = $arr_laravel_data['message_id'];
        $arr_data['service_code'] = $arr_laravel_data['service_code'];

        $len = base_convert(1 + strlen($arr_laravel_data['payload']) / 2, 10, 16);
        echo "length:::";
        var_dump($len);
        $arr_data['data_len'] = strlen($len) == 1 ? '000' . $len : (strlen($len) == 2 ? '00' . $len : $len);
        $arr_data['command'] = $arr_laravel_data['command'];
        $arr_data['payload'] = $arr_laravel_data['payload'];
        echo "formateconrt";
        var_dump($arr_data);
        return $arr_data;
    }
    // format payload
    function foramtPayloadFromLaravel($arr_laravel_data)
    {
        if (empty($arr_laravel_data['payload'])) {
            return false;
        }
        $arr_data = reportHead();

        $arr_data['ver_type_tkl'] = '60';
        $arr_data['code'] = '45';
        $arr_data['message_id'] = $arr_laravel_data['message_id'];
        $arr_data['service_code'] = $arr_laravel_data['service_code'];

        $len = base_convert(1 + strlen($arr_laravel_data['payload'])/2, 10, 16);
        $arr_data['data_len'] = strlen($len) ==1 ? '000'.$len : ( strlen($len) == 2 ? '00'.$len : $len );
        $arr_data['command'] = $arr_laravel_data['command'];
        $arr_data['payload'] = $arr_laravel_data['payload'];

        return $arr_data;
    }
    // 处理设备发起的报文请求
    function commandPattern ($hexData)
    {
        if (empty ($hexData)) { return false;}
        //  这里应该避免重复加载 CommandPattern.php,可以优化不使用 include_one 函数
        include_once('./CommandPattern.php');

        $command = substr($hexData, 16, 2);
        $reportHead = substr($hexData, 0, 18);
        $reportBody = substr($hexData, 18);

        $commandPattern = new CommandPattern($command, $reportHead, $reportBody);
        $arr_report_head = $commandPattern->handler();
        $device_mac = $commandPattern->device_mac;

        $arr_send_laravel = [];
        if ($arr_report_head['report_head']['command'] == '03')
        {
            $arr_send_laravel = $arr_report_head['report_head'];
        } else {
            foreach($arr_report_head['send'] as $key => $value) {
                $arr_send_laravel[$key] = $arr_report_head['report_head'][$key];
            }
        }
        return array('data' => $arr_send_laravel, 'device_mac' => $device_mac);
    }

    function hex2Binary($indata)
    {
        $length = strlen($indata);
        $rev = strrev($indata);
        $return = '';
        while($length--)
        {
            $tmp = decbin(hexdec($rev[$length]));
            $less = 4 - strlen($tmp);
            $append = '';
            while ($less--) {
              $append .= 0;
            }
            $return .= $append . $tmp;
        }
        return $return;
    }
    // 报文头
    function reportHead()
    {
         $arr_head = array(
             'ver_type_tkl' => '60',
             'code' => '45',
             'message_id' => '0000',
             'delimiter'  => 'FF',
             'service_code' => '01',
             'data_len'     => '0000',
             'command'      => '00',
             'payload'  => ''
         );
        return $arr_head;
    }
    // 处理 laravel 返回的数据
    function sendReport($ret)
    {

    }
    // 转发报文给 Laravel  处理
    function sendMessageByCurl($connection, $data)
    {
        global $active_socket;
        $formatData = commandPattern(bin2hex($data));

        // 下面是用于返回心跳响应
        $active_socket[$formatData['device_mac']] = $connection;
        if ($formatData['data']['command'] != '03')
        {
            echo "UDP->Laravel:\n";
            print_r($formatData['data']);
            echo "\n";
            $post = array('report' => json_encode($formatData['data']));
            $request_url = "http://qc02.xqopen.com/baking/bak/device/receive";
            $ret = scurl($request_url, $post);
            $ret = json_decode($ret, true);
            $arr_log = array(
                'datetime' => "AAA" . date('Y-m-d H:i:s') . "BBBB",
                'udp_send' => json_encode($formatData['data']),
                'laravel_return' => var_export($ret, true)
            );
            // udp 发送给 laravel的日志
            slog('udp_laravel_log', $arr_log);
            $ret = is_array($ret) ? array_merge($formatData['data'], json_decode($ret, true)) : $formatData['data'];
            //  格式化处理返回给device的数据
            $ret1 = foramtPayloadFromLaravel($ret);
            // 对 laravel 返回空 payload的处理
            if (!$ret1)
            {
                slog('laravel_return_error_log', 'error');
                return false;
            }
            $ret = hex2bin(join("", $ret1));
            echo "UDP->DEVICE:\n";
            print_r($ret1);
        } else {
            echo "心跳\n";
            $ret = hex2bin(join("", $formatData['data']));
        }
        // 这里最终都返回 hex2bin() 格式化数据
        return $ret;
    }
    // 保存心跳链接
    function keepHeartSocket($connection, $formatData)
    {
        global  $hear_socket_connect;
        // 如果是心跳，则发送控制指令
        if ($formatData['command'] == '00000101') {
            $hear_socket_connect = $connection;
        }
        // 可以一个日志...
    }
    function scurl ($request_url, $post)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $return = curl_exec($ch);
        curl_close($ch);
        return $return;
    }
    function slog($log_file, $arr_log)
    {
        if (is_array($arr_log))
        {
            file_put_contents($log_file, join("\t", $arr_log) . "\r\n", FILE_APPEND);
        }
    }

    Worker::runAll();