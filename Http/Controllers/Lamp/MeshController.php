<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 9/20/16
 * Time: 5:24 PM
 */

namespace App\Http\Controllers\Lamp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Validator;

class MeshController extends Controller
{

    /*  @分配 mesh - 每个分组的 mesh 为 0 - 255
     *  @param $num
     *  @param $groupId
     *  @return array
     * */
    public function get($num = 10, $groupId = 0)
    {

        if ((int) $groupId AND (int) $num)
        {
            $return = $this->getAssignMesh($num, $groupId);
            $arr_return = $return ? ['msg' => $return] : ['errmsg' => '必须先释放 Mesh'];
        } else {
            $arr_return = array('errmsg' => '接口参数格式不正确');
        }

        return $this->responseJson($arr_return);
    }
    private function getAssignMesh($num, $groupId)
    {
        $where = [
            ['status', '=', '1'],
            ['userId', '=', $this->getUserId()]
        ];
        $meshAddr = DB::table('mesh')->where($where)->get(['meshAddr']);
        $meshAddr = $this->getAssocDataByObject($meshAddr);
        $meshAddr = array_column($meshAddr, 'meshAddr');
        $result = $num - count($meshAddr);

        if ($result AND ($result > 0)) {
            $return = $this->getMesh($result, $groupId);
            $return['meshAddr'] = array_merge($meshAddr, $return['meshAddr']);
        } else {
            $meshAddr = array_slice($meshAddr, 0, 10);
            $return = array('meshAddr' => $meshAddr, 'groupId' => $groupId);;
        }
        return $return;
    }

    private function getMesh($num, $groupId)
    {
        $userId = $this->getUserId();
        $arr_id = [];
        $assign = false;

        $where = [
            ['status', '=', 0],
            ['groupId', '=', $groupId]
        ];
        DB::table('mesh')->where($where)->chunk($num, function($meshs) use ($userId, &$arr_id, &$assign) {

            $arr_id = [];
            foreach($meshs as $m)
            {
                $arr_id['mesh'][] = $m->id;
                $arr_id['mesh_addr'][] = $m->meshAddr;
                $arr_id['groupId'][] = $m->groupId;
            }

            $assign = DB::table('mesh')->whereIn('id', $arr_id['mesh'])->update(['status' => 1, 'userId' => $userId]);
            return  false;
        });

        if ($assign)
        {
            $groupId = array_unique($arr_id['groupId']);
            return array('meshAddr' => $arr_id['mesh_addr'], 'groupId' => array_shift($groupId));
        }
    }

    /*  @上报被分配的 mesh
     *  @param $request
     *  @param $groupId
     *  @return array
     * */
    public function report(Request $request, $groupId)
    {
        $arr_return = [];
        $reportMesh = $request->reportMesh;

        if (is_array($reportMesh) AND $reportMesh AND $groupId)
        {
            $arr_update = [];
            $arr_valid_mesh = [];
            foreach ($reportMesh as $item)
            {
                if ($item['meshAddr'] < 255)
                {
                    $arr_valid_mesh[] = $item['meshAddr'];
                    $arr_update[$item['meshAddr']] = [
                        'user' => $this->getUserId(),
                        'status' => 2,
                        'mac' => $item['mac']
                    ];
                }
            }

            try {
                foreach($arr_update as $key => $update) {
                    $where = [
                        ['meshAddr', '=', $key],
                        ['groupId','=', $groupId],
                        ['status','=', 1],
                    ];
                    DB::table('mesh')->where($where)->update($update);
                }
            } catch (\Exception $e) {
                $arr_return = ['errmsg' => '系统内部有错误,稍后重试'];
            }
        } else {
            $arr_return = ['errmsg' => 'meshAddr & mac & groupId 都不能为空'];
        }
        return $this->responseJson($arr_return);
    }

    /*  @设备删除,删除灯具
     *  @param $request
     *  @return mixed
     * */
    public function delDevice(Request $request)
    {
        $arr_return = [];
        if ($request->groupId AND $request->meshAddr)
        {
            // 释放 mesh
            $where = [
                ['meshAddr', '=', $request->meshAddr],
                ['groupId', '=', $request->groupId]
            ];
            $updateData = ['userId' => 0, 'status' => 0];
            try {
                DB::table("mesh")->where($where)->update($updateData);
                // 删除灯具
                DB::table("light")->where($where)->update(['status' => 0]);
            } catch(\Exception $e) {
                $arr_return = ['errmsg' => '请刷新后,重试.'];
            }
        } else {
            $arr_return = ['errmsg' => "接口参数不正确"];
        }
        return $this->responseJson($arr_return);
    }
}