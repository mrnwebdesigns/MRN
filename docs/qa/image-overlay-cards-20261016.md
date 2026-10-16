# Native image Cards — source qualification

Parent candidate **1.5.23** extends the existing Cards layout with an optional
image-and-caption presentation. Each card retains its native editable child row
and optional link/icon fields, with an additional image. Root/nested field keys
are unique. New fields control image ratio/loading, card gap, caption padding and
heading-to-copy gap. The rich introduction uses the new `card_intro` field so old
unrelated `content` metadata cannot become visible accidentally. Standard Cards
and timelines retain their existing rendering when the new fields are unset.

The presentation supports a caption below the image or an overlaid heading whose
description reveals on hover/focus. Unlinked reveal cards are named keyboard
focus stops; linked cards use their heading for an accessible name and keep safe
new-window attributes. Full copy remains visible on touch/narrow screens and
with reduced motion. CSS Grid allows long captions to grow rather than clipping
them to a fixed description height. Always-visible captions retain the legacy
5px hover/focus lift only with hover support, wide screens and no reduced-motion
preference. No new JavaScript, layout type or site CSS spacing patch is added.

The site mapper preserves 460/488 image geometry, 30px card gaps/caption padding,
12px reveal or 15px static caption gaps, three/four desktop columns, both action
styles, rich introduction/copy, repeated media and heading icons. Caption-only
cards use the native no-image fallback and existing media-icon fields. The curve
still requires native Graphic Element data; inactive historical links, audit
modal integration and image-free hover content remain explicit holds.

## Qualification

Source was loaded through the exact-clone CLI bootstrap, without replacing the
managed parent or changing activation/settings. The installed baseline remains
published parent 1.5.20 / RBL 0.2.2. Only owned drafts/journals were written, and all
were removed. All **63 authored source hashes** match the prior snapshot.

- **25 native assertions**: root/nested editable fields and unique media keys,
  ACF write/readback, exact geometry/loading, image order, rich copy, both caption
  variants, keyboard/link naming, malformed geometry refusal, default output,
  dormant-content compatibility and fixture removal.
- **120 existing assertions**: collection bounds 28, migration collections 33,
  other native presentations 40 and Body Text list controls 19.
- Converter **20261016.8**: **24 groups / 40 source fixtures** and **24 groups /
  34 installed fixtures**, all removed. Native fields, caption-only/icon cards,
  title entities, repeated media, both action styles and rendered query links
  survive repeat/readback/rollback. Missing contracts still refuse. Plans now
  bind parent builder/render files, styles/scripts and reusable-link helpers;
  a changed native renderer context refuses stale apply before target writes.
- Actual browser: three columns at 1280px, two at 1000px and one at 390px, without
  document overflow. Focus exposes the description and a 2px outline. Static
  caption focus produces the intended -5px transform. Mobile shows all copy.
  Reduced motion is source-reviewed; browser emulation is not claimed.
- Targeted MRN QA Engine WCAG 2 A/AA and 2.1 AA passes, with no ignored rules or
  regions. Initial deferred above-fold media measured LCP 3776ms; the native
  loading field fixes that configuration. Final component CWV: **LCP 192ms, CLS
  0, INP 32ms**, all good. These are local source-component results.
- API surface review: no new REST/AJAX/admin-post handlers, nonce paths or
  permissions. Existing ACF authorization remains in effect. Output uses native
  image/link helpers and escaped attributes; ratio and CSS lengths are bounded.
  PHP lint and diff checks pass. The staged MRN QA gate is recorded separately.

The public clone's SEO page was also inspected: all three legacy image-free
hover Cards have zero height and absolutely positioned captions. The mapper
reports a content decision rather than exposing that collapsed copy without a
review. This is separate from the native capability.

Source preview:
https://mrnwebdesigns-stack-migration.localhost/stack-image-cards-preview-20261016/

Content-free evidence is adjacent; proof images, raw source/schema and plans
remain private outside Git. Source acceptance, automatic publication, clone
adoption, new Classic Editor roundtrip and full-page acceptance remain separate
gates. No authored page converted and no production deployment ran. Remote
plugin-parity and site-owner deploy checks are deferred to promotion/rollout.
