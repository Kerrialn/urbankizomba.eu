<?php

declare(strict_types=1);

namespace App\Service\Content;

use DateTimeImmutable;
use DateTimeInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The articles on disk, parsed.
 *
 * Not a Doctrine repository despite the shape: the articles are Markdown files
 * in content/guides, so this reads the filesystem. Named a library rather than
 * a repository so nobody goes looking for an entity.
 *
 * Each file is cached against its own mtime, which means an edit invalidates
 * itself in dev and a deploy invalidates everything without a cache:clear.
 * Globbing the directory is a stat per file and stays out of the cache — the
 * listing has to notice a file that was added.
 */
final readonly class ArticleLibrary
{
    private MarkdownConverter $converter;

    public function __construct(
        private CacheInterface $cache,
        #[Autowire('%kernel.project_dir%/content/guides')]
        private string $directory,
    ) {
        $environment = new Environment([
            // The articles are ours, written in this repository and reviewed in
            // a diff. Escaping their HTML would only mean the tables come out as
            // text; there is no untrusted author to defend against.
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        // Every article compares something in a table — insurers against
        // intervals, columns against what they are for. Core CommonMark has no
        // tables, so without this they render as pipes.
        $environment->addExtension(new TableExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Everything published in this language, newest first.
     *
     * @return list<Article>
     */
    public function all(string $locale): array
    {
        $articles = [];

        foreach (glob($this->directory . '/*.md') ?: [] as $file) {
            // The directory's own README is documentation for whoever writes the
            // next article, not an article.
            if (basename($file) === 'README.md') {
                continue;
            }

            $article = $this->read($file);

            if ($article->isAvailableIn($locale)) {
                $articles[] = $article;
            }
        }

        usort($articles, static fn (Article $a, Article $b): int => $b->published <=> $a->published);

        return $articles;
    }

    /**
     * Null rather than an exception for a slug that is not there: an unknown
     * slug is a 404, and that decision belongs to the controller.
     */
    public function find(string $slug, string $locale): ?Article
    {
        // Built from the slug, so it has to be proven safe before it touches the
        // filesystem — a slug is a URL segment and arrives from the visitor.
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        $file = $this->directory . '/' . $slug . '.md';

        if (! is_file($file)) {
            return null;
        }

        $article = $this->read($file);

        return $article->isAvailableIn($locale) ? $article : null;
    }

    /**
     * The languages at least one article is published in.
     *
     * The routes exist in every active language; the texts do not. This is the
     * set that hreflang, the language switcher and the sitemap are allowed to
     * mention for the index page — an English URL listing nothing is a page a
     * crawler should never have been sent to.
     *
     * @return list<string>
     */
    public function publishedLocales(): array
    {
        $locales = [];

        foreach (glob($this->directory . '/*.md') ?: [] as $file) {
            if (basename($file) === 'README.md') {
                continue;
            }

            foreach ($this->read($file)->alternates as $locale) {
                $locales[$locale] = true;
            }
        }

        return array_keys($locales);
    }

    /**
     * The most recent change to any article, for the sitemap's lastmod.
     *
     * A real date, taken from the frontmatter the author maintains — unlike a
     * lastmod on the marketing pages, which would have to be invented.
     */
    public function lastUpdated(string $locale): ?DateTimeImmutable
    {
        $dates = array_map(static fn (Article $article): DateTimeImmutable => $article->updated, $this->all($locale));

        return $dates === [] ? null : max($dates);
    }

    private function read(string $file): Article
    {
        $key = 'article.' . sha1($file) . '.' . (filemtime($file) ?: 0);

        return $this->cache->get($key, fn (ItemInterface $item): Article => $this->parse($file));
    }

    /**
     * A frontmatter date, whichever of the three shapes YAML hands back: a
     * DateTime with PARSE_DATETIME, an integer timestamp without it, or a string
     * where the author quoted the value.
     */
    private function date(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value)) {
            return (new DateTimeImmutable())->setTimestamp($value);
        }

        return new DateTimeImmutable((string) $value);
    }

    private function parse(string $file): Article
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Article "%s" could not be read.', $file));
        }

        if (preg_match('/^---\R(.*?)\R---\R(.*)$/s', $contents, $matches) !== 1) {
            throw new RuntimeException(sprintf('Article "%s" has no frontmatter.', $file));
        }

        // PARSE_DATETIME, or a plain `published: 2026-09-10` comes back as a
        // Unix timestamp and DateTimeImmutable is handed "1788998400" to read as
        // a date string. Quoting the dates in every article would work too and
        // would be one unquoted date away from breaking again.
        $meta = Yaml::parse($matches[1], Yaml::PARSE_DATETIME);

        if (! is_array($meta)) {
            throw new RuntimeException(sprintf('Article "%s" has unreadable frontmatter.', $file));
        }

        $body = $matches[2];
        $heading = '';

        // The body opens with its own H1 so the file reads as a document on its
        // own — on GitHub, in an editor, in a review. The page renders that
        // heading in its own furniture, with the date and reading time beside
        // it, so it is lifted out here rather than left to appear twice.
        if (preg_match('/^#\s+(.+?)\R+/', $body, $title) === 1) {
            $heading = trim($title[1]);
            $body = substr($body, strlen($title[0]));
        }

        foreach (['slug', 'title', 'meta_description', 'published'] as $required) {
            if (! isset($meta[$required])) {
                throw new RuntimeException(sprintf(
                    'Article "%s" is missing "%s" — every article needs one to be indexable.',
                    basename($file),
                    $required,
                ));
            }
        }

        $published = $this->date($meta['published']);

        return new Article(
            slug: (string) $meta['slug'],
            locale: (string) ($meta['locale'] ?? 'en'),
            alternates: array_map('strval', (array) ($meta['alternates'] ?? [$meta['locale'] ?? 'en'])),
            title: (string) $meta['title'],
            metaDescription: (string) $meta['meta_description'],
            heading: $heading !== '' ? $heading : (string) $meta['title'],
            html: $this->converter->convert($body)->getContent(),
            published: $published,
            updated: isset($meta['updated']) ? $this->date($meta['updated']) : $published,
            author: isset($meta['author']) ? (string) $meta['author'] : null,
            category: isset($meta['category']) ? (string) $meta['category'] : null,
            readingMinutes: isset($meta['reading_minutes']) ? (int) $meta['reading_minutes'] : null,
            keywords: array_map('strval', (array) ($meta['keywords'] ?? [])),
            og: (array) ($meta['og'] ?? []),
            schema: (array) ($meta['schema'] ?? []),
            cta: (array) ($meta['cta'] ?? []),
        );
    }
}
