<?php
/**
 * Starter text for the "random-animal-fact-generator" page. Used once, when the page is created as a draft.
 * Built from content/pages/random-animal-fact-generator.txt by dev/build-content.mjs.
 *
 * @package FactFusion_AI_Core
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:html -->
[factfusion_generator category="animal"]
<!-- /wp:html -->

<!-- wp:paragraph -->
<p>This animal fact generator shows one animal fact at a time, chosen at random from facts that have been checked against a named source. Press <strong>New fact</strong> for another. Every fact names its source and links to it.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The animal facts here come from organizations that study or look after the animals they describe. So far that means NOAA Fisheries for whales, turtles, seals and rays, the U.S. National Park Service for bison and pronghorn, the Smithsonian's National Zoo for the cheetah, and the Natural History Museum in London for the octopus. It is why you will see measurements with units, and careful words such as "up to", "about" and "may". We keep those words because the source uses them. An animal fact without them is usually a tidier story than the animal itself.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How to use it</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li>Read the fact in the box at the top of the page. The line under it names the source and gives its tier, from A for a primary or official source down to D.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Press <strong>New fact</strong>, or the Space bar, for another animal fact. The generator avoids showing you the same fact twice in a row.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Press <strong>Read the explanation</strong> to open the fact's own page. It has a short answer, about a hundred words of background, three related facts and the full source details.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Use <strong>Copy</strong>, <strong>Share</strong> or <strong>Save</strong> to keep a fact or pass it on. Saved facts stay in your own browser.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>If you would rather read than press a button, the <a href="{{category:animal}}">Animal category page</a> lists every animal fact we have published, newest first. For facts on every subject, use the main <a href="{{page:random-fact-generator}}">random fact generator</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>A tip for teachers and quiz writers: a fact's title usually turns into a question with very little work. "A Narwhal's Tusk Is Actually a Tooth" becomes "What is a narwhal's tusk made of?", and the fact's page gives you the answer and the source to show for it.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">A real example</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This is a fact as the generator shows it:</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
[factfusion_fact slug="a-narwhals-tusk-is-actually-a-tooth"]
<!-- /wp:html -->

<!-- wp:paragraph -->
<p>A narwhal's tusk looks like a horn, and the animal is often compared to a unicorn because of it. NOAA Fisheries, the source for this fact, describes it as a tooth that grows forward through the jaw of the male. Notice what the claim does not say. It does not say every narwhal has a tusk, because the source records females with tusks and males without as exceptions.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Three more animal facts, each with a different kind of record:</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
[factfusion_fact slug="the-bison-is-the-largest-land-mammal-in-north-america"]
<!-- /wp:html -->

<!-- wp:html -->
[factfusion_fact slug="pronghorn-can-sprint-at-45-to-50-miles-per-hour"]
<!-- /wp:html -->

<!-- wp:html -->
[factfusion_fact slug="the-blue-whale-is-the-largest-animal-ever-to-live"]
<!-- /wp:html -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How these facts are verified</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>An editor reads the source page, writes the claim so that it says no more than the source does, and records the source and the date. Sizes, speeds and ages are copied with the source's own units and qualifiers. If two sources disagree, the fact is held back or written around the part that is not in doubt. Our page <a href="{{verify_url}}">How we verify facts</a> explains the tiers and the process.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>FactFusion AI uses AI assistance for content organization, drafting headlines, short answers and explanations, and classification. All facts are verified by human editors before publishing.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Add animal facts to your site</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This code shows a random verified animal fact, with its source, on any web page. It is free to use. Please keep the link.</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
[factfusion_embed_code category="animal"]
<!-- /wp:html -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Questions and answers</h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"ff-faq","layout":{"type":"default"}} -->
<div class="wp-block-group ff-faq"><!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Which animals are covered?</summary><!-- wp:paragraph -->
<p>Whales, dolphins, seals, sea turtles, rays and a few land mammals so far, along with the octopus. The list grows as new facts are checked, so there is no fixed number.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Why are so many of the animals from the sea?</summary><!-- wp:paragraph -->
<p>Many of our first animal facts were checked against NOAA Fisheries, which covers marine species. More land animals, birds and insects will be added as they are verified.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Are the sizes and speeds exact?</summary><!-- wp:paragraph -->
<p>They are the figures the source gives, with the source's own words such as "up to" or "about". Individual animals vary, so treat them as the recorded range, not a promise.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>What if I spot a mistake?</summary><!-- wp:paragraph -->
<p>Open the fact's page and use the "Report a problem with this fact" form. The editor checks the fact against its source again and corrects it if needed.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details --></div>
<!-- /wp:group -->
