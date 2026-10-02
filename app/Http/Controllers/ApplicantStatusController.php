<?php

namespace App\Http\Controllers;

use App\Models\MembershipApplication;
use Illuminate\Http\Request;

/**
 * Where a membership applicant follows their application. Once an admin
 * activates the account the role becomes 'resident' and this page forwards
 * to the resident dashboard.
 */
class ApplicantStatusController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'applicant') {
            return redirect()->route($user->dashboardRoute());
        }

        $app = MembershipApplication::where('user_id', $user->user_id)
            ->join('puroks', 'puroks.purok_id', '=', 'membership_applications.purok_id')
            ->select('membership_applications.*', 'puroks.purok_name')
            ->orderByDesc('membership_applications.created_at')
            ->first();

        return view('applicant.status', ['app' => $app]);
    }
}
