<?php
/**
 * Created by PhpStorm.
 * User: hanyu
 * Date: 13/8/16
 * Time: 16:45
 */
use Workerman\Worker;
include __DIR__ . '/../../vendor/workerman/workerman/Autoloader.php';

$active_socket = [];
$laravel_url = 'http://qc02.xqopen.com/baking/bak/device/receive';

include __DIR__ . '/CommandPattern.php';

date_default_timezone_set("Asia/Shanghai");

$udpSocketName = "udp://0.0.0.0:50003";
$tcpSocketName = "tcp://0.0.0.0:60000";

/**
 * 启动UDP server
 */
$udp_worker = new Worker($udpSocketName);
$udp_worker->count = 1;

/**
 * 在workerman启动的时候, 启动给Laravel的TCP服务
 */
$udp_worker->onWorkerStart = function ()
{
    global $tcpSocketName;

    $tcp_worker = new Worker($tcpSocketName);

    $tcp_worker->onMessage = function ($connection, $data)
    {

        global $active_socket;

        echo "tcp_worker\r\n";
        $laravel_data = json_decode($data, true);
        print_r($laravel_data);
        // 记录消息日志
        $ipport = $connection->getRemoteIp() . ':' . $connection->getRemotePort();
        $arr_receive = array(
            'title'  => 'Laravel->UDP',
            'ipport' => $ipport,
            'data' => is_array($laravel_data) ? join("\t", $laravel_data) : $laravel_data
        );

        slog('tcp_onmessage_log', $arr_receive);

        $commandPattern = new CommandPattern($laravel_data['command'], 'null', $laravel_data['payload']);

        // 根据deviceid,取得保留的socket
        $device_mac = $commandPattern->getDeviceMac();
        echo "000ec607c705\r\n";
        echo $device_mac;
        $send_socket = null;
        if (isset($active_socket[ $device_mac ])) {
            $send_socket = $active_socket[ $device_mac ];
            $arr_formatData = formatControlCommand($laravel_data);
            $arr_send_data = hex2bin(join("", $arr_formatData));
            $dret = $send_socket->send($arr_send_data);
            $connection->send($dret);
        } else {
            slog("h5_error_message_log", "\r\nNO MACHINE ONLINE", FILE_APPEND);
            $connection->send(0);
        }
    };
    $tcp_worker->listen();
};


/**
 * 处理UDP消息的主要函数
 * @param $connection
 * @param $data
 * @return bool
 */
$udp_worker->onMessage = function ($connection, $data)
{
    // 记录消息日志
    $ipport = $connection->getRemoteIp() . ':' . $connection->getRemotePort();
    $arr_receive = array(
        'title'  => 'Device->UDP',
        'ipport' => $ipport,
        'data' => bin2hex($data)
    );

    slog('udp_onmessage_log', $arr_receive);

    $ret = sendMessageByCurl($connection, $data);
    var_dump($ret);
};

/**
 * 生成给device的消息头
 * @return array
 */
function formatHead()
{
    $arr_head = array(
        'ver_type_tkl' => '60',
        'code'         => '45',
        'message_id'   => '0000',
        'delimiter'    => 'FF',
        'service_code' => '01',
        'data_len'     => '0000',
        'command'      => '00',
        'payload'      => ''
    );

    return $arr_head;
}


/**
 * 格式化控制命令
 * @param $arr_laravel_data
 * @return array
 */
function formatControlCommand($arr_laravel_data)
{
    $arr_data = formatHead();
    $arr_data['ver_type_tkl'] = '40';
    $arr_data['code'] = '02';
    $arr_data['message_id'] = $arr_laravel_data['message_id'];
    $arr_data['service_code'] = $arr_laravel_data['service_code'];

    $len = base_convert(1 + strlen($arr_laravel_data['payload']) / 2, 10, 16);
    $arr_data['data_len'] = strlen($len) == 1 ? '000' . $len : (strlen($len) == 2 ? '00' . $len : $len);
    $arr_data['command'] = $arr_laravel_data['command'];
    $arr_data['payload'] = $arr_laravel_data['payload'];

    return $arr_data;
}

/**
 * @param $connection
 * @param $arr_device
 * @return mixed
 */
function sendDeviceCommand($connection, $arr_device)
{

    $arr_data['ver_type_tkl'] = '40';
    $arr_data['code'] = '02';
    $arr_data['message_id'] = $arr_device['message_id'];
    $arr_data['delimiter'] = 'FF';
    $arr_data['service_code'] = $arr_device['service_code'];
    $len = base_convert(1 + strlen($arr_device['payload']) / 2, 10, 16);
    $arr_data['data_len'] = strlen($len) == 1 ? '000' . $len : (strlen($len) == 2 ? '00' . $len : $len);
    $arr_data['command'] = $arr_device['command'];
    $arr_data['payload'] = $arr_device['payload'];

    return $connection->send(hex2bin(join("", $arr_data)));
}

function sendMessageByCurl($connection, $data)
{
    global $active_socket;
    global $laravel_url;

    $arr_command_data = CommandPattern::FormatCommandPattern($data);
    $arr_data = $arr_command_data['data'];
    $command = $arr_command_data['data']['command'];
    $device_mac = $arr_command_data['device_mac'];

    $active_socket[$device_mac] = $connection;
    $arr_log['dev_mac'] = $device_mac;
    $arr_log['date_time'] = date('Y-m-d H:i:s');

    if ($command != '03') {

        $post = array('report' => json_encode($arr_data));
        $arr_laravel_ret = scurl($laravel_url, $post);
        $arr_data = is_array($arr_laravel_ret) ? array_merge($arr_data, $arr_laravel_ret) : $arr_data;
        $arr_format_data = formatPayloadFromLaravel($arr_data);

        if (empty($arr_format_data)) {
            slog('laravel_return_error_log', 'error');
            return false;
        }
    }

    $arr_log_data = isset($arr_format_data) ? $arr_format_data : $arr_data;
    slog('udp_device_log', array_merge($arr_log, $arr_log_data));

    $string_binary_data = isset($arr_format_data) ? hex2bin(join("", $arr_format_data)) : hex2bin(join("", $arr_data));

    return $connection->send($string_binary_data);
}

/**
 * 格式化从laravel返回的数据
 * @param $arr_laravel_data
 * @return array|bool
 */
function formatPayloadFromLaravel($arr_laravel_data)
{
    if (empty($arr_laravel_data['payload'])) {
        return false;
    }

    $arr_data = formatHead();

    $arr_data['ver_type_tkl'] = '60';
    $arr_data['code'] = '45';
    $arr_data['message_id'] = $arr_laravel_data['message_id'];
    $arr_data['service_code'] = $arr_laravel_data['service_code'];

    $len = base_convert(1 + strlen($arr_laravel_data['payload']) / 2, 10, 16);
    $arr_data['data_len'] = strlen($len) == 1 ? '000' . $len : (strlen($len) == 2 ? '00' . $len : $len);
    $arr_data['command'] = $arr_laravel_data['command'];
    $arr_data['payload'] = $arr_laravel_data['payload'];

    return $arr_data;
}


/**
 * 通过80端口和lavarel通讯
 * @param $request_url
 * @param $post 四个字段给Laravel
 * @return mixed
 */
function scurl($request_url, $post)
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


/**
 *
 * 因为workerman单独使用,所以需要单独的日志函数
 * @param $log_file
 * @param $arr_log
 */
function slog($log_file, $log_data)
{
    $logDirectory = __DIR__ . '/../../storage/logs/';
    $log_file .= '_' . date('Y-m-d');

    $log_file = $logDirectory . $log_file;
    if (is_array($log_data) AND join("", $log_data))
    {
        $log_data['datetime'] = date('Y-m-d H:i:s');
        $log_data = join("\t", $log_data) . "\r\n";
    } else if($log_data) {
        $log_data .= "\t" . date('Y-m-d H:i:s') . "\r\n";
    } else {
        exit(0);
    }

    return file_put_contents($log_file, $log_data, FILE_APPEND);
}

Worker::runAll();

