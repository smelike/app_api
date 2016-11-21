<?php

    /**
     * Created by PhpStorm.
     * User: james
     * Date: 7/29/16
     * Time: 4:51 PM
     */

    /**
     * Class CommandPattern
     * @package App\Workerman
     */
    class CommandPattern
    {
        public $device_mac = null;

        // 返回上层 UDP 使用的参数, true 表示需要返回给 laravel, false 代表不需要返回给 laravel
        private $arr_send =
            array('message_id' => true,
                  'service_code' => true,
                  'command' => true,
                  'payload' => true);

        private $arr_fields = array(
            // 心跳
            '03' => array(
                'payload' => array('timestamp' => 4,'device_mac' => 6, 'device_status' => 1),
                'send'  => array('timestamp', 'device_mac'),
            )
        );

        private $_ver_type = "6";
        private $_tkl = "0";
        private $_code = "45";
        private $_fields;
        private $_command;
        private $_reportHead;
        private $_reportBody;
        private $_proccess = true;

        /*
         * Initial Data for format
         *
         */
        public function __construct($command, $reportHead = "", $reportBody = "")
        {
            $this->_command = $command;
            if ($this->_command AND $reportHead AND $reportBody)
            {
                $this->_fields = isset($this->arr_fields[$command]) ? $this->arr_fields[$command] : "";
                $this->_reportHead = $reportHead;
                $this->_reportBody = $reportBody;
                $this->device_mac = $this->getDeviceMac();
            } else {
                $this->_proccess = false;
                exit('wrong wrong');
            }
        }

        /**
         * 取得device id
         * @return string
         */
        public function getDeviceMac()
        {
            return substr($this->_reportBody, 8, 12);
        }

        /**
         * 如果是心跳直接返回心跳
         * @return array|bool
         */
        public function handler()
        {
            $return = false;
            if (($this->_command == '03') AND ($this->_proccess)) {
                $return = $this->formatHeart();
            }  else if (($this->_command) AND ($this->_proccess)) {
                $return = $this->formatHead();
            }
            return $return;
        }


        /**
         * 格式化消息头
         * @return array
         */
        private function formatHead()
        {
            $arr_report_head = array(
                'ver_type' => substr($this->_reportHead, 0, 1),
                'tkl' => substr($this->_reportHead, 1, 1),
                'code' => substr($this->_reportHead, 2, 2),
                'message_id' => substr($this->_reportHead, 4, 4),
                'delimiter' => substr($this->_reportHead, 8, 2),
                'service_code' => substr($this->_reportHead, 10, 2),
                'data_len'  => substr($this->_reportHead, 12, 4),
                'command'   => substr($this->_reportHead, 16, 2),
                'payload'   => $this->_reportBody,
            );
            // Only initialize data while
            if ($arr_report_head['command'] != 3)
            {
                $arr_report_head['report_head']['ver_type'] = $this->_ver_type;
                $arr_report_head['report_head']['tkl'] = $this->_tkl;
                $arr_report_head['report_head']['code'] = $this->_code;
            }

            return ['report_head' => $arr_report_head, 'send' => $this->arr_send];
        }

        /**
         * format heartbeat telegram, and return response
         * 处理心跳的返回
         * @return array
         */
        public function formatHeart()
        {

            $arr_report_head = $this->formatHead();
            unset($arr_report_head['device_mac']);
            $star = 0;
            foreach($this->_fields['payload'] as $key => $length)
            {
                $length = $length * 2;
                $arr_report_body[$key] = substr($this->_reportBody, $star, $length);
                $star += $length;
            }
            $arr_report_head['report_head']['ver_type'] = $this->_ver_type;
            $arr_report_head['report_head']['tkl'] = $this->_tkl;
            $arr_report_head['report_head']['data_len'] = '000b';
            $arr_report_head['report_head']['code'] = $this->_code;
            //$device_mac = $arr_report_body_head['report_head']['payload'];
            unset ($arr_report_head['report_head']['payload']);
            $arr_report_head['report_head']['timestamp'] = dechex(time());
            $arr_report_head['report_head']['device_mac'] = $arr_report_body['device_mac'];

            return $arr_report_head;
        }


        /**
         * @param $data 原始数据
         * @return array|bool 格式化的数据
         */
        public static function FormatCommandPattern($data)
        {
            if (empty ($data)) { return false;}
            $hexData = bin2hex($data);
            //$hexData = "40020360ff0100140157adba00000ec607c705004d41535341474523";
            $command = substr($hexData, 16, 2);
            $reportHead = substr($hexData, 0, 18);
            $reportBody = substr($hexData, 18);

            $commandPattern = new CommandPattern($command, $reportHead, $reportBody);
            $arr_report_head = $commandPattern->handler();
            $device_mac = $commandPattern->device_mac;

            $arr_send_laravel = [];
            if ($arr_report_head['report_head']['command'] == '03')  {
                $arr_send_laravel = $arr_report_head['report_head'];
            } else {
                foreach($arr_report_head['send'] as $key => $value) {
                    $arr_send_laravel[$key] = $arr_report_head['report_head'][$key];
                }
            }

            return array('data' => $arr_send_laravel, 'device_mac' => $device_mac);
        }

    }
