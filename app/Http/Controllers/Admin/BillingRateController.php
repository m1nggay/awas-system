<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingRate;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class BillingRateController extends Controller
{
    public function index()
    {
        return view('admin.rates.index', ['rates' => BillingRate::orderBy('min_consumption')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->fields($request);
        if ($data['rate_name'] === '') {
            flash('danger', 'Rate name is required.');
            return back();
        }
        BillingRate::create($data + ['created_by' => $request->user()->user_id]);
        log_activity($request->user()->user_id, 'rate_add', "Added billing rate: {$data['rate_name']}");
        flash('success', 'Billing rate bracket added.');
        return back();
    }

    public function update(Request $request, BillingRate $rate)
    {
        $data = $this->fields($request);
        if ($data['rate_name'] === '') {
            flash('danger', 'Rate name is required.');
            return back();
        }
        $rate->update($data);
        log_activity($request->user()->user_id, 'rate_edit', "Edited billing rate #{$rate->rate_id}");
        flash('success', 'Billing rate bracket updated.');
        return back();
    }

    public function destroy(BillingRate $rate)
    {
        try {
            $rate->delete();
            flash('success', 'Billing rate deleted.');
        } catch (QueryException $e) {
            flash('danger', 'Cannot delete: this rate is already used by existing bills. Deactivate it instead.');
        }
        return back();
    }

    private function fields(Request $request): array
    {
        $max = clean($request->input('max_consumption'));
        return [
            'rate_name'            => clean($request->input('rate_name')),
            'min_consumption'      => (float)$request->input('min_consumption', 0),
            'max_consumption'      => $max === '' ? null : (float)$max,
            'rate_per_cubic_meter' => (float)$request->input('rate_per_cubic_meter', 0),
            'base_charge'          => (float)$request->input('base_charge', 0),
            'penalty_percentage'   => (float)$request->input('penalty_percentage', 0),
            'effective_date'       => clean($request->input('effective_date')) ?: date('Y-m-d'),
            'is_active'            => $request->boolean('is_active'),
        ];
    }
}
