<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 9/6/16
 * Time: 6:08 PM
 */

namespace App\Http\Controllers\Lamp;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Validator;

class GroupController extends Controller
{

    protected $_fields = array('id', 'name', 'createTime', 'updateTime');

    private $_table = 'lamp_group';

    /**Modified By River
     * @param Request $request
     * @return mixed
     */
    public function create(Request $request)
    {
        $data = $request->all();

        if(empty($data['groupName'])) {
            $return = ['errmsg' => '分组名字不能为空'];
            return $this->responseJson($return);
        }
        $groupName = $data['groupName'];
        $return = ['errmsg' => '已经达到分组上限'];
        $count = DB::table("group")->count();

        if ($count < 255)
        {
            $arr_data = ['name' => $groupName, 'createTime' => date('Y-m-d H:i:s'), 'updateTime' => date('Y-m-d H:i:s')];
            $groupId = DB::table("group")->insertGetId($arr_data);
            if(empty($groupId)) {
                $return = ['errmsg'=>'分组创建失败，请稍后重试'];
            } else {
                $this->generateMeshForGroup($groupId);
                $return = ['msg'=> $this->groupDetail($groupId)];
            }
        }

        return $this->responseJson($return);
    }
    private function groupDetail($groupId)
    {
        $where = [['id', '=', $groupId], ['status', '=', 0]];
        return DB::table("group")->where($where)->first();
    }

    /** Modified By River
     * @param  $id
     * @return mixed
     */
    public function del($id = '')
    {
        $groupId = (int) $id;
        $arr_return = [];

        if($groupId)
        {
            if ($this->lightInGroup($groupId)) {
                $arr_return = ['errmsg' => '该分组灯具不为空,无法删除.'];
                return $this->responseJson($arr_return);
            }
            $this->dbDelGroup($groupId);
        } else {
            $arr_return = ['errmsg'=>'请先选择一个分组'];
        }
        return $this->responseJson($arr_return);
    }

    /*  @删除分组的数据库操作,成功时,也把该分组的 mesh 删除
     *  @param $groupId
     *  @return void
     * */
    private function dbDelGroup($groupId)
    {
        if($this->groupDetail($groupId))
        {
            $updateColumn = ['status' => 1, 'updateTime' => date('Y-m-d H:i:s')];
            $update = DB::table('group')->where('id', $groupId)->update($updateColumn);
            $update ? DB::table('mesh')->where('groupId', $groupId)->delete() : "";
        }
    }
    /*  @修改分组
     *  @param $request
     * @return array
     * */
    public function edit(Request $request)
    {
        $arr_return = [];
        $groupId = (int) $request->input('groupId');
        $groupName = $request->input('groupName');

        if ($groupId AND $groupName) {

            $updateColumn = ['name' => $groupName, 'updateTime' => date('Y-m-d H:i:s')];
            $arr_result = $this->groupDetail($groupId);
            if ($arr_result) {
                $return = DB::table('group')->where('id', $groupId)->update($updateColumn);
                $return ? '' : $arr_return = ['errmsg' => '分组修改失败'];
            }
        } else {
            $arr_return = ['errmsg' => '接口参数不正确'];
        }

        return $this->responseJson($arr_return);
    }
    /*  @获取分组列表
     *  @param $request
     *  @return array
     * */
    public function glist(Request $request)
    {
        $per = 13;
        $pageNo = (int) $request->pageNo;
        $pageNo = $pageNo ? $pageNo : 1;
        $count = DB::table("group")->where('status', 0)->count();
        $where = 'status = 0';
        $arr_group = $this->pagination($this->_table, $where, $count, $pageNo);
        $rows = $pageNo * $per;
        $arr_return['msg']['more'] = ($arr_group AND ($count > $rows)) ? 0 : 1;
        $arr_return['msg']['group'] = $arr_group;

        if ($request->input('type') == 'light') {
            $arr_light = DB::table("light")->where('status', '=', 0)->get();
            $arr_group_light = [];
            foreach($arr_group as $group) {
                foreach($arr_light as $light) {
                    if ($light->groupId == $group->id) {
                        $this->formatArrayField($light);
                        $arr_group_light[$group->id][] = $light;
                    }
                }
            }
            $arr_return = ['msg' => ['group' => $arr_group_light]];
        }

        return $this->responseJson($arr_return);
    }

    /* @查询分组灯具
     * @param  $id
     * @param  $request
     * @return array
     * */
    public function glight(Request $request, $group_id = 0)
    {
        $id = (int) $group_id;
        $pageNo = (int) $request->input('pageNo');
        $pageNo = $pageNo ? $pageNo : 1;

        if ($id)
        {
            $arr_return = $this->lightInGroupPagination($id, $pageNo);
            $arr_light = &$arr_return['msg']['lights'];
            if ($arr_light)
            {
                foreach ($arr_light as &$light) {
                    $this->formatArrayField($light);
                }
            }
        } else {
            $arr_return = ['errmsg' => '分组 ID 不正确'];
        }
        return $this->responseJson($arr_return);
    }
    /*  @属于某个分组的灯具数量
     *  @param $id
     *  @return mixed
     * */
    private function lightInGroup($id)
    {
        $where = [['groupId', '=', $id], ['status', '=', 0]];
        return DB::table("light")->where($where)->count();
    }
    /*  @分组灯具
     *  @param $id
     *  @param $pageNo
     *  @param $per
     *  @return array
     * */
    private function lightInGroupPagination($id, $pageNo = 1, $per = 13)
    {
        $count = $this->lightInGroup($id);
        $arr_return['msg']['more'] = 0;
        $arr_return['msg']['lights'] = [];

        if ($count) {
            $where = "groupId = {$id} AND status = 0";
            $lights = $this->pagination('lamp_light', $where, $count, $pageNo);
            $rows = $pageNo * $per;
            $arr_return['msg']['more'] = ($lights AND ($count < $rows)) ? 0 : 1;
            $arr_return['msg']['lights'] = $lights;
        }
        return $arr_return;
    }
    /*  @格式化数组字段
     *  @param $light
     *  return void
     * */
    private function formatArrayField(&$light)
    {
        $light->images = empty($light->images) ? [] : json_decode($light->images, true);
        $light->describe = empty($light->describe) ? [] : json_decode($light->describe, true);
        $light->subSwitch = empty($light->subSwitch) ? [] : json_decode($light->subSwitch, true);
    }

    /*  @初始化 mesh 地址
     *  @param $groupId
     *  @return void
     * */
    private function generateMeshForGroup($groupId)
    {

        for($i = 1; $i <= 255; $i++)
        {
            $arr_data[] = [
                'meshAddr' => $i,
                'groupId' => $groupId,
                'status' => 0,
                'createTime' => date('Y-m-d H:i:s'),
                'updateTime' => date('Y-m-d H:i:s')
            ];
        }
        DB::table("mesh")->insert($arr_data);
    }
}