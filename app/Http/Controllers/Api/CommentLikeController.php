<?php

namespace App\Http\Controllers\Api;

use App\Comment;
use App\CommentLike;
use App\Report;
use Illuminate\Support\Facades\Auth;

class CommentLikeController
{
    /**
     * Seznam lajků u komentáře
     *
     * @param Report $report
     * @param Comment $comment
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Report $report, Comment $comment)
    {
        return response()->json([
            'error' => false,
            'likes' => CommentLike::where('comment_id', $comment->id)->count(),
        ], 200);
    }

    /**
     * Uložení lajku do databáze
     *
     * @param Report $report
     * @param Comment $comment
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Report $report, Comment $comment)
    {
        CommentLike::create(['user_id' => Auth::id(), 'comment_id' => $comment->id]);

        return response()->json([
            'error' => false,
        ], 200);
    }

    public function show()
    {
        abort(404);
    }

    public function update()
    {
        abort(404);
    }

    /**
     * Odstranění lajku z databáze
     *
     * @param Report $report
     * @param Comment $comment
     * @param CommentLike $like
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function destroy(Report $report, Comment $comment, CommentLike $like){
        if($like->user_id == Auth::id()){

            $like->delete();

            return response()->json([
                'error' => false,
            ], 200);

        } else {
            return response()->json([
                'error' => true,
            ], 403);
        }
    }
}
