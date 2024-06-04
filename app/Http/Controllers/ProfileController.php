<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\PostAttachmentResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Follower;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;


class ProfileController extends Controller
{
    public function index(User $user)
    {   
        $isCurrentUserFollower = false;
        if (!Auth::guest()) {
            $isCurrentUserFollower = Follower::where('user_id', $user->id)->where('follower_id', Auth::id())->exists();
        }

        $followerCount = Follower::where('user_id', $user->id)->count();

        $posts = Post::postsForTimeline(Auth::id(), false)
            ->leftJoin('users AS u', 'u.id', 'posts.user_id')
            ->where('user_id', $user->id)
            ->whereNull('group_id')
            ->orderBy('posts.created_at', 'desc')
            ->paginate(5);

        $posts = PostResource::collection($posts);

        $followers = User::query()
            ->select('users.*')
            ->join('followers AS f', 'f.follower_id', '=', 'users.id')
            ->where('user_id', $user->id)
            ->get();

        $followings = User::query()
            ->select('users.*')
            ->join('followers AS f', 'f.user_id', '=', 'users.id')
            ->where('f.follower_id', $user->id)
            ->get();

        // $photos = PostAttachment::query()
        //     ->where('mime', 'like', 'image/%')
        //     ->where('created_by', $user->id)
        //     ->latest()
        //     ->get();

        $photos = PostAttachment::query()
            ->join('posts', 'posts.id', '=', 'post_attachments.post_id')
            ->where('mime', 'like', 'image/%')
            ->where('created_by', $user->id)
            ->whereNull('posts.group_id')
            ->latest('post_attachments.created_at')
            ->get(['post_attachments.*']); 


        return Inertia::render('Profile/View', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'success' => session('success'),
            'isCurrentUserFollower' => $isCurrentUserFollower,
            'followerCount' => $followerCount,
            'posts' => $posts,
            'followers' => UserResource::collection($followers),
            'followings' => UserResource::collection($followings),
            'photos' => PostAttachmentResource::collection($photos),
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile', $request->user())->with('success', 'Thông tin cá nhân của bạn đã được cập nhật !');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function updateImage(Request $request) 
    {
        $data = $request->validate([
            'cover' => ['nullable', 'image'],
            'avatar' => ['nullable', 'image']
        ]);

        $user = $request->user();

        $avatar = $data['avatar'] ?? null;
        $cover = $data['cover'] ?? null;

        $success = '';

        if($cover) {
            // Xóa ảnh cũ
            if($user->cover_path) {
                Storage::disk('public')->delete($user->cover_path);
            }
            // Upload lên server và lưu path vào CSDL
            $path = $cover->store('user-/'.$user->id, 'public');
            $user->update(['cover_path' => $path]);
            $success = 'Ảnh bìa đã được cập nhật !';
        }

        if($avatar) {
            // Xóa ảnh cũ
            if($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            // Upload lên server và lưu path vào CSDL
            $path = $avatar->store('user-/'.$user->id, 'public');
            $user->update(['avatar_path' => $path]);
            $success = 'Ảnh đại diện đã được cập nhật !';
        }
        

        return back()->with('success', $success);
    }
}
