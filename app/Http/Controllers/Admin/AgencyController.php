<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgencyController extends Controller
{
    public function index()
    {
        $agencies = Agency::withCount('members')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.agencies.index', compact('agencies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'description'     => ['nullable', 'string', 'max:500'],
            'logo_url'        => ['nullable', 'url'],
            'portal_email'    => ['nullable', 'email'],
            'portal_password' => ['nullable', 'string', 'min:6'],
        ]);

        \Illuminate\Support\Facades\Log::info('Agency store', [
            'portal_email'    => $request->portal_email,
            'portal_password' => $request->portal_password ? 'set' : 'empty',
            'all'             => $request->all(),
        ]);

        Agency::create([
            'name'        => $request->name,
            'description' => $request->description,
            'logo_url'    => $request->logo_url,
            'code'        => strtoupper(Str::random(8)),
            'email'       => $request->portal_email ?: null,
            'password'    => $request->portal_password
                                ? \Illuminate\Support\Facades\Hash::make($request->portal_password)
                                : null,
        ]);

        return back()->with('success', 'Agency created successfully.');
    }

    public function update(Request $request, int $id)
    {
        $agency = Agency::findOrFail($id);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'logo_url'    => ['nullable', 'url'],
            'is_active'   => ['boolean'],
        ]);

        $agency->update($data);

        return back()->with('success', 'Agency updated.');
    }

    public function regenerateCode(int $id)
    {
        $agency = Agency::findOrFail($id);
        $agency->update(['code' => strtoupper(Str::random(8))]);

        return back()->with('success', 'Code regenerated: ' . $agency->code);
    }

    public function destroy(int $id)
    {
        Agency::findOrFail($id)->delete();
        return back()->with('success', 'Agency deleted.');
    }
}