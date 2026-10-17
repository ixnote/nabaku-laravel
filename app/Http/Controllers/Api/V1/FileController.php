<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Cloudinary;
use App\Support\Access;
use App\Support\Api;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function upload(Request $r, Cloudinary $cloud)
    {
        $r->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp,gif,pdf|max:10240']);

        return Api::ok(['url' => $cloud->upload($r->file('file'), Access::organization($r))['url']], 'url generated');
    }

    public function signature(Request $r, Cloudinary $cloud)
    {
        return Api::ok($cloud->uploadSignature(Access::organization($r)), 'upload signature generated');
    }
}
