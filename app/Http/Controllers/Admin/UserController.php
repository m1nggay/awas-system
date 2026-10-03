<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /** Stored role codes → shown as Administrator, Meter Reader, Consumer (see roleLabel()). */
    private const ROLES = ['admin', 'staff', 'resident'];

    public function index(Request $request)
    {
        $roleFil = clean($request->query('role'));
        // Only the three account types are managed here; membership applicants
        // are handled on the Applications page until they become consumers.
        $users = User::query()
            ->whereIn('role', self::ROLES)
            ->when(in_array($roleFil, self::ROLES, true), fn ($q) => $q->where('role', $roleFil))
            ->orderByDesc('created_at')->get();

        // Meter Readers' purok assignments: purok_id => user_id, and per user the purok names.
        $assignments = DB::table('meter_reader_puroks as a')->join('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->orderBy('p.purok_name')->get(['a.user_id', 'a.purok_id', 'p.purok_name']);

        return view('admin.users.index', [
            'users'       => $users,
            'roleFil'     => $roleFil,
            'puroks'      => Purok::orderBy('purok_name')->get(),
            'purokOwner'  => $assignments->pluck('user_id', 'purok_id'),
            'userPuroks'  => $assignments->groupBy('user_id'),
            'readerNames' => DB::table('users')->where('role', 'staff')->pluck('username', 'user_id'),
        ]);
    }

    /**
     * Audit trail (activity_logs). Administrators see everyone's (or one
     * user's via ?user_id=); a Meter Reader only ever sees their own.
     */
    public function logs(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();
        $userId = $isAdmin ? (int)$request->query('user_id', 0) : (int)$request->user()->user_id;
        $roleFil = $isAdmin ? clean($request->query('role')) : '';
        $action = clean($request->query('action'));
        $search = clean($request->query('q'));
        $from = clean($request->query('from'));
        $to = clean($request->query('to'));

        $logs = DB::table('activity_logs as l')
            ->leftJoin('users as u', 'u.user_id', '=', 'l.user_id')
            ->when($userId > 0, fn ($q) => $q->where('l.user_id', $userId))
            ->when(in_array($roleFil, self::ROLES, true), fn ($q) => $q->where('u.role', $roleFil))
            ->when($action !== '', fn ($q) => $q->where('l.action', $action))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('l.details', like_operator(), "%$search%")
                ->orWhere('l.ip_address', like_operator(), "%$search%")))
            ->when($from !== '', fn ($q) => $q->where('l.created_at', '>=', $from . ' 00:00:00'))
            ->when($to !== '', fn ($q) => $q->where('l.created_at', '<=', $to . ' 23:59:59'))
            ->orderByDesc('l.created_at')->orderByDesc('l.log_id')
            ->select('l.*', 'u.username', 'u.full_name', 'u.role')
            ->paginate(25)->withQueryString();

        return view('admin.users.logs', [
            'logs'     => $logs,
            'isAdmin'  => $isAdmin,
            'user'     => $userId > 0 ? User::find($userId) : null,
            'users'    => $isAdmin ? User::orderBy('full_name')->get(['user_id', 'full_name', 'username']) : collect(),
            'actions'  => DB::table('activity_logs')->when(!$isAdmin, fn ($q) => $q->where('user_id', $userId))
                ->distinct()->orderBy('action')->pluck('action'),
            'userId'   => $userId,
            'roleFil'  => $roleFil,
            'action'   => $action,
            'search'   => $search,
            'from'     => $from,
            'to'       => $to,
        ]);
    }

    public function store(Request $request)
    {
        $username = clean($request->input('username'));
        $fullName = clean($request->input('full_name'));
        $role = clean($request->input('role', 'staff'));
        $password = (string)$request->input('password', '');

        if ($username === '' || $fullName === '' || !in_array($role, self::ROLES, true)) {
            flash('danger', 'Please fill all required fields.');
            return back();
        }
        if ($policy = passwordPolicyError($password)) {
            flash('danger', $policy);
            return back();
        }
        if (User::where('username', $username)->exists()) {
            flash('danger', 'That username already exists.');
            return back();
        }

        $user = User::create([
            'username'       => $username,
            'password_hash'  => Hash::make($password),
            'full_name'      => $fullName,
            'email'          => clean($request->input('email')) ?: null,
            'contact_number' => clean($request->input('contact_number')) ?: null,
            'role'           => $role,
            'status'         => 'active',
        ]);
        log_activity($request->user()->user_id, 'user_add', "Created user account: $username (" . roleLabel($role) . ')');
        $moved = $role === 'staff' ? $this->assignPuroks($user, (array)$request->input('puroks', []), $request->user()->user_id) : [];
        flash('success', 'User account created.' . ($moved ? ' Moved from another meter reader: ' . implode(', ', $moved) . '.' : ''));
        return back();
    }

    /** Sets which puroks a Meter Reader reads. A purok moves away from any other reader. */
    public function updatePuroks(Request $request, User $user)
    {
        if (!$user->isMeterReader()) {
            flash('danger', 'Puroks can only be assigned to Meter Reader accounts.');
            return back();
        }
        $moved = $this->assignPuroks($user, (array)$request->input('puroks', []), $request->user()->user_id);
        $names = DB::table('meter_reader_puroks as a')->join('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->where('a.user_id', $user->user_id)->orderBy('p.purok_name')->pluck('p.purok_name');
        flash('success', "Puroks for {$user->username}: " . ($names->isEmpty() ? 'none' : $names->implode(', ')) . '.'
            . ($moved ? ' Moved from another meter reader: ' . implode(', ', $moved) . '.' : ''));
        return back();
    }

    /** @return string[] names of puroks taken over from another reader */
    private function assignPuroks(User $user, array $purokIds, int $adminId): array
    {
        $ids = Purok::whereIn('purok_id', array_map('intval', $purokIds))->pluck('purok_id')->all();
        $moved = DB::table('meter_reader_puroks as a')->join('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->whereIn('a.purok_id', $ids ?: [0])->where('a.user_id', '!=', $user->user_id)
            ->pluck('p.purok_name')->all();

        DB::transaction(function () use ($user, $ids) {
            DB::table('meter_reader_puroks')->where('user_id', $user->user_id)->delete();
            DB::table('meter_reader_puroks')->whereIn('purok_id', $ids ?: [0])->delete();
            foreach ($ids as $id) {
                DB::table('meter_reader_puroks')->insert(['user_id' => $user->user_id, 'purok_id' => $id]);
            }
        });

        $names = Purok::whereIn('purok_id', $ids ?: [0])->orderBy('purok_name')->pluck('purok_name');
        log_activity($adminId, 'user_puroks', "Assigned puroks to meter reader {$user->username}: "
            . ($names->isEmpty() ? 'none' : $names->implode(', ')));
        return $moved;
    }

    public function toggleStatus(Request $request, User $user)
    {
        if ((int)$user->user_id === (int)$request->user()->user_id) {
            flash('danger', 'You cannot deactivate your own account.');
            return back();
        }
        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);
        log_activity($request->user()->user_id, 'user_toggle_status', ($user->status === 'active' ? 'Activated ' : 'Deactivated ') . "user {$user->username} (" . roleLabel($user->role) . ')');
        flash('success', 'User status updated.');
        return back();
    }

    public function resetPassword(Request $request, User $user)
    {
        $newPassword = (string)$request->input('new_password', '');
        if ($policy = passwordPolicyError($newPassword)) {
            flash('danger', $policy);
            return back();
        }
        $user->update(['password_hash' => Hash::make($newPassword)]);
        log_activity($request->user()->user_id, 'user_reset_password', "Reset the password of {$user->username} (" . roleLabel($user->role) . ')');
        flash('success', 'Password reset successfully.');
        return back();
    }
}
