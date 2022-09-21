<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Facades\Image;
use PDO;
use PHPUnit\Util\Exception;

class NewsImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     *
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(): void
    {
        //Getting old news
        $old_news = News::all();

        //Getting news from xml, resizing the image and saving
        $resource = simplexml_load_file("http://rss.cnn.com/rss/edition.rss", null, LIBXML_NOCDATA);
        $namespaces = $resource->getNamespaces(true);

        $index = 1;
        foreach ($resource->channel->item as $item) {
            if ($index++ == 21)
                break;

            $media_content = $item->children($namespaces['media']);
            $image_path = null;
            if (isset($media_content->group->content)) {
                $image_url = (string)$media_content->group->content[0]->attributes()->url;
                $image = HTTP::get($image_url, ['stream' => true]);
                $image_extension = pathinfo($image_url, PATHINFO_EXTENSION);
                $image_path = 'uploads/news/' . time() . $index . '.' . $image_extension;
                file_put_contents(public_path($image_path), $image);
                $image_width = 200;
                $image_height = 200;
                $image = Image::make(public_path($image_path));
                if ($image->height() > $image->width()) {
                    $image_width = null;
                } else {
                    $image_height = null;
                }
                $image->resize($image_width, $image_height, function ($constraint) {
                    $constraint->aspectRatio();
                })->save(public_path($image_path));
            }

            $title = (string)$item->children()->title;
            $description = (string)$item->children()->description;
            $pubDate = (string)$item->children()->pubDate;

            if ($pubDate) {
                $pubDate = date('Y-m-d H:i:s', strtotime($pubDate));
            } else {
                $pubDate = null;
            }

            $db = new PDO('mysql:host=localhost;dbname=news', 'root', '');
            try {
                $db->beginTransaction();

                News::create(
                    [
                        'title' => $title,
                        'pubDate' => $pubDate,
                        'description' => $description,
                        'image' => $image_path,
                    ]);

                $db->commit();
            } catch (\Throwable $e) {
                $db->rollback();
                throw  new Exception("Something went wrong");
            }

            // Deleting old news
            if ($old_news) {
                foreach ($old_news as $old_n) {
                    $image_path = $old_n->image;
                    if ($image_path) {
                        File::delete(public_path($image_path));
                        $old_n->delete();
                    }
                }
            }
        }
    }
}
