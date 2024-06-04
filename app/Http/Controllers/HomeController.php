<?php

namespace App\Http\Controllers;

use App\Http\Enums\GroupUserStatus;
use App\Http\Resources\GroupResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function index(Request $request) 
    {   
        $userId = Auth::id();
        $user = $request->user();
        $allUser = User::where('id', '!=', Auth::id())->get();
        $posts = Post::postsForTimeline($userId)
            ->select('posts.*')
            ->leftJoin('followers AS f', function ($join) use ($userId) {
                $join->on('posts.user_id', '=', 'f.user_id')
                    ->where('f.follower_id', '=', $userId);
            })
            ->leftJoin('group_users AS gu', function ($join) use ($userId) {
                $join->on('gu.group_id', '=', 'posts.group_id')
                    ->where('gu.user_id', '=', $userId)
                    ->where('gu.status', GroupUserStatus::APPROVED->value);
            })
            ->where(function($query) use ($userId) {
                $query->whereNotNull('f.follower_id')
                    ->orWhereNotNull('gu.group_id')
                    ->orWhere('posts.user_id', $userId);
            })
            ->paginate(5);

        $post = PostResource::collection($posts);
        if($request->wantsJson()) {
            return $posts;
        }

        $groups = Group::query()
            ->with('currentUserGroup')
            ->select(['groups.*'])
            ->join('group_users AS gu', 'gu.group_id', 'groups.id')
            ->where('gu.user_id', Auth::id())
            ->orderBy('gu.role')
            ->orderBy('name')
            ->get();
            
        return Inertia::render('Home', [
            'posts' => $post,
            'allUser' => UserResource::collection($allUser),
            'groups' => GroupResource::collection($groups),
            'followings' => UserResource::collection($user->followings)
        ]);
    }
}
