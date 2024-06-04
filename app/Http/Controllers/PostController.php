<?php

namespace App\Http\Controllers;

use App\Http\Enums\ReactionEnum;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function store(StorePostRequest $request)
    {
        $data = $request->validated();

        $user = $request->user();

        DB::beginTransaction();
        $allFilePaths = [];
        try{
            $post = Post::create($data);

            $files = $data['attachments'] ?? [];
            foreach ($files as $file) {
                $path = $file->store('attachments/'.$post->id, 'public');
                $allFilePaths[] = $path;
                PostAttachment::create([
                    'post_id' => $post->id,
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'created_by' => $user->id
                ]);
            }
            DB::commit();
        }catch(\Exception $e){
            foreach ($allFilePaths as $path) {
                Storage::disk('public')->delete($path);
            }
            DB::rollBack();  
        }

        return back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePostRequest $request, Post $post)
    {
        $user =$request->user();

        DB::beginTransaction();
        $allFilePaths = [];
        try{
            $data = $request->validated();
            $post->update($data);

            $deleted_ids = $data['deleted_file_ids'] ?? [];

            $attachments = PostAttachment::query()
                ->where('post_id', $post->id)
                ->whereIn('id', $deleted_ids)
                ->get();

            foreach($attachments as $attachment) {
                $attachment->delete();
            }

            $files = $data['attachments'] ?? [];
            foreach ($files as $file) {
                $path = $file->store('attachments/'.$post->id, 'public');
                $allFilePaths[] = $path;
                PostAttachment::create([
                    'post_id' => $post->id,
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'created_by' => $user->id
                ]);
            }
            DB::commit();
        }catch(\Exception $e){
            foreach ($allFilePaths as $path) {
                Storage::disk('public')->delete($path);
            }
            DB::rollBack();  
        }

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        $id = Auth::id();

        if ($post->isOwner($id) || $post->group && $post->group->isAdmin($id)) {
            
            $post->delete();

            return back();
        }
        return response("Bạn không có quyền xóa bài viết này", 403);
    }

    public function downloadAttachment(PostAttachment $attachment) 
    {
        return response()->download(Storage::disk('public')->path($attachment->path), $attachment->name);
    }

    public function postReaction(Request $request, Post $post)
    {
        $user = $request->user();

        $data = $request->validate([
            'reaction' => [Rule::enum(ReactionEnum::class)]
        ]);

        $userId = Auth::id();
        $reaction = Reaction::where('user_id', $userId)
            ->where('object_id', $post->id)
            ->where('object_type', Post::class)
            ->first();

        if($reaction) {
            $hasReaction = false;
            $reaction->delete();
        } else {
            $hasReaction = true;
            Reaction::create([
                'object_id' => $post->id,
                'object_type' => Post::class,
                'user_id' => $userId,
                'type' => $data['reaction']
            ]);
        }



        $reactions = Reaction::where('object_id', $post->id)->where('object_type', Post::class)->count();

        return response([
            'num_of_reactions' => $reactions,
            'current_user_has_reaction' => $hasReaction
        ]);
    }

    public function createComment(Request $request, Post $post)
    {
        $data = $request->validate([
            'comment' => ['required'],
            'parent_id' => ['nullable', 'exists:comments,id']
        ]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'comment' => nl2br($data['comment']),
            'user_id' => Auth::id(),
            'parent_id' => $data['parent_id'] ?: null
        ]);

        $post = $comment->post;

        // $post->user->notify(new CommentCreated($comment, $post));

        return response(new CommentResource($comment), 201);
    }

    public function updateComment(UpdateCommentRequest $request, Comment $comment)
    {
        $data = $request->validated();

        $comment->update([
            'comment' => nl2br($data['comment'])
        ]);

        return new CommentResource($comment);
    }

    public function commentReaction(Request $request, Comment $comment)
    {
        $data = $request->validate([
            'reaction' => [Rule::enum(ReactionEnum::class)]
        ]);

        $userId = Auth::id();
        $reaction = Reaction::where('user_id', $userId)
            ->where('object_id', $comment->id)
            ->where('object_type', Comment::class)
            ->first();

        if ($reaction) {
            $hasReaction = false;
            $reaction->delete();
        } else {
            $hasReaction = true;
            Reaction::create([
                'object_id' => $comment->id,
                'object_type' => Comment::class,
                'user_id' => $userId,
                'type' => $data['reaction']
            ]);

            // if (!$comment->isOwner($userId)) {
            //     $user = User::where('id', $userId)->first();
            //     $comment->user->notify(new ReactionAddedOnComment($comment->post, $comment, $user));
            // }
        }

        $reactions = Reaction::where('object_id', $comment->id)->where('object_type', Comment::class)->count();

        return response([
            'num_of_reactions' => $reactions,
            'current_user_has_reaction' => $hasReaction
        ]);
    }

    public function deleteComment(Comment $comment)
    {
        if($comment->user_id != Auth::id()) {
            return response("Bạn không có quyền xóa bình luận này", 403);
        }

        $comment->delete();

        return response('', 204);
    }

}
 