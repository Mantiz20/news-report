<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class NewsImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        //Removing old news
        $old_news = News::all();
        if($old_news) {
            foreach ($old_news as $old_n) {
                $image_path =$old_n -> image;
                if($image_path){
                    File::delete(public_path($image_path));
                    $old_n -> delete();
                }
            }
        }

        //Getting news from xml, resizing the image and saving
        $resource = simplexml_load_file("http://rss.cnn.com/rss/edition.rss", null, LIBXML_NOCDATA);
        $namespaces = $resource->getNamespaces(true);

        $index = 1;
        foreach($resource->channel->item as $item){
            if($index++ == 20)
                break;

            $media_content = $item->children($namespaces['media']);
            $image_path = null;
            if (isset($media_content->group->content)) {
                $image_url = (string)$media_content->group->content[0]->attributes()->url;
                $image = file_get_contents($image_url);
                $image_extension = pathinfo($image_url, PATHINFO_EXTENSION);
                $image_path = 'uploads/news/' . time() .$index. '.' . $image_extension;
                file_put_contents(public_path($image_path), $image);
                Image::make(public_path($image_path)) -> resize(200, 200) -> save(public_path($image_path));
            }
            $title = (string)$item -> children() -> title;
            $description = (string)$item -> children() -> description;
            $pubDate = (string)$item -> children() -> pubDate;

            if($pubDate){
                $pubDate = date('Y-m-d H:i:s', strtotime($pubDate));
            } else {
                $pubDate = null;
            }

            //Adding news to the database
            News::create(
                [
                    'title' => $title,
                    'pubDate' => $pubDate,
                    'description' => $description,
                    'image' => $image_path,
                ]
            );
        }
    }
}
