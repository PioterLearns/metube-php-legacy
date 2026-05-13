create table youtube_feed
(
    id                  int auto_increment
        primary key,
    res                 int         default 720                   not null,
    name                varchar(100)                              not null,
    ytId                varchar(40)                               not null,
    latestVideo         datetime                                  null,
    subtitles           varchar(10)                               null,
    frequencyType       varchar(10) default 'daily'               not null comment 'always, daily, weekly, monthly',
    frequencyValue      int                                       null comment 'null for always/daily, 1-7 for weeklly, 1-31 for monthly (or null for random)',
    type                varchar(1)  default 'c'                   not null,
    lastChecked         datetime    default '1971-01-01 00:00:00' not null,
    sponsorBlockExclude varchar(20)                               null,
    sponsorBlockInclude varchar(20)                               null,
    category            varchar(10)                               null,
    constraint youtube_feed_pk
        unique (name)
);

create table youtube_filter
(
    id          int auto_increment
        primary key,
    channelId   int                    null,
    filter      varchar(100)           not null,
    type        varchar(7) default '0' not null,
    action      varchar(1)             null,
    channelName varchar(100)           null,
    constraint youtube_filter_youtube_feed_name_fk
        foreign key (channelName) references youtube_feed (name)
);

create index filter_channel
    on youtube_filter (channelId);

create table youtube_video
(
    id        int auto_increment
        primary key,
    thumbnail varchar(50)              null,
    filename  varchar(100)             null,
    channelId int                      null,
    ytId      varchar(100)             not null,
    type      varchar(5)               null,
    status    varchar(5) default 'new' not null,
    title     varchar(100)             not null,
    published datetime                 not null,
    constraint video_channel_FK
        foreign key (channelId) references youtube_feed (id)
            on delete cascade
);
