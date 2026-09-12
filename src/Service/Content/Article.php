<?php

declare(strict_types=1);

namespace App\Service\Content;

use DateTimeImmutable;

/**
 * One published article, parsed from a Markdown file in content/articles.
 *
 * The frontmatter fields exist to fill the meta tags base.html.twig already
 * renders — title, description, canonical, Open Graph, structured data — rather
 * than introducing a second way to set the same tags. What is not in the
 * frontmatter is not on the page.
 *
 * Immutable and free of Doctrine: articles are files in the repository, edited
 * and reviewed like code, not rows somebody can change in production. That is
 * the point of keeping them here — a change to a guide is a deployment, with a
 * diff and a review, not a CMS edit.
 */
final readonly class Article
{
    /**
     * @param list<string>                $alternates    locales this text exists in
     * @param list<string>                $keywords
     * @param array<string, mixed>        $og
     * @param array<string, mixed>        $schema
     * @param array<string, mixed>        $cta
     */
    public function __construct(
        public string $slug,
        public string $locale,
        public array $alternates,
        public string $title,
        public string $metaDescription,
        public string $heading,
        public string $html,
        public DateTimeImmutable $published,
        public DateTimeImmutable $updated,
        public ?string $author = null,
        public ?string $category = null,
        public ?int $readingMinutes = null,
        public array $keywords = [],
        public array $og = [],
        public array $schema = [],
        public array $cta = [],
    ) {
    }

    /**
     * Whether this text is published in the language being requested.
     *
     * Articles are Czech-only for now, and a page that exists in one language is
     * the normal case rather than an error — the routes are declared for that
     * language alone, so hreflang and the language switcher both stay honest
     * without anything here having to suppress them.
     */
    public function isAvailableIn(string $locale): bool
    {
        return in_array($locale, $this->alternates, true);
    }

    public function ogTitle(): string
    {
        return is_string($this->og['title'] ?? null) ? $this->og['title'] : $this->title;
    }

    public function ogDescription(): string
    {
        return is_string($this->og['description'] ?? null)
            ? $this->og['description']
            : $this->metaDescription;
    }

    public function ogImage(): ?string
    {
        return is_string($this->og['image'] ?? null) ? $this->og['image'] : null;
    }

    /**
     * The JSON-LD graph for this page.
     *
     * Built here rather than in the template because it is data with a shape,
     * and a nested object assembled out of Twig hashes is unreadable by the
     * second nesting level. The template's job is to json_encode it.
     *
     * Two nodes where the article carries questions: search engines treat
     * Article and FAQPage as separate things, and an FAQ folded into the Article
     * node is simply ignored.
     *
     * @param array<string, mixed> $company
     *
     * @return array<string, mixed>
     */
    public function jsonLd(string $canonicalUrl, array $company, string $logoUrl): array
    {
        $publisher = [
            '@type' => 'Organization',
            'name' => $company['name'] ?? '',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $logoUrl,
            ],
        ];

        $article = [
            '@type' => is_string($this->schema['type'] ?? null) ? $this->schema['type'] : 'Article',
            'headline' => is_string($this->schema['headline'] ?? null)
                ? $this->schema['headline']
                : $this->heading,
            'description' => $this->metaDescription,
            'inLanguage' => $this->locale,
            'datePublished' => $this->published->format('Y-m-d'),
            'dateModified' => $this->updated->format('Y-m-d'),
            'mainEntityOfPage' => $canonicalUrl,
            'url' => $canonicalUrl,
            'publisher' => $publisher,
        ];

        if ($this->author !== null) {
            $article['author'] = [
                '@type' => 'Organization',
                'name' => $this->author,
            ];
        }

        if (is_string($this->schema['section'] ?? null)) {
            $article['articleSection'] = $this->schema['section'];
        }

        $graph = [$article];
        $faq = $this->faqNode();

        if ($faq !== null) {
            $graph[] = $faq;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function faqNode(): ?array
    {
        $questions = $this->schema['faq'] ?? null;

        if (! is_array($questions) || $questions === []) {
            return null;
        }

        $entities = [];

        foreach ($questions as $question) {
            if (! is_array($question) || ! isset($question['q'], $question['a'])) {
                continue;
            }

            $entities[] = [
                '@type' => 'Question',
                'name' => (string) $question['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => (string) $question['a'],
                ],
            ];
        }

        return $entities === [] ? null : [
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
