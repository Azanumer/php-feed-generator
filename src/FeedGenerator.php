<?php
/**
 * FeedGenerator — dependency-free RSS 2.0 and Atom 1.0 feed builder.
 *
 * One channel, one item list, two renderers. Dates accept a DateTime,
 * a Unix timestamp, or any string strtotime() understands. All text is
 * XML-escaped on the way out; HTML descriptions are wrapped in CDATA
 * (RSS) or emitted as type="html" (Atom) so markup survives the feed.
 *
 * @author Azan Umer
 * @license MIT
 */

declare(strict_types=1);

class FeedGenerator
{
    /** @var string Feed title (required). */
    private string $title = '';

    /** @var string Canonical URL of the site/feed owner (required). */
    private string $link = '';

    /** @var string Short description of the feed. */
    private string $description = '';

    /** @var string BCP 47 language tag, e.g. "en-US". */
    private string $language = 'en-US';

    /** @var string Generator string written into the feed. */
    private string $generator = 'FeedGenerator';

    /** @var array<int, array<string, mixed>> Ordered feed items. */
    private array $items = [];

    public function __construct(string $title = '', string $link = '')
    {
        $this->title = $title;
        $this->link  = $link;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setLink(string $link): self
    {
        $this->link = $link;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function setGenerator(string $generator): self
    {
        $this->generator = $generator;
        return $this;
    }

    /**
     * Add one feed item.
     *
     * Accepted keys:
     *   title (string, required), link (string, required),
     *   description (string), description_is_html (bool, default false),
     *   pub_date (DateTimeInterface|int|string, default now),
     *   guid (string, defaults to link), author (string),
     *   categories (string[]), enclosure (array{url,length,type}).
     *
     * @throws InvalidArgumentException When title or link is missing.
     */
    public function addItem(array $item): self
    {
        $title = trim((string) ($item['title'] ?? ''));
        $link  = trim((string) ($item['link'] ?? ''));
        if ('' === $title) {
            throw new InvalidArgumentException('Feed item requires a title.');
        }
        if ('' === $link) {
            throw new InvalidArgumentException('Feed item requires a link.');
        }

        $item['title']               = $title;
        $item['link']                = $link;
        $item['description']         = (string) ($item['description'] ?? '');
        $item['description_is_html'] = (bool) ($item['description_is_html'] ?? false);
        $item['pub_date']            = $this->normalizeDate($item['pub_date'] ?? null);
        $item['guid']                = (string) ($item['guid'] ?? $link);
        $item['author']              = (string) ($item['author'] ?? '');
        $item['categories']          = array_values(array_filter(array_map(
            'strval',
            (array) ($item['categories'] ?? [])
        )));
        if (isset($item['enclosure'])) {
            $enc = $item['enclosure'];
            if (!is_array($enc) || empty($enc['url']) || empty($enc['type'])) {
                throw new InvalidArgumentException('Enclosure requires url and type.');
            }
            $item['enclosure'] = [
                'url'    => (string) $enc['url'],
                'length' => (int) ($enc['length'] ?? 0),
                'type'   => (string) $enc['type'],
            ];
        }
        $this->items[] = $item;
        return $this;
    }

    /**
     * Render the feed as RSS 2.0.
     *
     * @throws InvalidArgumentException When the channel title or link is missing.
     */
    public function renderRss(): string
    {
        $this->assertChannel();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0">' . "\n<channel>\n";
        $xml .= $this->tag('title', $this->title);
        $xml .= $this->tag('link', $this->link);
        $xml .= $this->tag('description', $this->description);
        $xml .= $this->tag('language', $this->language);
        $xml .= $this->tag('generator', $this->generator);
        $xml .= $this->tag('lastBuildDate', $this->newestDate()->format(DATE_RSS));

        foreach ($this->items as $item) {
            $xml .= "<item>\n";
            $xml .= $this->tag('title', $item['title']);
            $xml .= $this->tag('link', $item['link']);
            if ('' !== $item['description']) {
                $xml .= $item['description_is_html']
                    ? $this->cdataTag('description', $item['description'])
                    : $this->tag('description', $item['description']);
            }
            $xml .= $this->tag('pubDate', $item['pub_date']->format(DATE_RSS));
            $xml .= '<guid isPermaLink="' . ($item['guid'] === $item['link'] ? 'true' : 'false') . '">'
                . $this->escape($item['guid']) . "</guid>\n";
            if ('' !== $item['author']) {
                $xml .= $this->tag('author', $item['author']);
            }
            foreach ($item['categories'] as $category) {
                $xml .= $this->tag('category', $category);
            }
            if (isset($item['enclosure'])) {
                $enc = $item['enclosure'];
                $xml .= '<enclosure url="' . $this->escape($enc['url']) . '" length="'
                    . $enc['length'] . '" type="' . $this->escape($enc['type']) . '" />' . "\n";
            }
            $xml .= "</item>\n";
        }

        $xml .= "</channel>\n</rss>\n";
        return $xml;
    }

    /**
     * Render the feed as Atom 1.0.
     *
     * @throws InvalidArgumentException When the channel title or link is missing.
     */
    public function renderAtom(): string
    {
        $this->assertChannel();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= $this->tag('title', $this->title);
        $xml .= '<link rel="alternate" href="' . $this->escape($this->link) . '" />' . "\n";
        $xml .= $this->tag('id', $this->link);
        $xml .= $this->tag('updated', $this->newestDate()->format(DATE_ATOM));
        if ('' !== $this->description) {
            $xml .= $this->tag('subtitle', $this->description);
        }
        $xml .= $this->tag('generator', $this->generator);

        foreach ($this->items as $item) {
            $xml .= "<entry>\n";
            $xml .= $this->tag('title', $item['title']);
            $xml .= '<link rel="alternate" href="' . $this->escape($item['link']) . '" />' . "\n";
            $xml .= $this->tag('id', $item['guid']);
            $xml .= $this->tag('updated', $item['pub_date']->format(DATE_ATOM));
            if ('' !== $item['description']) {
                if ($item['description_is_html']) {
                    $xml .= '<summary type="html">' . $this->escape($item['description']) . "</summary>\n";
                } else {
                    $xml .= $this->tag('summary', $item['description']);
                }
            }
            if ('' !== $item['author']) {
                $xml .= '<author><name>' . $this->escape($item['author']) . "</name></author>\n";
            }
            foreach ($item['categories'] as $category) {
                $xml .= '<category term="' . $this->escape($category) . '" />' . "\n";
            }
            if (isset($item['enclosure'])) {
                $enc = $item['enclosure'];
                $xml .= '<link rel="enclosure" href="' . $this->escape($enc['url']) . '" length="'
                    . $enc['length'] . '" type="' . $this->escape($enc['type']) . '" />' . "\n";
            }
            $xml .= "</entry>\n";
        }

        $xml .= "</feed>\n";
        return $xml;
    }

    /** @return array<int, array<string, mixed>> The items added so far. */
    public function items(): array
    {
        return $this->items;
    }

    /** @throws InvalidArgumentException */
    private function assertChannel(): void
    {
        if ('' === trim($this->title)) {
            throw new InvalidArgumentException('Feed requires a channel title.');
        }
        if ('' === trim($this->link)) {
            throw new InvalidArgumentException('Feed requires a channel link.');
        }
    }

    /**
     * Normalize any accepted date form to a DateTimeImmutable (UTC).
     *
     * @param DateTimeInterface|int|string|null $date
     * @throws InvalidArgumentException On an unparseable date string.
     */
    private function normalizeDate($date): DateTimeImmutable
    {
        if ($date instanceof DateTimeImmutable) {
            return $date->setTimezone(new DateTimeZone('UTC'));
        }
        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date)
                ->setTimezone(new DateTimeZone('UTC'));
        }
        if (is_int($date)) {
            return (new DateTimeImmutable('@' . $date))->setTimezone(new DateTimeZone('UTC'));
        }
        if (is_string($date) && '' !== trim($date)) {
            $ts = strtotime($date);
            if (false === $ts) {
                throw new InvalidArgumentException('Unparseable date: ' . $date);
            }
            return (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('UTC'));
        }
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** Newest item date, or now when the feed is empty. */
    private function newestDate(): DateTimeImmutable
    {
        $newest = null;
        foreach ($this->items as $item) {
            if (null === $newest || $item['pub_date'] > $newest) {
                $newest = $item['pub_date'];
            }
        }
        return $newest ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function tag(string $name, string $value): string
    {
        return '<' . $name . '>' . $this->escape($value) . '</' . $name . ">\n";
    }

    private function cdataTag(string $name, string $value): string
    {
        // A literal "]]>" inside the content would close the CDATA section
        // early; split it so the output stays well-formed.
        $safe = str_replace(']]>', ']]]]><![CDATA[>', $value);
        return '<' . $name . '><![CDATA[' . $safe . ']]></' . $name . ">\n";
    }
}
