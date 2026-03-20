<?php

namespace Yl\Posts\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Yl\Helper\Http\Controllers\BaseApiController;
use Yl\Posts\Http\Requests\StorePostRequest;
use Yl\Posts\Http\Requests\UpdatePostRequest;
use Yl\Posts\Http\Resources\PostResource;
use Yl\Posts\Jobs\ProcessPostPublishedJob;
use Yl\Posts\Models\Post;

/**
 * PostController
 *
 * CRUD operations for the Posts module.
 *
 * Routes:
 *   GET    /api/posts          → index
 *   POST   /api/posts          → store
 *   GET    /api/posts/{id}     → show
 *   PUT    /api/posts/{id}     → update
 *   DELETE /api/posts/{id}     → destroy
 */
class PostController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/posts",
     *     summary="List all posts",
     *     tags={"Posts"},
     *     @OA\Parameter(name="status", in="query", required=false,
     *         @OA\Schema(type="string", enum={"draft","published","archived"})
     *     ),
     *     @OA\Response(response=200, description="Paginated post list")
     * )
     */
    public function index(): JsonResponse
    {
        $query = Post::recent();

        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        return $this->success(
            PostResource::collection($query->paginate(15)),
            'Posts retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/posts",
     *     summary="Create a post",
     *     tags={"Posts"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","body"},
     *             @OA\Property(property="title",        type="string",  example="My First Post"),
     *             @OA\Property(property="slug",         type="string",  example="my-first-post"),
     *             @OA\Property(property="body",         type="string",  example="Post content here."),
     *             @OA\Property(property="status",       type="string",  enum={"draft","published","archived"}, example="draft"),
     *             @OA\Property(property="published_at", type="string",  format="date-time", example="2024-06-01T10:00:00Z")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Post created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::create($request->validated());

        // Only dispatch the publish job when the post is immediately published.
        if ($post->status === 'published') {
            ProcessPostPublishedJob::dispatch($post);
        }

        return $this->created(
            new PostResource($post),
            'Post created successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/posts/{id}",
     *     summary="Get a post",
     *     tags={"Posts"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Post found"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $post = Post::find($id);

        if (! $post) {
            return $this->notFound("Post #{$id} not found");
        }

        return $this->success(new PostResource($post), 'Post retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/posts/{id}",
     *     summary="Update a post",
     *     tags={"Posts"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title",        type="string",  example="Updated Title"),
     *             @OA\Property(property="body",         type="string",  example="Updated body."),
     *             @OA\Property(property="status",       type="string",  enum={"draft","published","archived"}),
     *             @OA\Property(property="published_at", type="string",  format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Post updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(UpdatePostRequest $request, int $id): JsonResponse
    {
        $post = Post::find($id);

        if (! $post) {
            return $this->notFound("Post #{$id} not found");
        }

        $wasPublished = $post->status === 'published';
        $post->update($request->validated());

        // Dispatch the job only on the transition to published,
        // not on every subsequent update of an already-published post.
        if (! $wasPublished && $post->status === 'published') {
            ProcessPostPublishedJob::dispatch($post);
        }

        return $this->success(new PostResource($post), 'Post updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/posts/{id}",
     *     summary="Delete a post",
     *     tags={"Posts"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Post deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $post = Post::find($id);

        if (! $post) {
            return $this->notFound("Post #{$id} not found");
        }

        $post->delete();

        return $this->success(null, 'Post deleted successfully');
    }
}
