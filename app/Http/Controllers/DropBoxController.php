<?php

namespace App\Http\Controllers;

use Dcblogdev\Dropbox\Facades\Dropbox;
use Illuminate\Http\Request;

class DropBoxController extends Controller
{

    public function index()
    {
        $list = Dropbox::files()->listContents('/audit');
        return view('welcome', [
            'list'  => $list['entries'],
        ]);
    }

    public function upload(Request $request)
    {
        $path = '/audit';
        $file = $request->file('file');

        // upload file
        Dropbox::files()->upload($path, $file);

        // rename
        $fromPath = $path . '/' . $file->getFilename();
        $toPath = $path . '/' . $file->getClientOriginalName();
        Dropbox::files()->move($fromPath, $toPath);

        return redirect()->back();
    }
}
