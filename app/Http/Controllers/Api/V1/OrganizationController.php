<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Cloudinary;
use App\Support\Access;
use App\Support\Api;
use App\Support\OrganizationView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OrganizationController extends Controller
{
    public function index(Request $r)
    {
        return response()->json(Api::page(Api::filters(Organization::where('isActive', true)->where('blocked', false)->whereNull('deletedAt'), $r, ['name', 'type']), $r, OrganizationView::public(...), 500));
    }

    public function show(Request $r, ?string $id = null)
    {
        if ($id === null) {
            return app(AuthController::class)->profile($r);
        }
        $org = Access::find(Organization::class, $id);
        abort_unless($org->isActive && ! $org->blocked && ! $org->deletedAt, 404, 'Organization not available');

        return Api::ok(OrganizationView::public($org), 'Organization details fetched successfully');
    }

    public function update(Request $r)
    {
        Access::owner($r);
        $v = $r->validate(['name' => 'required|string|min:3|max:200', 'phone' => 'sometimes|string|max:30', 'description' => 'sometimes|string|max:20000', 'type' => 'sometimes|in:faith-based,non-profit,others']);
        $org = Organization::findOrFail(Access::organization($r));
        $org->update($v);

        return Api::ok($org, 'Account updated successfully');
    }

    public function avatar(Request $r, Cloudinary $cloud)
    {
        Access::owner($r);
        $r->validate(['file' => 'required|image|max:10240']);
        $image = $cloud->upload($r->file('file'), Access::organization($r));
        Organization::findOrFail(Access::organization($r))->update(['profile_image' => $image['url']]);

        return Api::ok(null, 'profile updated successfully');
    }

    public function password(Request $r)
    {
        $v = $r->validate(['password' => 'required|string|min:6|max:50']);
        $r->user()->update(['password' => Hash::make($v['password'])]);

        return Api::ok(null, 'password changed successfully');
    }

    public function adminPassword(Request $r)
    {
        $v = $r->validate(['user' => 'required|string', 'password' => 'required|string|min:6|max:50']);
        Access::find(Organization::class, $v['user'])->update(['password' => Hash::make($v['password'])]);

        return Api::ok(null, 'password changed successfully');
    }

    public function block(Request $r, string $id)
    {
        $org = Access::find(Organization::class, $id);
        abort_if((string) $org->getKey() === (string) $r->user()->getKey(), 409, 'Cannot block your own administrator account');
        $org->update(['blocked' => ! $org->blocked]);

        return Api::ok($org, 'Organization '.($org->blocked ? 'blocked' : 'unblocked').' successfully');
    }

    public function delete(Request $r, string $id)
    {
        $org = Access::find(Organization::class, $id);
        abort_if((string) $org->getKey() === (string) $r->user()->getKey(), 409, 'Cannot delete your own administrator account');
        // Retain the financial audit trail and all references.
        $org->update(['blocked' => true, 'isActive' => false, 'deletedAt' => now()]);

        return Api::ok(null, 'Organization deleted successfully');
    }
}
