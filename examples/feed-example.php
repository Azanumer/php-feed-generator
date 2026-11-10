<?php
/**
 * Example: build a small blog feed and print it as RSS 2.0 and Atom 1.0.
 *
 * Usage: php examples/feed-example.php > /tmp/feed.xml
 *        php examples/feed-example.php atom > /tmp/feed.atom
 */

declare(strict_types=1);

require __DIR__ . '/../src/FeedGenerator.php';

$feed = (new FeedGenerator('CorelHost Blog', 'https://corelhost.com/blog'))
    ->setDescription('Hosting tips, WordPress guides and server tutorials.')
    ->setLanguage('en-US')
    ->setGenerator('FeedGenerator 1.0');

$feed->addItem([
    'title'       => 'How to speed up WordPress with FastCGI cache',
    'link'        => 'https://corelhost.com/blog/wordpress-fastcgi-cache',
    'description' => '<p>Cache full pages in Nginx and serve them in under 50ms — no plugin required.</p>',
    'description_is_html' => true,
    'pub_date'    => '2026-11-08 10:00:00',
    'author'      => 'Azan Umer',
    'categories'  => ['WordPress', 'Performance'],
]);

$feed->addItem([
    'title'      => 'Let\'s Encrypt auto-renewal, the safe way',
    'link'       => 'https://corelhost.com/blog/letsencrypt-auto-renewal',
    'description' => 'A certbot renew wrapper that only reloads the web server when a certificate actually changed.',
    'pub_date'   => new DateTimeImmutable('2026-11-05 14:30:00'),
    'categories' => ['Security', 'Linux'],
    'enclosure'  => [
        'url'    => 'https://corelhost.com/podcast/letsencrypt.mp3',
        'length' => 4823441,
        'type'   => 'audio/mpeg',
    ],
]);

$feed->addItem([
    'title'      => 'Why your MySQL backups need a verify step',
    'link'       => 'https://corelhost.com/blog/verify-mysql-backups',
    'description' => 'A backup you never restored is a hope, not a backup.',
    'pub_date'   => time() - 86400,
    'guid'       => 'urn:corelhost:post:verify-mysql-backups',
]);

$format = $argv[1] ?? 'rss';
echo 'atom' === $format ? $feed->renderAtom() : $feed->renderRss();
