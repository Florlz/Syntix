<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SessionRevoker
{
    public function revoke(User $user): int
    {
        return DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
