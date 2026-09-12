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
 * The guides: what somebody finds in a search engine before they know the
 * site exists. "What is urban kiz", "how do I find a social in a new city".
 *
 * Markdown files in content/guides, reviewed like code. See
 * {@see ArticleLibrary} for the frontmatter.
 */
final class ArticleController extends AbstractController
{
    #[Route('/guides', name: 'app_articles', methods: ['GET'])]
    public function index(Request $request, ArticleLibrary $articles): Response
    {
        return $this->render('article/index.html.twig', [
            'articles' => $articles->all($request->getLocale()),
            'published_locales' => $articles->publishedLocales(),
        ]);
    }

    #[Route('/guides/{slug}', name: 'app_article', requirements: [
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
            'more' => array_values(array_filter(
                $articles->all($request->getLocale()),
                static fn (Article $other): bool => $other->slug !== $article->slug,
            )),
        ]);
    }
}
