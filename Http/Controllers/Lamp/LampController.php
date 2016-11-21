<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 9/5/16
 * Time: 11:56 AM
 */

namespace App\Http\Controllers\Lamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\LightPostRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Validator;

class LampController extends Controller
{

    private $_fields = [
        'id', 'lightNo', 'name', 'meshAddr', 'code', 'mainSwitch', 'subSwitch', 'groupId',
        'price', 'images', 'describe', 'createTime', 'updateTime'
    ];

    private $_table = "lamp_light";

    /*  @创建灯具
     *  @param $request
     *  @param $groupId
     * @return array
     * */
    public function create(Request $request, $groupId = '')
    {

        $messages = [
            'name.required' => '灯具名称不能为空',
            'name.alpha_dash' => '灯具名称包含非法字符',
            'groupId.required' => '必须选择分组',
            'groupId.integer' => '分组 ID 必须为数字',
            'lightNo.require' => '灯具编号',
            'meshAddr.required' => '灯具的 meshAddr 地址不能为空',
            'code.required' => '控制命令不能为空'
        ];

        $arr_rules = [
            'name' => 'required|alpha_dash',
            'groupId' => 'required|integer',
            'lightNo' => 'required',
            'meshAddr' => 'required',
            'code' => 'required'
        ];

        $light = $request->input('light');
        $light['groupId'] = $groupId;
        $validator = Validator::make($light, $arr_rules, $messages, array_values($arr_rules));
        $arr_error = $this->messageBag($validator->errors(), $arr_rules);

        if (empty($arr_error))
        {
            $arr_return = $this->insertLamp($light);
        } else {
            $arr_return = ['errmsg' => array_shift($arr_error)];
        }
        return $this->responseJson($arr_return);
    }

    /*  @插入灯具
     *  @param $light
     *  @return array
     * */
    private function insertLamp($light)
    {
        $arr_return = [];
        $where = [
            ['groupId', '=', $light['groupId']],
            ['status', '=', 0],
        ];
        $count = DB::table('light')->where($where)->count();
        if ($count < 255) {
            $arr_lamp = $this->formatInsertData($light);
            $id = DB::table('light')->insertGetId($arr_lamp);
            empty($id) ? '' : $arr_return['msg'] = ['id' => $id];
        } else {
            $arr_return = ['errmsg' => '灯具数量已经达到上限'];
        }

        return $arr_return;
    }
    /*  @格式化灯具数据
     *  @param $light
     *  @return array
     * */
    private function formatInsertData($light)
    {
        $arr_data = [];
        array_shift($this->_fields);
        foreach($this->_fields as $field) {
            $arr_data[$field] = isset($light[$field]) ? $light[$field] : '';
        }
        $arr_data['createTime'] = date('Y-m-d H:i:s');
        $arr_data['updateTime'] = date('Y-m-d H:i:s');

        foreach($arr_data as $key => $value) {
            if (is_array($value)) {
                $arr_data[$key] = json_encode($value, JSON_FORCE_OBJECT);
            }
        }
        return $arr_data;
    }

    /*  @删除灯具
     *  @param $groupId
     *  @param $id
     *  @return array
     * */
    public function del($groupId = '', $id = '')
    {
        $this->roleAuth();
        $groupId = (int) $groupId;
        $id = (int) $id;

        $arr_return = [];
        if ($groupId AND $id)
        {
            $where = [['id', '=', $id], ['groupId', '=', $groupId]];
            $update = ['status' => 1, 'updateTime' => date('Y-m-d H:i:s')];
            $return = DB::table('light')->where($where)->update($update);
            $return ? "" : $arr_return = ['errmsg' => '删除灯具失败'] ;
        } else {
            $arr_return = ['errmsg' => '必须要先选择删除的灯具'];
        }

        return $this->responseJson($arr_return);
    }
    /*  @以分组 ID 与灯具 ID 查询灯具
     *  @param $groupId
     *  @param $id
     *  @return array
     * */
    public function query($groupId = '', $id = '')
    {
        $groupId = (int) $groupId;
        $id = (int) $id;

        if ($groupId AND $id)
        {
            $return = $this->lightDetail($id);
            if ($return) {
                $arr_return['msg'] = $return;
            } else {
                $arr_return['errmsg'] = "不存在该灯具";
            }
        } else {
            $arr_return['errmsg'] = "接口参数格式不正确";
        }

        return $this->responseJson($arr_return);
    }

    /*  @以编号/名称查询灯具
     *  @param $request
     *  @param $groupId
     *  @return array
     * */
    public function search(Request $request, $groupId = 0)
    {

        $pageNo = (int) $request->input('pageNo');
        $pageNo = $pageNo ? $pageNo : 1;
        $per = 13;
        $query = $request->input('query');

        if (empty($query)) {
            return $this->responseJson(['errmsg' => '搜索内容不能为空']);
        }

        if ((int) $query)
        {
            $field = 'code';
            $where = "code like '%{$query}%' AND status = 0";
        } else {
            $field = 'name';
            $where = "name like '%{$query}%' AND status = 0";
        }

        $count = DB::table('light')->where($field, 'like', "%{$query}%")->count();

        $groupId ? $where = $where . ' AND groupId = ' . $groupId : '';
        $lights = $this->pagination($this->_table, $where, $count, $pageNo);
        $rows = $pageNo * $per;
        $arr_return['msg']['more'] = ($lights AND ($count < $rows)) ? 0 : 1;

        if ($lights) {
            foreach ($lights as &$light)
            {
                $this->jsonDecode($light);
            }
        }
        $arr_return['msg']['lights'] = (array) $lights ? $lights : [];
        return $this->responseJson($arr_return);
    }
    private function lightDetail($id)
    {
        $where = [['id', '=', $id],['status', '=', 0]];
        $arr_light = DB::table("light")->where($where)->first();
        $this->jsonDecode($arr_light);
        return $arr_light;
    }
    private function jsonDecode(&$arr_light)
    {
        if ($arr_light AND is_object($arr_light)) {
            $arr_light->images = empty($arr_light->images) ? [] : json_decode($arr_light->images, true);
            $arr_light->describe = empty($arr_light->describe) ? [] : json_decode($arr_light->describe, true);
            $arr_light->subSwitch = empty($arr_light->subSwitch) ? [] : json_decode($arr_light->subSwitch, true);
        }
    }

    /*  @编辑灯具
     *  @param $request
     *  @param $groupId
     *  @param $id
     *  @return array
     * */
    public function edit(Request $request, $groupId = '', $id = '')
    {
        $messages = [
            'id.required' => '商品ID不能为空',
            'name.required' => '灯具名称不能为空',
            'groupId.required' => '必须选择分组',
            'meshAddr.required' => '灯具的 meshAddr 地址不能为空',
            'code.required' => '控制命令不能为空',
            'price.required' => '价格不能为空'
        ];

        $arr_rules = [
            'id' => 'required',
            'name' => 'required',
            'groupId' => 'required',
            'meshAddr' => 'required',
            'code' => 'required',
            'price'  => 'required|digits_between:1,6'
        ];

        $data = $request->all();
        $data['groupId'] = (int) $groupId;
        if (isset($data['light']) AND $data['groupId'])
        {
            $data['light']['groupId'] = $data['groupId'];
            $data['light']['id'] = (int) $id;
            $validator = Validator::make($data['light'], $arr_rules, $messages, array_values($arr_rules));
            $arr_error = $this->messageBag($validator->errors(), $arr_rules);
            if (empty($arr_error))
            {
                $return = $this->updateLight($data['light']);
                $arr_return = ['errmsg' => '灯具修改失败'];
                if ($return) {
                    $arr_return = ['msg' => $return];
                }
            } else {
                $arr_return = ['errmsg' => array_shift($arr_error)];
            }
        } else {
            $arr_return = ['errmsg' => '接口参数不正确'];
        }
        return $this->responseJson($arr_return);
    }
    private function updateLight($light)
    {
        $light['updateTime'] = date('Y-m-d H:i:s');
        $return = $this->lightDetail($light['id']);
        $update = false;
        if ($return)
        {
            $where = [['id', '=', $light['id']], ['status', '=', 0]];
            isset($light['images']) AND is_array($light['images']) ? $light['images'] =  json_encode($light['images'], JSON_FORCE_OBJECT) : '';
            isset($light['subSwitch']) AND is_array($light['subSwitch']) ? $light['subSwitch'] = json_encode($light['subSwitch'], JSON_FORCE_OBJECT) : '';
            isset($light['describe']) AND is_array($light['describe']) ? $light['describe'] = json_encode($light['describe'], JSON_FORCE_OBJECT) : '';

            $update = DB::table('light')->where($where)->update($light);
            $new = $this->lightDetail($light['id']);
            return $update ? $new : false;
        }
        return $return && $update;
    }

    /*  @查询绑定的灯具
     *  @param void
     *  @return array
     * */
    public function queryBind()
    {
        $arr_blight = [];
        $arr_bind_light = DB::table('light')->whereNotNull('meshAddr')->get();

        foreach($arr_bind_light as $light) {
            if ($light->code == 'FF') {
                $arr_blight[] = $light;
            }
        }
        $arr_return = ['msg' => $arr_blight];
        return $this->responseJson($arr_return);
    }
    /*  @上传图片
     *  @param $request
     *  @return array
     * */
    public function img(Request $request)
    {
        $image = $request->file('image');
        if ($image)
        {
            $imageName = uniqid('light_img_') . '.' . $image->getClientOriginalExtension();
            $imageUrl = env('APP_URL') . '/public/upload/lights/' . $imageName;
            try {
                $image->move(base_path() . '/public/upload/lights/', $imageName);
                $arr_return = ['msg' => $imageUrl];
            } catch (FileException $e) {
                $arr_return = ['errmsg' => '灯具图片上传失败'];
            }
        } else {
            $arr_return = ['errmsg' => "上传图片不能为空"];
        }

        return $this->responseJson($arr_return);
    }
    // 绑定某个 mesh_addr 的设备信息
    public function queryByMeshAddr(Request $request)
    {
        $arr_return = [];
        if ($request->has('meshAddr')) {
            $arr_light = DB::table('light')->where('meshAddr', $request->input('meshAddr'))->get();
            $arr_return['msg'] = empty($arr_light) ? $this->_msg : $arr_light;
        } else {
            $arr_return['errmsg'] = 'mesh_addr 值不能为空';
        }

        return $this->responseJson($arr_return);
    }
    public function queryMeshAddrList()
    {
        $arr_return = [];
        $arr_mesh_addr = DB::table('light')->select('meshAddr')->distinct()->get();
        $arr_return['msg'] = $arr_mesh_addr ? $arr_mesh_addr : $this->_msg;

        return $this->responseJson($arr_return);
    }
}