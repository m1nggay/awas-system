<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class ResidentController extends Controller
{
    /** The water account linked to the logged-in resident (never taken from user input). */
    protected function consumer(Request $request): ?object
    {
        return DB::table('consumers as c')
            ->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->where('c.user_id', $request->user()->user_id)
            ->first(['c.*', 'p.purok_name']);
    }
}
