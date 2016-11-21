<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class Customer extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return ['id' => 'required'];
        //echo 23452345;
        return [
            'id' => 'required',
            'tel' => array('required','regex:/^1[3-8]\d{9}$/i'),
            'name' => 'required|between:2,20',
            'addrs' => 'required'
        ];
    }
}
