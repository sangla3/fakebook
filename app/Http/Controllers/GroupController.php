<?php

namespace App\Http\Controllers;

use App\Http\Enums\GroupUserRole;
use App\Http\Enums\GroupUserStatus;
use App\Http\Requests\InviteUsersRequest;
use App\Notifications\RequestToJoinGroup;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use Carbon\Carbon;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Http\Resources\GroupUserResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\GroupUser;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GroupController extends Controller
{
    
    public function profile(Request $request, Group $group)
    {
        $group->load('currentUserGroup');

        $userId = Auth::id();

        if ($group->hasApprovedUser($userId)) {
        $posts = Post::postsForTimeline($userId)
            ->where('group_id', $group->id)
            ->paginate(5);
        $posts = PostResource::collection($posts);
        } else {
            return Inertia::render('Group/View', [
                'success' => session('success'),
                'group' => new GroupResource($group),
                'posts' => null,
                'users' => [],
                'requests' => []
            ]);
        }

        if ($request->wantsJson()) {
            return $posts;
        }

        $users = User::query()
            ->select(['users.*', 'gu.role', 'gu.status', 'gu.group_id'])
            ->join('group_users AS gu', 'gu.user_id', 'users.id')
            ->orderBy('users.name')
            ->where('group_id', $group->id)
            ->get();
        $requests = $group->pendingUsers()->orderBy('name')->get();

        return Inertia::render('Group/View', [
            'success' => session('success'),
            'group' => new GroupResource($group),
            'posts' => $posts,
            'users' => GroupUserResource::collection($users),
            'requests' => UserResource::collection($requests)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $data['user_id'] = Auth::id();
        $group = Group::create($data);

        $groupUserData = [
            'status' => GroupUserStatus::APPROVED->value,
            'role' => GroupUserRole::ADMIN->value,
            'user_id' => Auth::id(),
            'group_id' => $group->id,
            'created_by' => Auth::id()
        ];

        GroupUser::create($groupUserData);
        $group->status = $groupUserData['status'];
        $group->role = $groupUserData['role'];

        // return response(new GroupResource($group), 201);
        return back();
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGroupRequest $request, Group $group)
    {
        $group->update($request->validated());

        return back()->with('success', "Cập nhật thành công");
    }

    public function updateImage(Request $request, Group $group)
    {
        if (!$group->isAdmin(Auth::id())) {
            return response("Bạn không có quyền thực hiện hành động này", 403);
        }
        $data = $request->validate([
            'cover' => ['nullable', 'image'],
            'thumbnail' => ['nullable', 'image']
        ]);

        $thumbnail = $data['thumbnail'] ?? null;
        $cover = $data['cover'] ?? null;

        $success = '';
        if ($cover) {
            if ($group->cover_path) {
                Storage::disk('public')->delete($group->cover_path);
            }
            $path = $cover->store('group-' . $group->id, 'public');
            $group->update(['cover_path' => $path]);
            $success = 'Ảnh bìa đã được cập nhật';
        }

        if ($thumbnail) {
            if ($group->thumbnail_path) {
                Storage::disk('public')->delete($group->thumbnail_path);
            }
            $path = $thumbnail->store('group-' . $group->id, 'public');
            $group->update(['thumbnail_path' => $path]);
            $success = 'Ảnh đại diện đã được cập nhật';
        }

        return back()->with('success', $success);
    }

    public function inviteUsers(InviteUsersRequest $request, Group $group)
    {
        $data = $request->validated();

        $user = $request->user;

        $groupUser = $request->groupUser;

        if ($groupUser) {
            $groupUser->delete();
        }

        $hours = 240;
        $token = Str::random(256);

        GroupUser::create([
            'status' => GroupUserStatus::PENDING->value,
            'role' => GroupUserRole::USER->value,
            'token' => $token,
            'token_expire_date' => Carbon::now()->addHours($hours),
            'user_id' => $user->id,
            'group_id' => $group->id,
            'created_by' => Auth::id(),
        ]);


        return back()->with('success', 'Người dùng đã được mời tham gia nhóm');
    }

    public function requestJoin(string $slug)
    {
        $group = Group::where('slug', $slug)->firstOrFail();
        $user = auth()->user();

        // Kiểm tra nếu người dùng đã gửi yêu cầu hoặc đã tham gia nhóm
        $existingRequest = GroupUser::where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->first();

        if ($existingRequest) {
            return redirect()->back()->with('error', 'Bạn đã gửi yêu cầu hoặc đã tham gia nhóm này.');
        }

        // Tạo yêu cầu tham gia nhóm
        GroupUser::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'role' => GroupUserRole::USER->value,
            'status' => 'pending',
            'token' => null,
            'token_used' => null,
            'token_expire_date' => null,
            'created_by' => $user->id,
        ]);

        return redirect()->back()->with('success', 'Yêu cầu tham gia nhóm đã được gửi.');
    }

    public function approveInvitation(string $token)
    {
        $groupUser = GroupUser::query()
            ->where('token', $token)
            ->where('token_used', null)
            ->where('token_expire_date', '>', Carbon::now())
            ->first();
    
        if (!$groupUser) {
            return redirect()->back()->with('success', 'Token không hợp lệ hoặc đã hết hạn.');
        }
    
        $groupUser->status = GroupUserStatus::APPROVED->value;
        $groupUser->token_used = Carbon::now();
        $groupUser->save();
    
        return redirect(route('group.profile', $groupUser->group->slug))
            ->with('success', 'Bạn đã chấp nhận tham gia nhóm "' . $groupUser->group->name . '"');
    }
    
    public function rejectInvitation(string $token)
    {
        $groupUser = GroupUser::query()
            ->where('token', $token)
            ->where('token_used', null)
            ->where('token_expire_date', '>', Carbon::now())
            ->first();

        if (!$groupUser) {
            return redirect()->back()->with('success', 'Token không hợp lệ hoặc đã hết hạn.');
        }

        $groupUser->delete();

        return redirect()->back()->with('success', 'Bạn đã từ chối lời mời tham gia nhóm "' . $groupUser->group->name . '".');
    }
    

    public function join(Group $group)
    {
        $user = \request()->user();

        $status = GroupUserStatus::APPROVED->value;
        $successMessage = 'Bạn đã tham gia nhóm "' . $group->name . '"';
        if (!$group->auto_approval) {
            $status = GroupUserStatus::PENDING->value;
            $successMessage = 'Đã gửi yêu cầu tham gia';
        }

        GroupUser::create([
            'status' => $status,
            'role' => GroupUserRole::USER->value,
            'user_id' => $user->id,
            'group_id' => $group->id,
            'created_by' => $user->id,
        ]);

        return back()->with('success', $successMessage);
    }

    public function approveRequest(Request $request, Group $group)
    {
        if (!$group->isAdmin(Auth::id())) {
            return response("Bạn không có quyền thực hiện hành động này", 403);
        }

        $data = $request->validate([
            'user_id' => ['required'],
            'action' => ['required']
        ]);

        $groupUser = GroupUser::where('user_id', $data['user_id'])
            ->where('group_id', $group->id)
            ->where('status', GroupUserStatus::PENDING->value)
            ->first();

        if ($groupUser) {
            $approved = false;
            if ($data['action'] === 'approve') {
                $approved = true;
                $groupUser->status = GroupUserStatus::APPROVED->value;
            } else {
                $groupUser->status = GroupUserStatus::REJECTED->value;
            }
            $groupUser->save();

            $user = $groupUser->user;
            // $user->notify(new RequestApproved($groupUser->group, $user, $approved));

            // return back();
            return back()->with('success', 'Đã '.( $approved ? 'chấp nhận' : 'từ chối' ).' "' . $user->name . '" tham gia nhóm ');
        }

        return back();
    }

    public function changeRole(Request $request, Group $group)
    {
        if (!$group->isAdmin(Auth::id())) {
            return response("Bạn không có quyền thực hiện hành động này", 403);
        }
    
        $user_id = $request->input('user_id');
        $role = $request->input('role');
    
        if ($group->isOwner($user_id)) {
            return response("Bạn không thể phân quyền", 403);
        }
    
        $groupUser = GroupUser::where('user_id', $user_id)
            ->where('group_id', $group->id)
            ->first();
    
        if ($groupUser) {
            $groupUser->role = $role;
            $groupUser->save();
        }
    
        return back();
    }

    public function removeUser(Request $request, Group $group)
    {
        if (!$group->isAdmin(Auth::id())) {
            return response("Bạn không có quyền thực hiện hành động này", 403);
        }

        $data = $request->validate([
            'user_id' => ['required'],
        ]);

        $user_id = $data['user_id'];

        $groupUser = GroupUser::where('user_id', $user_id)
            ->where('group_id', $group->id)
            ->first();

        if ($groupUser) {
            $user = $groupUser->user;
            $groupUser->delete();
        }

        return back();
    }
    
}
