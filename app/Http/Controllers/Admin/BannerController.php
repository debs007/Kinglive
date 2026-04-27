<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('sort_order')->orderByDesc('created_at')->paginate(20);
        return view('admin.banners.index', compact('banners'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'     => ['nullable', 'string', 'max:100'],
            'image_url' => ['required', 'string'],
            'link_url'  => ['nullable', 'string', 'max:255'],
        ]);

        Banner::create([
            'title'      => $request->title,
            'image_url'  => $request->image_url,
            'link_url'   => $request->link_url,
            'sort_order' => Banner::max('sort_order') + 1,
        ]);

        return back()->with('success', 'Banner added.');
    }

    public function toggleActive(int $id)
    {
        $banner = Banner::findOrFail($id);
        $banner->update(['is_active' => ! $banner->is_active]);
        return back()->with('success', 'Banner status updated.');
    }

    public function destroy(int $id)
    {
        Banner::findOrFail($id)->delete();
        return back()->with('success', 'Banner deleted.');
    }

    /** Public API — active banners */
    public function apiIndex()
    {
        $banners = Banner::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'image_url', 'link_url']);

        return response()->json($banners);
    }
}
