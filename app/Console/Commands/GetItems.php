<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\NewStory;
use App\Models\TopStory;
use Illuminate\Console\Command;
use Yadakhov\Curl;

class GetItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hn:getitems';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get items information.';

    /**
     * TopStories constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $news = collect(NewStory::where('done', false)->orderBy('id', 'desc')->get());
        $tops = collect(TopStory::where('done', false)->orderBy('id', 'desc')->get());
        $all = $news->merge($tops);

        foreach ($all as $story) {
            $stories = $story->items;

            $items = json_decode($stories, true);

            $count = 0;
            foreach ($items as $id) {
                $data = $this->getItem($id);

                if (isset($data['id'])) {
                    Item::insertOnDuplicateKey($data);
                    $this->info(++$count . ': Done with ' . array_get($data, 'title'));
                }
            }

            $story->done = true;
            $story->save();
            $this->info('Done parsing all stories.');
        }
    }

    /**
     * Curl the item api and return as an array
     *
     * @param $id
     *
     * @return array
     */
    protected function getItem($id)
    {
        $url = sprintf('https://hacker-news.firebaseio.com/v0/item/%s.json', $id);

        $json = Curl::getInstance()->get($url);
        $json = json_decode($json, true);

        // For deleted items we just return the delete flag.
        if (!empty($json['deleted'])) {
            return [
                'id' => array_get($json, 'id'),
                'deleted' => array_get($json, 'deleted'),
            ];
        }

        $data = [
            'id' => array_get($json, 'id'),
            'deleted' => array_get($json, 'deleted', false),
            'type' => array_get($json, 'type'),
            'by' => array_get($json, 'by'),
            'time' => array_get($json, 'time'),
            'text' => array_get($json, 'text'),
            'dead' => array_get($json, 'dead', false),
            'parent' => array_get($json, 'parent'),
            'kids' => json_encode(array_get($json, 'kids')), // array
            'url' => array_get($json, 'url'),
            'score' => array_get($json, 'score'),
            'title' => array_get($json, 'title'),
            'parts' => json_encode(array_get($json, 'parts')),  // array
            'descendants' => array_get($json, 'descendants'),
        ];

        return $data;
    }
}
