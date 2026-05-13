<?php

namespace Youtube;

class Channel
{

    public function __construct(
        public readonly ?int    $id = null,
        public readonly ?string $name = null,
        public readonly ?string $ytId = null,
        public readonly ?string $category = null,
        public ?\DateTime       $lastChecked = null,
    )
    {
    }

    public function storagePath(string $type): string
    {
        $dir = match ($type) {
            'solo', 'short' => $this->category ?? 'solo',
            'pcast' => 'podcasts',
            default => throw new \LogicException("Unknown type: {$type}")
        };
        $channel = str_replace('"', '', $this->name);
        $path = '/storage/youtube/' . $dir . '/' . $channel;
        if ($type === 'short') {
            $path .= '/shorts';//todo obsolete?
        }
        if ((false === file_exists($path)) && !mkdir($path, recursive: true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }
        return $path;
    }
}