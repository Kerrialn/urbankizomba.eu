# Guides

Short articles that bring people to the site from a search engine: "what is
urban kiz", "urban kiz festivals in Europe", "how to find a social in a new
city". They are rendered by `ArticleController` at `/guides` and
`/guides/{slug}`, parsed by `ArticleLibrary` (Markdown to HTML, frontmatter to
meta tags). Adding a guide means adding a file; the index, the sitemap and the
footer find it themselves.

## Frontmatter

| Key | Where it goes |
| --- | --- |
| `slug` | The URL. Must match the filename. |
| `title` | `<title>`, `og:title`, `twitter:title` |
| `meta_description` | `<meta name="description">`, `og:description` |
| `locale`, `alternates` | Language of the text and the languages it exists in. English only for now: `alternates: [en]`. |
| `published`, `updated` | Absolute dates. `updated` feeds the sitemap's `lastmod`. |
| `author`, `category`, `reading_minutes`, `keywords` | Shown on the page and in the meta tags. |
| `og.image` | A path under `public/`; without one the site-wide card is used. |
| `schema.faq` | A list of `{q, a}` rendered as `FAQPage` structured data. |
| `cta.primary`, `cta.secondary` | `{route, label}` for the buttons at the end. |

The body opens with its own `# Heading`, which the page lifts out and renders
as the H1 with the date beside it.

## Writing them

The audience is somebody who does not know the scene yet, or knows it and has
just landed in a new city. Say what a thing is before saying what to do about
it. Link to the calendar and the city pages rather than to specific events,
which go stale. Do not make claims about a festival's line-up or a school's
quality: those belong in a listing, where the organiser owns them.
