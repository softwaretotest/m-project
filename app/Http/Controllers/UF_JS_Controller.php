<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class UF_JS_Controller extends Controller
{
    public function save(Request $request)
    {
        $validated = $request->validate([
            'uf_name' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'code'    => ['present', 'nullable', 'string'],
        ]);

        $dir  = app_path('Constant/JS');
        $path = $dir . DIRECTORY_SEPARATOR . $validated['uf_name'] . '.js';

        File::ensureDirectoryExists($dir);
        File::put($path, $validated['code'] ?? '');

        return response()->json([
            'ok'   => true,
            'path' => 'app/Constant/JS/' . $validated['uf_name'] . '.js',
        ]);
    }

    public function load(string $uf_name)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $uf_name)) {
            return response()->json(['code' => ''], 400);
        }

        $path = app_path('Constant/JS/' . $uf_name . '.js');
        $code = File::exists($path) ? File::get($path) : '';

        return response()->json([
            'ok' => true,
            'code' => $code,
        ]);
    }
}
