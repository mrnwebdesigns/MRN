# Schema and AI Discovery Baseline

This is the canonical structured-data and crawler baseline for new MRN stack sites.

## Ownership

- **Business Information** is the canonical source for reusable theme tokens and visible operational content, including logo variants, profile, phone/text numbers, address, business hours, and holiday hours.
- **Active SEO provider** owns public organization identity and the base `Organization`, `WebSite`, `WebPage`, breadcrumb, article, canonical, social, and XML sitemap output. SEOPress is the preferred provider for new stack sites; SmartCrawl remains supported for existing sites during migration.
- **MRN Schema Bridge** assigns stable entity references, applies MRN author/privacy policy, and supplies stack-specific content mappings without replacing SEOPress identity fields.
- **MRN SEO Helper** owns public post-type title and meta-description templates.
- Themes and content components should provide visible source data, not emit a competing site-wide schema graph.

## Rollout Defaults

MRN Schema Bridge fills missing SmartCrawl settings once per bridge release on legacy SmartCrawl sites. Existing site choices win.

The baseline lets SEOPress own sitemap, title/meta, social/canonical, and public organization/schema output. Business Information remains available to theme components and the token layer; its organization enrichment is used only by legacy SmartCrawl sites.

Author, date, search, comment, audio, and video schema remain conservative until a site intentionally configures them.

## Content Mapping

| Content or intent | Baseline schema |
| --- | --- |
| Home and standard pages | Active provider `WebSite` and `WebPage` graph |
| About page | `AboutPage` |
| Contact page | `ContactPage` plus organization `ContactPoint` data |
| Service page or service CPT | `Service` linked to the canonical organization and page |
| Case study/project | `Article` and `CreativeWork` linked to the canonical page |
| Public team profile | `ProfilePage` and `Person` |
| Visible gallery | `CollectionPage` and `ImageGallery` |
| Visible testimonial quote | `Quotation` with speaker attribution; never a rating by default |
| Other public CPTs | Safe active-provider `WebPage`/article baseline until explicitly mapped |

The Classic Editor **SEO & Schema** panel defaults to **Auto**. Editors can override a page to About, Collection, Contact, Profile, or Service, add a schema description, or disable only bridge-owned supplemental schema. It does not expose raw JSON-LD.

## Author Policy

The rollout default is organization-authored content. Internal WordPress users are removed from public schema and references are replaced with the canonical organization. A site publishing real bylined editorial work can switch to public authors or use the allowlist policy in Business Information.

## AI Discovery and Robots

On public sites, WordPress's virtual `robots.txt` uses separate controls for retrieval and training:

- AI search/retrieval is allowed by default for `OAI-SearchBot`, `Claude-SearchBot`, and `Claude-User`.
- model training is blocked by default for `GPTBot`, `ClaudeBot`, and `Google-Extended`.

These are crawl preferences, not access-control guarantees. Important pages must remain indexable, internally linked, canonically identified, fast, accessible, and supported by visible factual content.

The stack does not publish `llms.txt`. Revisit it only if major AI retrieval systems establish a documented, interoperable use for it.

## New-Site Checklist

1. Complete the visible and operational fields in **Business Information** for theme and token consumers.
2. Complete SEOPress Knowledge Graph and, where applicable, Local Business settings; confirm organization name, logo, URL, type, contact details, address, and coordinates are accurate.
3. Choose the MRN author policy and independently review AI retrieval and model-training preferences.
4. Confirm SEOPress, its sitemap, title/meta templates, and site representation were initialized without overwriting intentional settings.
5. Assign explicit page intent only where Auto cannot infer it.
6. Confirm every public CPT has an indexation decision, title/meta template, archive decision, sitemap inclusion decision, and schema mapping.
7. Run **Tools > Schema Health** against the production sitemap after the site becomes public.
8. Inspect representative URLs in a schema validator and the applicable search-engine rich-result test.
9. Verify the live canonical, robots directives, XML sitemap, HTTP status, and rendered structured-data graph.
10. Monitor search-console indexing and enhancement reports after launch.

## Release QA

Run the schema bridge contract test and scoped MRN QA:

```bash
php /Users/khofmeyer/Development/MRN-plugins/mrn-schema-bridge/tests/contract-regression.php
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run \
  --project-root /Users/khofmeyer/Development/MRN-plugins/mrn-schema-bridge
```

New Stack sites provision filterable automatic BlogPosting (`post`) and NewsArticle (`press_release`) templates while SEOPress Free and PRO are active during bootstrap. Complete Business Information identity is synchronized into SEOPress on bootstrap and later ACF options-page saves; incomplete values never erase existing provider settings. Verify the bootstrap ordering contract with:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/qa-seopress-schema-bootstrap.sh
```

For a full stack rollout, also run the theme, security, runtime, accessibility, performance, parity, and rollout-contract checks in `ROLLOUT_CHECKLIST.md`.
