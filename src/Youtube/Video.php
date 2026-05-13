<?php

namespace Youtube;

class Video {

    public function __construct(
        private readonly string $id,
        private ?string $filename,
        private Channel $channelId,
    )
    {

    }
}