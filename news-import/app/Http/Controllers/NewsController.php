<?php

namespace App\Http\Controllers;

use App\Models\News;


class NewsController extends Controller
{
    public function index()
    {
        $news = News::all();
        dd($news->toJson(JSON_PRETTY_PRINT));
    }
}
