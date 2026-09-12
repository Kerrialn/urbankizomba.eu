<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Service\Content\Article;
use App\Service\Content\ArticleLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The articles: what a practice finds in a search engine before it has heard of
 * us.
 *
 * Public and asking for nothing, for the same reason the calculator is. Somebody
 * looking up "kdy proplatí pojišťovna preventivní prohlídku" has a question, not
 * an intention to buy software, and a login in front of the answer ends the
 * visit.
 *
 * The routes are published in both languages; **the texts, so far, are Czech
 * only**. Those are different facts and the difference has to reach the page,
 * because a Czech article advertising an English URL that has nothing behind it
 * is worse for a crawler than one that never mentions English.
 *
 * So each page declares what it is actually published in, via
 * `published_locales`: base.html.twig narrows hreflang and the language switcher
 * to that set, {@see SitemapController} lists only those URLs, and an English
 * request for a Czech-only article gets a 404 rather than Czech text under an
 * English address.
 *
 * Translating an article is then one line in its frontmatter — adding `en` to
 * `alternates` — plus the English file. Nothing here changes.
 */
final class ArticleController extends AbstractController
{
    #[Route(path: [
        'cs' => '/clanky',
        'en' => '/articles',
    ], name: 'app_articles', methods: ['GET'])]
    public function index(Request $request, ArticleLibrary $articles): Response
    {
        return $this->render('article/index.html.twig', [
            'articles' => $articles->all($request->getLocale()),
            'published_locales' => $articles->publishedLocales(),
        ]);
    }

    /**
     * The slug is the filename. It is constrained to the same shape the library
     * validates, so a path traversal never reaches routing in the first place.
     *
     * A slug that exists in another language is still a 404 here: serving Czech
     * under /en/article/… would be the duplicate the hreflang rules exist to
     * prevent, and the language switcher never offers the link in the first
     * place.
     */
    #[Route(path: [
        'cs' => '/clanky/{slug}',
    ], name: 'app_article', requirements: [
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ], methods: ['GET'])]
    public function show(string $slug, Request $request, ArticleLibrary $articles): Response
    {
        $article = $articles->find($slug, $request->getLocale());

        if (! $article instanceof Article) {
            throw new NotFoundHttpException();
        }

        return $this->render('article/show.html.twig', [
            'article' => $article,
            'published_locales' => $article->alternates,
            // Everything else published, minus this one: an article nothing links
            // to is an article a crawler reaches once and a reader leaves from.
            'more' => array_values(array_filter(
                $articles->all($request->getLocale()),
                static fn (Article $other): bool => $other->slug !== $article->slug,
            )),
        ]);
    }
}
