<?php

namespace App\Http\Controllers;

use App\Models\NavigationItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NavigationItemController extends Controller
{
    public function index()
    {
        $navigationItems = NavigationItem::ordered()->paginate(10);
        return response()->view('cms.navigation_item.index', compact('navigationItems'));
    }

    public function create()
    {
        return response()->view('cms.navigation_item.create');
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), $this->rules());

        if (! $validator->fails()) {
            $item = new NavigationItem();
            $this->fill($item, $request);
            $isSaved = $this->saveWithUniqueSlug($item, $request->input('navigation_item_name_en'));

            if ($isSaved) {
                return response()->json(['icon' => 'success', 'title' => 'Created is Successfully'], 200);
            } else {
                return response()->json(['icon' => 'error', 'title' => 'Created is Failed'], 400);
            }
        } else {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }
    }

    public function edit(string $id)
    {
        $navigationItem = NavigationItem::findOrFail($id);
        return response()->view('cms.navigation_item.edit', compact('navigationItem'));
    }

    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), $this->rules());

        if (! $validator->fails()) {
            $item = NavigationItem::findOrFail($id);
            $this->fill($item, $request);
            $isUpdated = $item->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('navigation_item.index')], 200);
            } else {
                return response()->json(['icon' => 'error', 'title' => 'Updated is Failed'], 400);
            }
        } else {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }
    }

    public function destroy(string $id)
    {
        NavigationItem::findOrFail($id)->delete();
        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }

    private function rules(): array
    {
        return [
            'navigation_item_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'navigation_item_name_ar' => ['required', 'string', 'max:45'],
            'navigation_item_icon' => ['required', 'string', 'max:45', 'regex:/^[a-z0-9-]+$/'],
            'navigation_item_url' => ['nullable', 'string', 'max:255', 'regex:/^(#|https?:\/\/\S+|(?!\/\/)[A-Za-z0-9_\-\/\.]+(\?\S*)?(#\S*)?)$/'],
            'navigation_item_sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'navigation_item_is_active' => ['required', 'boolean'],
            'navigation_item_is_mobile' => ['required', 'boolean'],
            'navigation_item_is_desktop' => ['required', 'boolean'],
            'navigation_item_is_coming_soon' => ['required', 'boolean'],
        ];
    }

    private function fill(NavigationItem $item, Request $request): void
    {
        $item->navigation_item_name_en = $request->input('navigation_item_name_en');
        $item->navigation_item_name_ar = $request->input('navigation_item_name_ar');
        $item->navigation_item_icon = $request->input('navigation_item_icon');
        $item->navigation_item_url = $request->filled('navigation_item_url') ? $request->input('navigation_item_url') : null;
        $item->navigation_item_sort_order = $request->filled('navigation_item_sort_order') ? (int) $request->input('navigation_item_sort_order') : 0;
        $item->navigation_item_is_active = $request->boolean('navigation_item_is_active');
        $item->navigation_item_is_mobile = $request->boolean('navigation_item_is_mobile');
        $item->navigation_item_is_desktop = $request->boolean('navigation_item_is_desktop');
        $item->navigation_item_is_coming_soon = $request->boolean('navigation_item_is_coming_soon');
    }

    /**
     * The unique index on the slug is the real protection against duplicates.
     * If two requests pick the same slug at the same time, the loser retries
     * with a fresh slug instead of returning a 500.
     */
    private function saveWithUniqueSlug(NavigationItem $item, string $name): bool
    {
        $attempts = 3;

        for ($i = 1; $i <= $attempts; $i++) {
            $item->navigation_item_slug = $this->generateUniqueSlug($name);

            try {
                return $item->save();
            } catch (UniqueConstraintViolationException $e) {
                if ($i === $attempts) {
                    throw $e;
                }
            }
        }

        return false;
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 40, ''), '-');
        $slug = $base;
        $i = 2;

        while (NavigationItem::where('navigation_item_slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}