<?php
/**
 * Starter text for the "random-space-fact-generator" page. Used once, when the page is created as a draft.
 * Built from content/pages/random-space-fact-generator.txt by dev/build-content.mjs.
 *
 * @package FactFusion_AI_Core
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:html -->
[factfusion_generator category="space"]
<!-- /wp:html -->

<!-- wp:paragraph -->
<p>This space fact generator shows one fact about the planets, the Moon, the Sun or the solar system at a time, chosen at random. Press <strong>New fact</strong> for another. At the time of writing, every space fact here was checked against a page published by NASA, and each one links to that page.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Space is a subject where big numbers travel fast and lose their details on the way. A moon count is repeated for years after it has changed. A temperature loses its unit. Here each fact keeps what the source says: the figure, the unit, and the date when the source ties its figure to one. You can open the NASA page and compare the wording yourself.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How to use it</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li>Read the fact in the box at the top of the page. The line under it names the source and its tier. NASA is a Tier A source, the highest of our four tiers.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Press <strong>New fact</strong>, or the Space bar on a keyboard, for another space fact.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Press <strong>Read the explanation</strong> to open the fact's own page, with a short answer, about a hundred words of background and three related space facts.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Use <strong>Copy</strong> to copy the fact with its source, <strong>Share</strong> to send it on, or <strong>Save</strong> to keep it in your own browser.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>To see everything in one place, the <a href="{{category:space}}">Space category page</a> lists every space fact we have published. Our article <a href="{{page:best-facts-about-space}}">Verified Space Facts With NASA Sources</a> groups them by subject and explains how they were checked. For facts on other subjects, use the main <a href="{{page:random-fact-generator}}">random fact generator</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>If you are preparing a quiz or a lesson, a fact's title usually works as the answer and the short answer on its page as the explanation. Both come with a source you can show.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">A real example</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Here is one fact as the generator shows it:</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
[factfusion_fact slug="venus-is-the-hottest-planet-in-the-solar-system"]
<!-- /wp:html -->

<!-- wp:paragraph -->
<p>Most people would guess Mercury, because it is closest to the Sun. NASA's page on Venus says otherwise and gives the reason: a thick atmosphere that traps heat. The claim carries the temperature in both Fahrenheit and Celsius because the source gives both.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Three more, to show the kind of detail a source provides:</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
[factfusion_fact slug="jupiter-has-the-shortest-day-of-any-planet"]
<!-- /wp:html -->

<!-- wp:html -->
[factfusion_fact slug="saturns-rings-are-vast-but-only-about-30-feet-thick"]
<!-- /wp:html -->

<!-- wp:html -->
[factfusion_fact slug="all-the-asteroids-together-weigh-less-than-the-moon"]
<!-- /wp:html -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How these facts are verified</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>An editor opens the NASA page, finds the sentence that supports the claim, and writes the claim so that it says no more than that sentence does. The source, its tier and the date are recorded and shown on the fact's page, with a confidence score. Where a figure on the source page is tied to a date, the claim keeps the date. The process is described in full on <a href="{{verify_url}}">How we verify facts</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>FactFusion AI uses AI assistance for content organization, explanation drafting, and classification. All facts are verified by human editors before publishing.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Add space facts to your site</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This code shows a random verified space fact, with its NASA source, on any web page. It is free to use. Please keep the link.</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
[factfusion_embed_code category="space"]
<!-- /wp:html -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Questions and answers</h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"ff-faq","layout":{"type":"default"}} -->
<div class="wp-block-group ff-faq"><!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Why are the sources all NASA?</summary><!-- wp:paragraph -->
<p>NASA runs many of the missions that measured these things and publishes the figures itself, which makes it a primary source. We rank it Tier A. Other space agencies and peer-reviewed studies qualify in the same way.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Do space facts go out of date?</summary><!-- wp:paragraph -->
<p>Some do. The number of known moons changes as discoveries are confirmed. That is why each fact page shows the date it was last verified, and why a claim keeps any date the source attaches to its figure.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Why do some facts use miles and Fahrenheit?</summary><!-- wp:paragraph -->
<p>We keep the units the source uses, and give metric figures wherever the source provides them. Converting a number ourselves would add a step the source did not check.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Can I use these facts in a classroom?</summary><!-- wp:paragraph -->
<p>Yes. You may quote individual facts with a link back to the fact page, and the embed code above is free to use.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details --></div>
<!-- /wp:group -->
