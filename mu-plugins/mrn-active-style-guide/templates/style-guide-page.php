<?php
/**
 * Logged-in Style Guide front-end template.
 *
 * @var string $template
 */

defined('ABSPATH') || exit;

get_header();
?>
<?php mrn_active_style_guide_print_shared_styles(); ?>
<style id="mrn-active-style-guide-page-styles">
    .mrn-active-style-guide-page {
        padding: 48px 0 72px;
        background: #fff;
    }

    .mrn-active-style-guide-page .entry-content,
    .mrn-active-style-guide-page .entry-header {
        max-width: none;
    }

    .mrn-active-style-guide-page__inner {
        width: min(1240px, calc(100% - 48px));
        margin: 0 auto;
    }

    .mrn-active-style-guide-page__layout {
        display: grid;
        grid-template-columns: 260px minmax(0, 1fr);
        gap: 32px;
        align-items: start;
    }

    .mrn-active-style-guide-page__sidebar {
        position: sticky;
        top: 96px;
        display: grid;
        gap: 16px;
    }

    .mrn-active-style-guide-page__sidebar-card {
        padding: 18px;
        border: 1px solid #dcdcde;
        background: #fff;
    }

    .mrn-active-style-guide-page__sidebar-title {
        margin: 0 0 12px;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .mrn-active-style-guide-page__search {
        width: 100%;
        min-height: 42px;
        padding: 0 12px;
        border: 1px solid #c3c4c7;
        box-sizing: border-box;
        font: inherit;
    }

    .mrn-active-style-guide-page__nav {
        display: grid;
        gap: 8px;
    }

    .mrn-active-style-guide-page__nav-group + .mrn-active-style-guide-page__nav-group {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #f0f0f1;
    }

    .mrn-active-style-guide-page__nav-label {
        margin: 0 0 8px;
        color: #50575e;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .mrn-active-style-guide-page__nav a {
        display: inline-flex;
        align-items: center;
        min-height: 36px;
        padding: 0 12px;
        border: 1px solid #dcdcde;
        color: #1d2327;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
    }

    .mrn-active-style-guide-page__content {
        display: grid;
        gap: 40px;
    }

    .mrn-active-style-guide-page__header {
        display: grid;
        gap: 18px;
        padding-bottom: 24px;
        border-bottom: 1px solid #dcdcde;
    }

    .mrn-active-style-guide-page__eyebrow {
        margin: 0;
        color: #50575e;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .mrn-active-style-guide-page__title {
        margin: 0;
    }

    .mrn-active-style-guide-page__intro {
        max-width: 720px;
        margin: 0;
        color: #50575e;
    }

    .mrn-active-style-guide-page__meta {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .mrn-active-style-guide-page__meta-item {
        padding: 14px;
        border: 1px solid #dcdcde;
        background: #fff;
    }

    .mrn-active-style-guide-page__meta-item strong {
        display: block;
        margin-bottom: 6px;
        font-size: 13px;
    }

    .mrn-active-style-guide-page__group {
        display: grid;
        gap: 22px;
    }

    .mrn-active-style-guide-page__group-header {
        display: grid;
        gap: 8px;
    }

    .mrn-active-style-guide-page__group-title {
        margin: 0;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #50575e;
    }

    .mrn-active-style-guide-page__group-intro {
        max-width: 760px;
        margin: 0;
        color: #50575e;
    }

    .mrn-active-style-guide-page__section + .mrn-active-style-guide-page__section {
        margin-top: 28px;
    }

    .mrn-active-style-guide-page__section h2 {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .mrn-active-style-guide-page__section-copy {
        max-width: 720px;
        margin: 0 0 16px;
        color: #50575e;
    }

    .mrn-active-style-guide-page__section.is-hidden,
    .mrn-active-style-guide-page__nav-item.is-hidden,
    .mrn-active-style-guide-page__group.is-hidden {
        display: none;
    }

    .mrn-active-style-guide-page__empty {
        display: none;
        padding: 24px;
        border: 1px dashed #c3c4c7;
        color: #50575e;
        background: #f6f7f7;
    }

    .mrn-active-style-guide-page__empty.is-visible {
        display: block;
    }

    @media (max-width: 782px) {
        .mrn-active-style-guide-page {
            padding: 32px 0 56px;
        }

        .mrn-active-style-guide-page__inner {
            width: min(1240px, calc(100% - 32px));
        }

        .mrn-active-style-guide-page__layout {
            grid-template-columns: 1fr;
        }

        .mrn-active-style-guide-page__sidebar {
            position: static;
        }
    }
</style>
<main id="primary" class="site-main mrn-active-style-guide-page">
    <div class="mrn-active-style-guide-page__inner">
        <div class="mrn-active-style-guide-page__layout">
            <aside class="mrn-active-style-guide-page__sidebar" aria-label="Style guide navigation">
                <div class="mrn-active-style-guide-page__sidebar-card">
                    <h2 class="mrn-active-style-guide-page__sidebar-title">Find Elements</h2>
                    <input type="search" class="mrn-active-style-guide-page__search" placeholder="Search buttons, links, forms..." aria-label="Search style guide" />
                </div>
                <nav class="mrn-active-style-guide-page__sidebar-card mrn-active-style-guide-page__nav" aria-label="Style guide sections">
                    <div class="mrn-active-style-guide-page__nav-group">
                        <p class="mrn-active-style-guide-page__nav-label">Foundations</p>
                        <a class="mrn-active-style-guide-page__nav-item" href="#colors" data-target="colors">Colors</a>
                        <a class="mrn-active-style-guide-page__nav-item" href="#type" data-target="type">Typography</a>
                    </div>
                    <div class="mrn-active-style-guide-page__nav-group">
                        <p class="mrn-active-style-guide-page__nav-label">Elements</p>
                        <a class="mrn-active-style-guide-page__nav-item" href="#buttons" data-target="buttons">Buttons</a>
                        <a class="mrn-active-style-guide-page__nav-item" href="#links" data-target="links">Links</a>
                        <a class="mrn-active-style-guide-page__nav-item" href="#forms" data-target="forms">Forms</a>
                    </div>
                    <div class="mrn-active-style-guide-page__nav-group">
                        <p class="mrn-active-style-guide-page__nav-label">Patterns</p>
                        <a class="mrn-active-style-guide-page__nav-item" href="#surfaces" data-target="surfaces">Light & Dark Surfaces</a>
                        <a class="mrn-active-style-guide-page__nav-item" href="#starter-rules" data-target="starter-rules">Starter Rules</a>
                    </div>
                </nav>
            </aside>

            <div class="mrn-active-style-guide-page__content">
                <header class="mrn-active-style-guide-page__header">
            <p class="mrn-active-style-guide-page__eyebrow">Front-End Theme Reference</p>
                    <h1 class="mrn-active-style-guide-page__title">Style Guide</h1>
                    <p class="mrn-active-style-guide-page__intro">This page is the coded reference for the current site. Designers can hand off direction, developers can build against it, and QA can use it to catch visual drift when multiple people are touching the front end.</p>
                    <div class="mrn-active-style-guide-page__meta">
                        <div class="mrn-active-style-guide-page__meta-item">
                            <strong>Use it for QA</strong>
                            Compare page output against these approved patterns when something feels off.
                        </div>
                        <div class="mrn-active-style-guide-page__meta-item">
                            <strong>Use it for build work</strong>
                            New templates should inherit these behaviors instead of inventing fresh button or link styles.
                        </div>
                        <div class="mrn-active-style-guide-page__meta-item">
                            <strong>Use it for handoff</strong>
                            Designers, leads, and front-end devs can point to one live source of truth.
                        </div>
                    </div>
                </header>

                <div class="mrn-active-style-guide-page__empty" aria-live="polite">No sections match that filter yet.</div>

                <section class="mrn-active-style-guide-page__group" data-guide-group="foundations">
                    <div class="mrn-active-style-guide-page__group-header">
                        <p class="mrn-active-style-guide-page__group-title">Foundations</p>
                        <p class="mrn-active-style-guide-page__group-intro">These are the base ingredients that the rest of the site should inherit from: tokens, hierarchy, and rhythm.</p>
                    </div>

                    <section id="colors" class="mrn-active-style-guide-page__section" data-guide-label="colors palette tokens site colors variables foundations">
                        <h2>Colors</h2>
                        <p class="mrn-active-style-guide-page__section-copy">Treat these like the approved palette for the site. If a page color feels wrong, start by checking whether it matches one of these tokens.</p>
                        <?php mrn_active_style_guide_render_color_grid(); ?>
                    </section>

                    <section id="type" class="mrn-active-style-guide-page__section" data-guide-label="typography headings body copy text hierarchy foundations">
                        <h2>Typography</h2>
                        <p class="mrn-active-style-guide-page__section-copy">These samples should reflect the live heading and body styles from the theme. They set the expected hierarchy for content editors and front-end builds.</p>
                        <?php mrn_active_style_guide_render_typography_samples(); ?>
                    </section>
                </section>

                <section class="mrn-active-style-guide-page__group" data-guide-group="elements">
                    <div class="mrn-active-style-guide-page__group-header">
                        <p class="mrn-active-style-guide-page__group-title">Elements</p>
                        <p class="mrn-active-style-guide-page__group-intro">These are the repeatable building pieces that should stay visually consistent across templates, modules, and reusable blocks.</p>
                    </div>

                    <section id="buttons" class="mrn-active-style-guide-page__section" data-guide-label="buttons primary secondary hover focus states actions elements">
                        <h2>Buttons</h2>
                        <p class="mrn-active-style-guide-page__section-copy">Hover and focus these buttons directly. This is where the team should agree on how primary and secondary actions behave before those treatments spread across templates.</p>
                        <?php mrn_active_style_guide_render_button_samples(); ?>
                    </section>

                    <section id="links" class="mrn-active-style-guide-page__section" data-guide-label="links inline navigation hover visited underline behavior elements">
                        <h2>Links</h2>
                        <p class="mrn-active-style-guide-page__section-copy">Links usually drift fastest when multiple devs are involved. Use these examples to define what an inline text link should look like and how it should react on hover.</p>
                        <div class="mrn-active-style-guide-inline-links">
                            <p>Body copy can include an <a href="#">inline text link</a> that stays readable without looking like a button.</p>
                            <p>Navigation-style items can be more restrained, like <a href="#">section navigation</a> or <a href="#">utility actions</a>.</p>
                        </div>
                    </section>

                    <section id="forms" class="mrn-active-style-guide-page__section" data-guide-label="forms inputs textarea focus fields elements">
                        <h2>Forms</h2>
                        <p class="mrn-active-style-guide-page__section-copy">These starter fields help define input spacing, borders, and focus treatment before real forms get built. Later this can expand into error, success, and help-text states.</p>
                        <?php mrn_active_style_guide_render_form_samples(); ?>
                    </section>
                </section>

                <section class="mrn-active-style-guide-page__group" data-guide-group="patterns">
                    <div class="mrn-active-style-guide-page__group-header">
                        <p class="mrn-active-style-guide-page__group-title">Patterns</p>
                        <p class="mrn-active-style-guide-page__group-intro">Patterns help the team agree on context. The same link or button may need a different approved treatment depending on the surface it lives on.</p>
                    </div>

                    <section id="surfaces" class="mrn-active-style-guide-page__section" data-guide-label="surfaces dark light sections patterns contextual links buttons">
                        <h2>Light and Dark Surfaces</h2>
                        <p class="mrn-active-style-guide-page__section-copy">This is the start of context testing. It shows how links and buttons may need to behave differently on bright and dark section backgrounds.</p>
                        <div class="mrn-active-style-guide-mini-grid">
                            <div class="mrn-active-style-guide-surface">
                                <p class="mrn-active-style-guide-label">Light Surface</p>
                                <p class="mrn-active-style-guide-note">This is a plain section treatment with an <a href="#">inline link</a> and a standard CTA below.</p>
                                <p><a href="#" class="mrn-active-style-guide-button">Secondary Action</a></p>
                            </div>
                            <div class="mrn-active-style-guide-surface mrn-active-style-guide-surface--dark">
                                <p class="mrn-active-style-guide-label">Dark Surface</p>
                                <p class="mrn-active-style-guide-note">Dark sections often need their own approved link and button behavior so contrast and emphasis stay reliable.</p>
                                <p><a href="#" class="mrn-active-style-guide-button is-primary">Primary Action</a></p>
                            </div>
                        </div>
                    </section>

                    <section id="starter-rules" class="mrn-active-style-guide-page__section" data-guide-label="starter rules standards naming css components handoff patterns">
                        <h2>Starter Rules</h2>
                        <p class="mrn-active-style-guide-page__section-copy">This is where the guide starts moving from visual samples to implementation rules. As the site matures, each component can gain a usage note, approved name, and optional class or component reference.</p>
                        <div class="mrn-active-style-guide-mini-grid">
                            <article class="mrn-active-style-guide-card">
                                <h3>Primary Action</h3>
                                <p class="mrn-active-style-guide-note">Use for the main CTA in a section. Avoid multiple competing primary actions in the same visual group.</p>
                            </article>
                            <article class="mrn-active-style-guide-card">
                                <h3>Body Link</h3>
                                <p class="mrn-active-style-guide-note">Use inside running text. Keep it clearly interactive without making it feel like a button.</p>
                            </article>
                            <article class="mrn-active-style-guide-card">
                                <h3>Section Surface</h3>
                                <p class="mrn-active-style-guide-note">Document whether a pattern is intended for light, dark, image, or tinted backgrounds so context does not get guessed later.</p>
                            </article>
                        </div>
                    </section>
                </section>
            </div>
        </div>
    </div>
</main>
<script id="mrn-active-style-guide-page-script">
    (function () {
        var searchInput = document.querySelector('.mrn-active-style-guide-page__search');
        var sections = Array.prototype.slice.call(document.querySelectorAll('.mrn-active-style-guide-page__section'));
        var groups = Array.prototype.slice.call(document.querySelectorAll('.mrn-active-style-guide-page__group'));
        var navItems = Array.prototype.slice.call(document.querySelectorAll('.mrn-active-style-guide-page__nav-item'));
        var emptyState = document.querySelector('.mrn-active-style-guide-page__empty');

        if (!searchInput || !sections.length) {
            return;
        }

        function applyFilter() {
            var query = searchInput.value.trim().toLowerCase();
            var visibleCount = 0;

            sections.forEach(function (section) {
                var haystack = ((section.getAttribute('data-guide-label') || '') + ' ' + (section.textContent || '')).toLowerCase();
                var matches = query === '' || haystack.indexOf(query) !== -1;
                section.classList.toggle('is-hidden', !matches);
                if (matches) {
                    visibleCount += 1;
                }
            });

            groups.forEach(function (group) {
                var hasVisibleSection = group.querySelector('.mrn-active-style-guide-page__section:not(.is-hidden)');
                group.classList.toggle('is-hidden', !hasVisibleSection);
            });

            navItems.forEach(function (item) {
                var targetId = item.getAttribute('data-target');
                var target = targetId ? document.getElementById(targetId) : null;
                var shouldHide = !target || target.classList.contains('is-hidden');
                item.classList.toggle('is-hidden', shouldHide);
            });

            if (emptyState) {
                emptyState.classList.toggle('is-visible', visibleCount === 0);
            }
        }

        searchInput.addEventListener('input', applyFilter);
        applyFilter();
    }());
</script>
<?php
get_footer();
