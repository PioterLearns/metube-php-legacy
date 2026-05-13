yt-dlp wrapper for archiving YouTube videos.

Written and updated for personal use, over couple of years.\
Git history recently lost... Not much to cry about though (https://xkcd.com/1296/) \
Was never meant to be public.\
**NOT representative of how I write production code!!!**\
Generally I don't recommend actually using this to anyone who's not me.\
Only uploading it as a reference for future re-write in a different language / framework as a learning experience for that technology.\
Not sure what said technology will be at the moment, as I have 2 other projects that take priority at the time of writing this.

Included "features":
 - CLI operated only. The way PHP was meant to be used! /s
 - Updates feeds based on RSS (vides that get unlisted, and then re-listed potentially get lost, because I only check "new videos" by checking latest publish date)
 - Composer dependancies, and Migrations configurations that ended up orphaned, since I didn't care develop those features
 - Quite a bit of spaghetti, hardcoded paths, and other smells, that need taking care of
