<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 10/26/16
 * Time: 2:48 PM
 */

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Validator;

class CommentController extends Controller
{

    private $_table = 'lamp_comment';

    public function index($customerId = 0)
    {
        $customerId = (int) $customerId;
        $arr_return = [];

        if ($customerId)
        {
            $where = [
                ['customerId', '=', $customerId],
                ['status', '=', 0],
            ];
            $comments = DB::table('comment')->where($where)->get();
            if ($comments) {
                foreach ($comments as &$comment) {
                    $salesman = DB::table('user')->where('id', $comment->salesmanId)->first();
                    $comment->salesmanName = $salesman->name ? $salesman->name : $salesman->tel;
                }
            }
            $comments = $comments ? ['comment' => $comments] : $this->_msg;
            $arr_return = ['msg' => $comments];
        }
        return $this->responseJson($arr_return);
    }

    public function store(Request $request, $customerId)
    {
        $comment = $request->input('content');
        $customerId = (int)$customerId;

        if ($comment AND $customerId) {
            $arr_comment = [
                'comment' => $comment,
                'customerId' => $customerId,
                'salesmanId' => $this->getUserId(),
                'createTime' => date('Y-m-d H:i:s'),
                'updateTime' => date('Y-m-d H:i:s'),
            ];
            $id = DB::table('comment')->insertGetId($arr_comment);
            $arr_return = ['msg' => ['id' => $id]];
        } else {
            $arr_return = ['errmsg' => '接口参数有错误'];
        }

        return $this->responseJson($arr_return);
    }

    public function updateComment(Request $request, $commentId)
    {
        $commentId = (int) $commentId;
        $comment = $request->comment;

        $arr_return = [];
        if ($comment AND $commentId) {
            $maxCommentId = DB::table("comment")->max('id');
            if ($maxCommentId > $commentId) {
                $updateColumn = [
                    'comment' => $comment,
                    'salesmanId' => $this->getUserId(),
                    'updateTime' => date('Y-m-d H:i:s')
                ];
                DB::table('comment')->where('id', $commentId)->update($updateColumn);
            } else {
                $arr_return = ['errmsg' => '该评论不存在'];
            }
        } else {
            $arr_return = ['errmsg' => ' comment 或 commentId 不能为空'];
        }
        return $this->responseJson($arr_return);
    }

    public function delComment($comment_id)
    {
        $commentId = (int) $comment_id;
        $maxCommentId = DB::table('comment')->max('id');
        $arr_return = [];
        if ($commentId < $maxCommentId)
        {
            $update = [
                'status' => 1,
                'updateTime' => date('Y-m-d H:i:s')
            ];
            DB::table('comment')->where('id', $commentId)->update($update);
        } else {
            $arr_return = ['errmsg' => '接口参数不正确'];
        }
        return $this->responseJson($arr_return);
    }
}