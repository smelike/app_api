<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 10/3/16
 * Time: 4:49 PM
 */

namespace App\Http\Controllers;


use Illuminate\Routing\Controller;

class WelcomeController extends Controller
{

    public function index()
    {
        $items = array(12,34,56,78,90);
        //dd($items);
        \Log::debug($items);
        \Log::info('just an information message.');
        \Log::warning('Something may be going wrong');
        \Log::critical('Danger, Will Robinson! Danger!');

        \Debugbar::error('Something is definitely wrong.');
        return view('welcome');
    }
}