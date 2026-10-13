<?php
/**
 * Starter text for the "random-fact-generator" page. Used once, when the page is created as a draft.
 * Built from content/pages/random-fact-generator.txt by dev/build-content.mjs.
 *
 * @package FactFusion_AI_Core
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:html -->
[factfusion_generator]
<!-- /wp:html -->

<!-- wp:paragraph -->
<p>A random fact generator shows you one fact at a time, picked by chance. This one picks only from facts that a person has checked against a named source, so every fact comes with a link to the page where it was confirmed. Press <strong>New fact</strong> and another one appears.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>That is the difference from most generators, which hand you a sentence and nothing else. Here each fact has its own page with the source, the date it was last verified, a confidence score and a short explanation. Nothing is written by AI on the spot. The generator can only choose from facts that are already published on this site. That makes the collection smaller than a list copied from around the web, and it grows only as fast as facts can be checked.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How to use it</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li>Read the fact in the box at the top of this page. Under it you will see the source it was checked against, and a tier from A to D that shows what kind of source it is.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Press <strong>New fact</strong> for another one. On a keyboard, the Space bar does the same thing.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Choose a category, such as Space, Animal or History, to stay within one subject. <strong>All</strong> brings back the full mix. There are also separate generators for <a href="{{page:random-animal-fact-generator}}">animal facts</a> and <a href="{{page:random-space-fact-generator}}">space facts</a>.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Press <strong>Read the explanation</strong> to open the fact's own page, with about a hundred words of background and the full source details.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Use <strong>Copy</strong> to copy the fact with its source and link, <strong>Share</strong> to send it to someone, or <strong>Save</strong> to keep it. Saved facts are stored in your own browser, not on our servers, and are listed on the <a href="{{facts_url}}">All Facts</a> page.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>You do not need an account, and there is nothing to pay. To keep the service available to everyone, there is a limit on how many facts one connection can load in an hour. If you reach it, the page tells you, and you can keep reading by browsing the categories instead.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">A real example</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Here is one fact exactly as the generator serves it:</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
[factfusion_fact slug="an-octopus-has-three-hearts"]
<!-- /wp:html -->

<!-- wp:paragraph -->
<p>The sentence is short, but it can be checked. The source link goes to the museum page the claim was compared with, and the fact's own page explains what each of the three hearts does. If a source changes, or a reader shows us that we misread it, the fact is corrected and its verified date changes with it.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Two more examples show the range of sources. The first was checked against a page of the U.S. Environmental Protection Agency, the second against NASA:</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
[factfusion_fact slug="bananas-are-slightly-radioactive"]
<!-- /wp:html -->

<!-- wp:html -->
[factfusion_fact slug="saturns-rings-are-vast-but-only-about-30-feet-thick"]
<!-- /wp:html -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How these facts are verified</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Each fact starts from a source, not from a guess. An editor reads the source page, writes the claim so that it says no more than the source does, and records the source, its tier and the date. Only then is the fact published. Tier A means a primary or official source, such as a science agency, a national archive or a peer-reviewed study. The full method is on our page <a href="{{verify_url}}">How we verify facts</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>FactFusion AI uses AI assistance for content organization, drafting headlines, short answers and explanations, and classification. All facts are verified by human editors before publishing. A fact whose explanation was drafted with AI help says so on its page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Add the generator to your site</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The code below shows a random verified fact, with its source, on any web page. It is free to use, including on commercial sites. Please keep the link, as our <a href="{{terms_url}}">terms</a> ask.</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
[factfusion_embed_code]
<!-- /wp:html -->

<!-- wp:paragraph -->
<p>Developers can also request a random fact as data from our free public API at <code>{{api_url}}</code>. No key is needed.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Questions and answers</h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"ff-faq","layout":{"type":"default"}} -->
<div class="wp-block-group ff-faq"><!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Are the facts really random?</summary><!-- wp:paragraph -->
<p>Yes. Each press of the button picks one published fact by chance, avoiding the one you have just seen. If you choose a category, the pick is random within that category.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Where do the facts come from?</summary><!-- wp:paragraph -->
<p>From named sources such as NASA, NOAA, the U.S. National Park Service, the U.S. National Institutes of Health, museums, archives and reference works such as Wikipedia. The source and its tier are shown under every fact.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Does AI write the facts?</summary><!-- wp:paragraph -->
<p>No. AI is never the source of a fact here. It helps to draft headlines, short answers and explanations and to organize content, and a person checks every fact against its source before it is published.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Can a fact be wrong?</summary><!-- wp:paragraph -->
<p>It can. Sources change and editors make mistakes. Every fact page has a form for reporting a problem. Reports go to the editor, who checks the fact again.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"ff-faq__item"} -->
<details class="wp-block-details ff-faq__item"><summary>Can I use the facts in a quiz, a lesson or a newsletter?</summary><!-- wp:paragraph -->
<p>Yes. You may quote individual facts with a link back to the fact page. The embed code above and the public API are free.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details --></div>
<!-- /wp:group -->
