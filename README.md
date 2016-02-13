# Yelp Bookmark Notification on Slack

Note that `lib/OAuth.php` is copied from [Yelp/yelp-api](https://github.com/Yelp/yelp-api/tree/master/v2/php/lib).

This is a helper bot on my Slack channel which could notify me nearby bookmarked restaurants on Yelp.

Some notable tricks:

- Yelp doesn't provide user authenticated information yet. But somehow the users' bookmarks are public! So I can parse the bookmark HTML page and extract restaurant IDs. One potential problem is if I've bookmarked too many restaurants, pagination may complicate the extraction process.
- On the other hand Yelp does provide API for *business* query, by which I can get restaurants' ratings, categories, and most importantly, coordinates.
- For now bookmarks are extracted offline and stored into a JSON file by cron jobs daily (at midnight).
- On Android, I use [herverenault/Self-Hosted-GPS-Tracker](https://github.com/herverenault/Self-Hosted-GPS-Tracker) to send GPS tracking information to my personal server.
- Once the server gets my coordinates it will calculate the nearest bookmarked restaurants which are not notified within 10 minutes, and use Slack's incoming webhooks to send notifications.

Here's the screenshot on my phone.

![screenshot](http://i.imgur.com/kU2L4S5l.png)
