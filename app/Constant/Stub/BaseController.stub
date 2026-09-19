<?php

namespace App\Http\Controllers;

abstract class BaseController extends Controller
{
    protected function responseSuccess($data, $message = 'Success')
    {
        return response()->json(['status' => 'success', 'data' => $data, 'message' => $message]);
    }

    protected function responseError($message, $code = 400)
    {
        return response()->json(['status' => 'error', 'message' => $message], $code);
    }
}
