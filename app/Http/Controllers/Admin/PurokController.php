<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purok;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Puroks: a tab of System Settings (administrators only). */
class PurokController extends Controller
{
    /** Old links to the Puroks page land on its System Settings tab. */
    public function index()
    {
        return redirect()->to(self::back());
    }

    /** What System Settings needs to show the Puroks tab. */
    public static function sectionData(): array
    {
        return ['puroks' => DB::table('puroks as p')
            ->select('p.*', DB::raw('(SELECT COUNT(*) FROM consumers c WHERE c.purok_id = p.purok_id) AS consumer_count'))
            ->orderBy('p.purok_name')->get()];
    }

    private static function back(): string
    {
        return route('admin.settings.edit') . '#puroks';
    }

    public function store(Request $request)
    {
        return $this->save($request, new Purok(), 'Purok added.');
    }

    public function update(Request $request, Purok $purok)
    {
        return $this->save($request, $purok, 'Purok updated.');
    }

    public function destroy(Purok $purok)
    {
        try {
            $purok->delete();
            flash('success', 'Purok deleted.');
        } catch (QueryException $e) {
            flash('danger', 'Cannot delete: consumers are still assigned to this purok.');
        }
        return redirect()->to(self::back());
    }

    private function save(Request $request, Purok $purok, string $message)
    {
        $name = clean($request->input('purok_name'));
        if ($name === '') {
            flash('danger', 'Purok name is required.');
            return redirect()->to(self::back());
        }
        try {
            $purok->fill(['purok_name' => $name, 'description' => clean($request->input('description')) ?: null])->save();
            flash('success', $message);
        } catch (QueryException $e) {
            flash('danger', 'A purok with that name already exists.');
        }
        return redirect()->to(self::back());
    }
}
