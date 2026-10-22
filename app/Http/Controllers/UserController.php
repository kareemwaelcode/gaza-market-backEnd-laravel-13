<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
public function index()
{
    $users = User::select(['id','user_full_name','user_phone','user_role','user_status','user_last_login_at','area_id',])->with('area:id,area_name_en')->orderByDesc('id')->paginate(10);
    return response()->view('cms.user.index', compact('users'));
    }

    public function create()
    {
    $areas = Area::ordered()->get(['id', 'area_name_en']);
    return response()->view('cms.user.create', compact('areas'));
    }

    public function store(Request $request)
    {
    $validator = validator($request->all(), $this->rules());

    if ($validator->fails()) {
        return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
    }

    if ($this->phoneTaken($request->input('user_phone'))) {
        return response()->json(['icon' => 'error', 'title' => 'This phone number is already registered'], 400);
    }

    $user = new User();
    $user->user_full_name = $request->input('user_full_name');
    $user->user_phone = $request->input('user_phone');
    $user->user_role = $request->input('user_role');
    $user->user_status = $request->input('user_status');
    $user->user_preferred_lang = $request->input('user_preferred_lang');
    $user->user_theme = $request->input('user_theme');
    $user->area_id = $request->input('area_id');

    if ($request->filled('user_password')) {
        $user->user_password = $request->input('user_password');
    }
    $isSaved = $user->save();

    if ($isSaved) {
        return response()->json(['icon' => 'success', 'title' => 'Created is Successfully'], 200);
    }

    return response()->json(['icon' => 'error', 'title' => 'Created is Failed'], 400);
    }

    public function edit(string $id)
    {
    $user = User::findOrFail($id);
    $areas = Area::ordered()->get(['id', 'area_name_en']);
    return response()->view('cms.user.edit', compact('user', 'areas'));
    }
    public function update(Request $request, string $id)
    {
    $validator = validator($request->all(), $this->rules());

    if ($validator->fails()) {
        return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
    }

    $user = User::findOrFail($id);

    if ($this->phoneTaken($request->input('user_phone'), $user->id)) {
        return response()->json(['icon' => 'error', 'title' => 'This phone number is already registered'], 400);
    }

    $user->user_full_name = $request->input('user_full_name');
    $user->user_phone = $request->input('user_phone');
    $user->user_role = $request->input('user_role');
    $user->user_status = $request->input('user_status');
    $user->user_preferred_lang = $request->input('user_preferred_lang');
    $user->user_theme = $request->input('user_theme');
    $user->area_id = $request->input('area_id');

    if ($request->filled('user_password')) {
        $user->user_password = $request->input('user_password');
    }

    $isUpdated = $user->save();

    if ($isUpdated) {
        return response()->json(['redirect' => route('user.index')], 200);
    }

    return response()->json(['icon' => 'error', 'title' => 'Updated is Failed'], 400);
    }

    public function destroy(string $id)
    {
    $user = User::findOrFail($id);

    DB::transaction(function () use ($user) {
        $user->user_status = 'deleted';
        $user->save();
        $user->delete();
    });

    return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }

    private function rules(): array
    {
    return [
        'user_full_name' => ['required', 'string', 'max:45'],
        'user_phone' => ['required', 'string', 'regex:/^(970|972)\d{9}$/'],
        'user_password' => ['nullable', 'string', 'min:8', 'max:255', 'regex:/^(?=.*[A-Za-z\x{0600}-\x{06FF}])(?=.*\d).+$/u'],
        'user_role' => ['required', Rule::in(['user', 'store_owner', 'admin'])],
        'user_status' => ['required', Rule::in(['active', 'suspended'])],
        'user_preferred_lang' => ['required', Rule::in(['en', 'ar'])],
        'user_theme' => ['required', Rule::in(['light', 'dark'])],
        'area_id' => ['nullable', 'integer', 'exists:areas,id'],
    ];
    }

    private function phoneTaken(string $phone, ?int $ignoreId = null): bool
    {
    $tail = substr($phone, 3);

    return User::withTrashed()
        ->whereIn('user_phone', ['970' . $tail, '972' . $tail])
        ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
        ->exists();
    }
    }