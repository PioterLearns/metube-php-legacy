<?php

namespace Controllers;

use DateTime;
use Doctrine\DBAL;
use Doctrine\DBAL\Connection;
use Exception;
use Helpers\TimeCheckTrait;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;
use Youtube\Channel;
use Youtube\Filter;

class Youtube
{
    use TimeCheckTrait;

    private array $dirsToFilter = [];

    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @throws DBAL\Exception
     */
    public function findFeeds(string $name): array
    {
        $feeds = $this->connection->prepare(
            "
            SELECT c.id, c.name
            FROM youtube_feed c
            WHERE c.name LIKE :name
        "
        );
        $feeds->bindValue('name', "%{$name}%");

        return $feeds->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DBAL\Exception
     */
    public function updateFeed(int $feedId): void
    {
        $channel = $this->connection->executeQuery(
            "
            SELECT c.*,
                   GROUP_CONCAT(f.filter) as filterPhrases,
                   GROUP_CONCAT(f.type) as filterTypes,
                   GROUP_CONCAT(f.action) as filterActions
            FROM youtube_feed c
                 LEFT JOIN youtube_filter f ON f.channelName = c.name
            WHERE c.id = {$feedId}
            GROUP BY c.id
        "
        );
        foreach ($channel->fetchAllAssociative() as $feed) {
            $this->parseFeed($feed);
        }
    }

    /**
     * @throws DBAL\Exception
     * @throws Exception
     */
    public function updateFeeds(): void
    {
        $channels = $this->connection->executeQuery(
            "
            SELECT c.*,
                   GROUP_CONCAT(f.filter) as filterPhrases,
                   GROUP_CONCAT(f.type) as filterTypes,
                   GROUP_CONCAT(f.action) as filterActions
            FROM youtube_feed c
                 LEFT JOIN youtube_filter f ON f.channelName = c.name
            GROUP BY c.id
        "
        );

        foreach ($channels->fetchAllAssociative() as $feed) {
            switch ($feed['frequencyType']) {
                case 'daily':
                    $fetch = $this->dailyCheck($feed['lastChecked']);
                    break;
                case 'weekly':
                    $fetch = $this->weeklyCheck($feed['lastChecked'], $feed['frequencyValue']);
                    break;
                case 'monthly':
                    $fetch = $this->monthlyCheck($feed['lastChecked'], $feed['frequencyValue']);
                    break;
                case 'always':
                    $fetch = true;
                    break;
                case 'never':
                case 'manual':
                    $fetch = false;
                    break;
                default:
                    dd("Unknown frequency type!!! {$feed['frequencyType']}");
            }
            if ($fetch) {
                printLn("Fetching {$feed['name']}");
                $this->parseFeed($feed);
            }
        }
    }

    /**
     * @throws DBAL\Exception
     * @throws Exception
     */
    private function parseFeed(array $feed): void
    {
        $prefix = match ($feed['type']) {
            'p' => 'https://www.youtube.com/feeds/videos.xml?playlist_id=',
            'c' => 'https://www.youtube.com/feeds/videos.xml?channel_id=',//todo publish date is not reliable way to check if video is already registered. Check by id?
            default => '',
        };

        if (empty($prefix)) {
            dd("Unknown feed type {$feed['type']}");
        }

        $filters = [];
        $filterPhrases = explode(',', $feed['filterPhrases']);
        $filterTypes = explode(',', $feed['filterTypes']);
        $filterActions = explode(',', $feed['filterActions']);
        foreach ($filterPhrases as $i => $filterPhrase) {
            if (empty($filterPhrase)) {
                continue;
            }
            $filters[] = new Filter($filterPhrase, $filterTypes[$i], $filterActions[$i]);
        }
        $ch = curl_init("{$prefix}{$feed['ytId']}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $feedData = curl_exec($ch);
//        var_dump($feedData);die();c
        if (false === $feedData) {
            dd("Fetching feed failed for {$feed['ytId']}. No internet?");
        }
        usleep(300000);
        try {
            $xml = new SimpleXMLElement($feedData);
        } catch (\Throwable) {
            var_dump($feedData);
            var_dump("{$prefix}{$feed['ytId']}");
            die();
        }
        $lastVideo = $feed['latestVideo'];
        if (!empty($lastVideo)) {
            $lastVideo = new DateTime($lastVideo);
            $newCheckTime = $lastVideo;
        } else {
            $newCheckTime = null;
        }
        foreach ($xml->children("http://www.w3.org/2005/Atom")->entry as $entry) {
            $published = new DateTime((string)$entry->published);
            $title = (string)$entry->title;
            $videoId = $entry->children("http://www.youtube.com/xml/schemas/2015")->videoId;
            $media = $entry->children("http://search.yahoo.com/mrss/")->group;
            $thumbnailUrl = (string)$media->thumbnail->attributes()['url'];
            $description = (string)$media->description;
            if (empty($lastVideo) || $lastVideo < $published) {
                if (empty($newCheckTime)) {
                    $newCheckTime = $published;
                }
                $type = null;
                foreach ($filters as $filter) {
                    $type = $filter->filter($title, $description, $feed['name']);
                    if (isset($type)) {
                        break;
                    }
                }
                $newCheckTime = max($newCheckTime, $published);
                $this->connection->insert("youtube_video", [
                    'ytId' => $videoId,
                    "channelId" => $feed['id'],
                    'title' => $title,
                    'published' => $published->format('Y-m-d H:i:s'),
                    'thumbnail' => $thumbnailUrl,
                    'status' => isset($type) ? 'filed' : 'new',
                    'type' => $type
                ]);
            }
        }
        $this->connection->update("youtube_feed", [
            'latestVideo' => $newCheckTime->format('Y-m-d H:i:s'),
            'lastChecked' => $this->currentDate(),
        ], [
            'id' => $feed['id']
        ]);
    }

    /**
     * @throws DBAL\Exception
     */
    public function addChannel(string $link, string $lang, int $res, string $category, ?string $name): void
    {
        if (str_starts_with($link, '@')) {
            $link = 'https://www.youtube.com/' . $link;
            $name = explode('@', $link)[1];
        }

        if (empty($name)) {
            die('Provide channel name');
        }

        $ch = curl_init($link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        $matches = [];
        preg_match('/\"https:\/\/www\.youtube\.com\/channel\/([^"]*)\"/', $server_output, $matches);

        if (empty($matches[1])) {
            dd('channel not found');
        }
        $this->connection->executeStatement(
            "
                INSERT INTO youtube_feed (ytId, name, subtitles, type, res, category) 
                VALUES (:id, :name, :lang, 'c', :res, :category)
            ",
            [
                'id' => $matches[1],
                'name' => $name,
                'lang' => $lang,
                'res' => $res,
                'category' => $category
            ]
        );
    }

    /**
     * @throws DBAL\Exception
     */
    public function addPlaylist(string $link, string $name, string $lang, string $res, string $category): void
    {
        $parsedUrl = parse_url($link);
        if (isset($parsedUrl['query'])) {
            $tmp = [];
            parse_str($parsedUrl['query'], $tmp);
            $id = $tmp['list'];
        } else {
            $id = $link;
        }

        if (!str_starts_with($id, 'PL')) {
            die("Invalid playlist. $id in $link");
        }

        $this->connection->executeStatement(
            "INSERT INTO youtube_feed (ytId, name, subtitles, category, res, type) 
                    VALUES (:id, :name, :lang, :category, :res, 'p')",
            [
                'id' => $id,
                'name' => $name,
                'lang' => $lang,
                'category' => $category,
                'res' => $res
            ]
        );
    }


    /**
     * @throws DBAL\Exception
     */
    public function downloadVideos(string $channelId = null, bool $skipSubs = false): void
    {
        var_dump($channelId);
        $channelSQL = '';
        if (isset($channelId)) {
            $channelSQL = "AND c.id = $channelId";//todo SQLi but IDGAF for now
        }
        $videos = $this->connection->executeQuery(
            "
            SELECT v.id, v.ytId, v.title, v.type, c.category,
                   c.name as channel, c.subtitles, c.sponsorBlockExclude as exclude, c.sponsorBlockInclude as include,
                   c.res
            FROM youtube_video v
                 LEFT JOIN youtube_feed c ON v.channelId = c.id
            WHERE v.status = 'filed'
              $channelSQL
              AND v.type NOT IN ('ignor', 'skip')
            GROUP BY v.id
        "
        );

        foreach ($videos->fetchAllAssociative() as $video) {
            $channel = str_replace('"', '', $video['channel']);

            $channel = new Channel(name: $channel, category: $video['category']);
            $videoId = str_replace('"', '', $video['ytId']);
            $type = $video['type'];
            $path = $channel->storagePath($type);
            if ((false === file_exists($path)) && !mkdir($path, true) && !is_dir($path)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
            $videoUrl = "https://www.youtube.com/watch?v=$videoId";
            $overrides = [];

            if ('pcast' === $type) {
                $overrides['quality'] = '-f ba';
                $subs = null;
            } else {
                $subs = $video['subtitles'];
                $quality = $video['res'];
            }
            if ($skipSubs) {
                $subs = null;
            }

            //todo constant
            $sponsorBlockArgs = ['intro' => null, 'outro' => null, 'sponsor' => null];
            if (isset($video['exclude'])) {
                foreach (explode(',', $video['exclude']) as $exclude) {
                    $sponsorBlockArgs[$exclude] = null;
                }
            }
            if (isset($video['include'])) {
                foreach (explode(',', $video['include']) as $include) {
                    unset($sponsorBlockArgs[$include]);
                }
            }
            $sponsorBlockExclude = implode(',', array_keys($sponsorBlockArgs));
            $overrides['sponsorBlockExclude'] = "--sponsorblock-remove {$sponsorBlockExclude}";
            printLn();
            printLn("Downloading {$video['channel']}: {$video['title']}");
            printLn();

            $res = $this->download($videoUrl, $subs, $quality ?? '', $path, $overrides);

            if (0 === $res) {
                $this->connection->update('youtube_video', [
                    'status' => 'done'
                ], [
                    'id' => $video['id']
                ]);
            } else {
//                $this->connection->update('youtube_video', [
//                    'status' => 'error'
//                ], [
//                    'id' => $video['id']
//                ]);
                var_dump('ERROR' . $res);
            }
            sleep(3);
        }
        printLn("Filteing shorts");
        foreach ($this->dirsToFilter as $dir => $true) {
            $this->filterShorts($dir);
        }
    }

    /**
     * @throws DBAL\Exception
     */
    public function manageVideos(): void
    {
        $videos = $this->connection->executeQuery(
            "
            SELECT v.id, v.title, v.ytId, v.type, c.name as channel
            FROM youtube_video v
                 LEFT JOIN youtube_feed c ON v.channelId = c.id
            WHERE status='new'
        "
        );

        foreach ($videos->fetchAllAssociative() as $video) {
            $type = null;
            printLn("{$video['channel']}: {$video['title']}");
            if (empty($type)) {
                $type = readline('[S]olo, [I]gnore, [P]odcast, S[k]ip, (x)Short: ');
                //todo extract common logic...
                $type = match ($type) {
                    'i', 'I' => 'ignor',
                    's', 'S' => 'solo',
                    'p', 'P' => 'pcast',
                    'x', 'X' => 'solo',
                    'k', 'K' => 'skip',
                    default => null
                };
            }

            if (isset($type)) {
                $this->connection->update('youtube_video', [
                    'type' => $type,
                    'status' => 'filed'
                ], [
                    'id' => $video['id']
                ]);
            }
        }
    }

    /**
     * @throws DBAL\Exception
     */
    public function clearPending(): void
    {
        $this->connection->executeQuery("
            UPDATE youtube_video
            SET status='abort'
            WHERE type != 'ignor'
              AND status='filed'
        ");
    }

    public function downloadManual(
        string $videoUrl,
        string $dir = null,
        string $subs = null,
        string $quality = '720',
        $rawName = null
    ): void {
        $urls = explode(' ', $videoUrl);
        if (isset($rawName)) {
            $overrides = ['name' => ''];
        }
        foreach ($urls as $url) {
            $res = $this->download($url, $subs, $quality, $dir, $overrides ?? []);
            var_dump($res);
        }
    }

    private function download(
        string $videoUrl,
        string $subs = null,
        string $quality = '720',
        string $dir = null,
        array $overrides = [],
    ): int {
        //template fields: duration upload_date was_live playlist_index
        $ytDlpArgs = [
//            'workaround' => '--extractor-args "youtube:player_client=default,-android_sdkless"',
            'skipLive' => '--break-match-filters "!is_live"',
            'quality' => '-S "res:' . $quality . '"',
            'name' => "-o '%(upload_date>%Y-%m-%d)s_<%(duration>%H-%M-%S)s>_%(title)s[%(id)s].%(ext)s'",
            'meta' => '--write-description --progress',
            'sponsorBlock' => "--sponsorblock-mark all"
        ];
        if (!empty($subs)) {
            $ytDlpArgs['subs'] = '--write-subs --write-auto-subs --sub-langs "' . $subs . '"';
        }
        if (!empty($dir)) {
            $this->dirsToFilter[$dir] = true;
            $ytDlpArgs['path'] = '-P "' . $dir . '"';
        }

        foreach ($overrides as $key => $override) {
            $ytDlpArgs[$key] = $override;
        }
//dd('yt-dlp ' . implode(' ', $ytDlpArgs) . ' "' . $videoUrl . '"');
        passthru('yt-dlp ' . implode(' ', $ytDlpArgs) . ' "' . $videoUrl . '"', $res);
        return $res;
    }

    private function filterShorts(string $directory, string $pattern = "/<00-0[0-3]/"): void
    {
        //todo add delete short option

        // Normalize directory path
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);

        // Validate directory exists and is readable
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("Directory does not exist: {$directory}");
        }

        if (!is_readable($directory)) {
            throw new InvalidArgumentException("Directory is not readable: {$directory}");
        }

        // Scan directory (excluding . and ..)
        $items = scandir($directory);
        if ($items === false) {
            throw new RuntimeException("Failed to scan directory: {$directory}");
        }

        foreach ($items as $item) {
            // Skip current/parent directory references
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $directory . DIRECTORY_SEPARATOR . $item;

            // Skip directories
            if (is_dir($itemPath)) {
                continue;
            }

            // Check if filename matches the pattern
            if (preg_match($pattern, $item)) {
                $filteredDir = $directory . DIRECTORY_SEPARATOR . 'short';
                if (!is_dir($filteredDir) && !mkdir($filteredDir, 0755, true) && !is_dir($filteredDir)) {
                    throw new RuntimeException("Failed to create filtered directory: {$filteredDir}");
                }
                $destination = $filteredDir . DIRECTORY_SEPARATOR . $item;

                if (str_ends_with($item, 'srt') || str_ends_with($item, 'vtt')) {
                    printLn("Removing subs for short: {$item}");
                    unlink($itemPath);
                    continue;
                }
                // Handle filename conflicts
                if (file_exists($destination)) {
                    printLn("File already exists in filtered folder: {$item} Removing");
                    unlink($itemPath);
                    continue;
                }

                // Move the file
                if (rename($itemPath, $destination)) {
                    printLn("Moved {$item} to {$destination}");
                } else {
                    printLn("Failed to move file: {$item}");
                }
            }
        }
    }
}