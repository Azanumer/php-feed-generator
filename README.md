# PHP Feed Generator

A dependency-free RSS 2.0 and Atom 1.0 feed builder. One channel, one item list, two renderers — no XML library, no framework, no templating.

## Usage

```php
require 'src/FeedGenerator.php';

$feed = (new FeedGenerator('CorelHost Blog', 'https://corelhost.com/blog'))
    ->setDescription('Hosting tips, WordPress guides and server tutorials.')
    ->setLanguage('en-US');

$feed->addItem([
    'title'       => 'How to speed up WordPress with FastCGI cache',
    'link'        => 'https://corelhost.com/blog/wordpress-fastcgi-cache',
    'description' => '<p>Cache full pages in Nginx — no plugin required.</p>',
    'description_is_html' => true,
    'pub_date'    => '2026-11-08 10:00:00',
    'author'      => 'Azan Umer',
    'categories'  => ['WordPress', 'Performance'],
]);

header('Content-Type: application/rss+xml; charset=utf-8');
echo $feed->renderRss();   // or renderAtom() for Atom 1.0
```

## Item fields

| Field | Required | Notes |
|---|---|---|
| `title` | yes | Item title. |
| `link` | yes | Canonical URL of the item. |
| `description` | no | Plain text, or HTML when `description_is_html` is true. |
| `description_is_html` | no | HTML descriptions go in CDATA (RSS) or `type="html"` (Atom). |
| `pub_date` | no | `DateTime`, Unix timestamp, or any string `strtotime()` understands. Defaults to now. |
| `guid` | no | Defaults to the item link. A non-URL guid is emitted with `isPermaLink="false"` in RSS. |
| `author` | no | Author name (RSS `<author>`, Atom `<author><name>`). |
| `categories` | no | Array of category strings. |
| `enclosure` | no | `['url' => ..., 'length' => ..., 'type' => ...]` — podcast audio, video, etc. Requires `url` and `type`. |

## Notes

- Dates are normalized to UTC and formatted per spec: RFC 2822 for RSS, RFC 3339 for Atom.
- `lastBuildDate` (RSS) and `<updated>` (Atom) come from the newest item, or now when the feed is empty.
- A literal `]]>` inside an HTML description is split so the CDATA section stays well-formed.
- Missing channel title/link or item title/link throws `InvalidArgumentException` — fail loudly instead of emitting an invalid feed.

## License

MIT — see [LICENSE](LICENSE).
