<?php

namespace App\Http\Controllers;

use App\Models\LoanCollateralCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoanCollateralCategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $key = Str::slug($data['label'], '_');

        if (LoanCollateralCategory::where('key', $key)->exists()) {
            return back()->with('error', 'A collateral category with that name already exists.');
        }

        LoanCollateralCategory::create([
            'key'       => $key,
            'label'     => $data['label'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Collateral category added.');
    }

    public function toggle(LoanCollateralCategory $collateralCategory)
    {
        $collateralCategory->update(['is_active' => ! $collateralCategory->is_active]);

        return back()->with('success', $collateralCategory->is_active
            ? "\"{$collateralCategory->label}\" is active again."
            : "\"{$collateralCategory->label}\" hidden from new collateral — existing records keep showing it.");
    }
}
