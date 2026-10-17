<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use App\Services\JwtService;
use Closure;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;

final class JwtAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->bearerToken(), 401, 'Unauthorized access');
        try {
            $claims = app(JwtService::class)->decode($request->bearerToken());
        } catch (ExpiredException|SignatureInvalidException|BeforeValidException|\UnexpectedValueException|\DomainException $e) {
            abort(401, 'Unauthorized access');
        }
        abort_unless(isset($claims->id,$claims->exp) && is_string($claims->id) && preg_match('/^[a-f0-9]{24}$/i', $claims->id), 401, 'Unauthorized access');
        $user = Organization::find($claims->id) ?? User::find($claims->id);
        abort_unless($user && $user->isActive && ! $user->blocked && ! $user->deletedAt, 401, 'Unauthorized access');
        if ($user instanceof User) {
            $org = Organization::find((string) $user->organization);
            abort_unless($org && $org->isActive && ! $org->blocked && ! $org->deletedAt, 401, 'Unauthorized access');
        }
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
