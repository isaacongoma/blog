<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreBlogPost;
use App\Http\Requests\PostUpdate;
use App\Post as Post;
use App\Category;
use App\Tag;
use Validator;
use Purifier;
use Image;
use Storage;

class PostController extends Controller
{
    public function __construct() {
      return $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $posts = DB::table('posts')->orderBy('id', 'DESC')->paginate(10);
        return view('posts.index',['posts' => $posts]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = Category::all();
        $tags = Tag::all();
        return view('posts.create', ['categories' => $categories, 'tags' => $tags]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreBlogPost $request)
    {
       //dd($request);
       $post = new Post;

       $post->title = $request->title;
       $post->slug = $request->slug;
       $post->category_id = $request->category_id;
       $post->body = Purifier::clean($request->body);


       if($request->hasFile('featured_image')) {
         $image = $request->featured_image;
         $fileName = time() . "." . $image->getClientOriginalExtension();
         $location = public_path('images/' . $fileName);
         Image::make($image)->resize(800, 400)->save($location);

         $post->image = $fileName;
       }

       $post->save();

       $post->tags()->sync($request->tag_id, false);

       session()->flash('success', 'The blog has been posted successfully!');

       return redirect()->route('posts.show', $post->id);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
      $post = Post::find($id);
      return view('posts.show', ['post' => $post]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $post = Post::find($id);
        $categories = Category::all();
        $tags = Tag::all();
        $tags2 = [];
        $cats_arr = [];
        $tag_ids = [];
        foreach ($categories as $category) {
          $cats_arr[$category->id] = $category->name;
        }
        foreach ($tags as $tag) {
          $tags2[$tag->id] = $tag->name;
        }

        foreach ($post->tags as $tag) {
          $tag_ids[] = $tag->id;
        }

        return view('posts.edit', ['post' => $post, 'categories' => $cats_arr, 'tags' => $tags2, 'tag_ids' => $tag_ids]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'title' => 'required|max:255',
            'slug' => [
                'required',
                'alpha_dash',
                'min:5',
                'max:191',
                Rule::unique('posts')->ignore($id),
            ],
            'category_id' => 'required|integer',
            'body' => 'required',
            'featured_image' => 'sometimes|image'
        ]);

        $post = Post::find($id);
        $post->title = $request->title;
        $post->slug = $request->slug;
        $post->category_id = $request->category_id;
        $post->body = Purifier::clean($request->body);

        if($request->hasFile('featured_image')) {
          $image = $request->file('featured_image');
          $fileName = time() . "." . $image->getClientOriginalExtension();
          $oldFileName = $post->image;
          $location = public_path('images/' . $fileName);
          Image::make($image)->resize(800, 400)->save($location);
          Storage::delete($oldFileName);

          $post->image = $fileName;
        }

        $post->save();

        $post->tags()->sync($request->tag_id);

        session()->flash('success', 'Post has been updated successfully!');

        return redirect()->route('posts.show', ['id' => $post->id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $post = Post::find($id);
        $post->tags()->detach();
        Storage::delete($post->image);
        $post->delete();

        session()->flash('success', 'Post has been deleted successfully!');

        return redirect()->route('posts.index');
    }
}
