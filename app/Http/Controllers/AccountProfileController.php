<?php

namespace App\Http\Controllers;

use App\Services\ApplicationFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The profile header for every role: the signed-in user's name and profile picture. */
class AccountProfileController extends Controller
{
    /**
     * Change the signed-in user's name. For a consumer it is also the name on
     * their water account (bills, receipts), so both are updated and the old
     * name is kept in the activity log.
     */
    public function updateName(Request $request)
    {
        $name = preg_replace('/\s+/', ' ', clean($request->input('full_name')));
        $error = match (true) {
            mb_strlen($name) < 2 => 'Please enter your full name.',
            mb_strlen($name) > 150 => 'The name is too long (150 characters at most).',
            !preg_match("/^[\p{L}][\p{L}\s.'\-]*$/u", $name) => 'The name can only have letters, spaces, periods, hyphens and apostrophes.',
            default => null,
        };
        if ($error) {
            return back()->withErrors([$error])->withInput();
        }

        $user = $request->user();
        $old = $user->full_name;
        if ($name === $old) {
            return back();
        }
        DB::transaction(function () use ($user, $name) {
            DB::table('users')->where('user_id', $user->user_id)->update(['full_name' => $name]);
            if ($user->role === 'resident') {
                DB::table('consumers')->where('user_id', $user->user_id)->update(['full_name' => $name]);
            }
        });

        log_activity($user->user_id, 'profile_update', "Changed name from \"$old\" to \"$name\"");
        flash('success', 'Your name was updated.');
        return back();
    }

    /** The signed-in user's own picture (private file). */
    public function show(Request $request, ApplicationFiles $files)
    {
        return $files->in('profile-photos')->response($request->user()->profile_photo, [
            'Cache-Control' => 'private, max-age=86400',
        ], 'No profile picture.');
    }

    /** Upload or replace it (JPG, PNG or WEBP, up to 5 MB). */
    public function update(Request $request, ApplicationFiles $files)
    {
        $errors = [];
        $name = $files->in('profile-photos')->store($request->file('photo'), '', 'Profile picture', true, $errors);
        if ($errors) {
            return back()->withErrors($errors);
        }

        $user = $request->user();
        $old = $user->profile_photo;
        DB::table('users')->where('user_id', $user->user_id)->update(['profile_photo' => $name]);
        $files->in('profile-photos')->delete($old);

        log_activity($user->user_id, 'profile_update', 'Changed profile picture');
        flash('success', 'Profile picture updated.');
        return back();
    }

    public function destroy(Request $request, ApplicationFiles $files)
    {
        $user = $request->user();
        $files->in('profile-photos')->delete($user->profile_photo);
        DB::table('users')->where('user_id', $user->user_id)->update(['profile_photo' => null]);

        log_activity($user->user_id, 'profile_update', 'Removed profile picture');
        flash('success', 'Profile picture removed.');
        return back();
    }
}
