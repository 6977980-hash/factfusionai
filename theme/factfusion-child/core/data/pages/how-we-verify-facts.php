<?php
/**
 * Starter text for the "how-we-verify-facts" page. Used once, when the page is created.
 *
 * @package FactFusion_AI_Core
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:paragraph -->
<p>FactFusion AI publishes short facts, and each one links to the source it was checked against. This page explains how a fact gets onto the site, what the labels on a fact page mean, and how AI is used.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Where the facts come from</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>A fact starts with a source, not the other way round. We look for sources that publish the information first-hand and stand behind it, and we sort them into four tiers.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><strong>Tier A:</strong> primary and official sources, such as government and intergovernmental science agencies, peer-reviewed research and official records.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Tier B:</strong> established reference works and institutions, such as major museums and encyclopedias, including Wikipedia.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Tier C:</strong> established news organisations and magazines.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Tier D:</strong> blogs and social media. We avoid these.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>The tier is shown as a badge next to the source on every fact page. We use Tier A or Tier B sources wherever they exist. Many of our newer facts cite Wikipedia, which is Tier B. Where a Tier A source exists, we prefer it and add it when the fact is next checked.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How a fact is checked</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li>The claim is written to say only what the source says. If the source gives a number as approximate, so do we.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>The claim, and every number and name in the explanation, is compared with the text of the source page.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>The editor approves the fact. Nothing is published without that approval, and its date is recorded as the fact's "last verified" date.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>The fact page then shows the source link, the source tier, the last verified date, a confidence score and the name of the editor who verified it.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>AI tools assist with the comparison in step 2 and with drafting. They do not replace step 3: the editor decides whether a fact is published.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">What the confidence score means</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The confidence score, from 0 to 100, is the editor's judgement of how directly the source supports the claim. A fact stated outright by a primary source scores higher than one that rests on an estimate or a rounded figure. It is a judgement, not a calculation.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Keeping facts up to date</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Sources change and knowledge moves on. When a fact is checked again, its last verified date is updated. If a fact can no longer be supported, it is corrected or removed.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Corrections</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Every fact page has a "Report a problem with this fact" form. Reports go to the editor. If a report is right, the fact is corrected and its verification date is updated. If you would rather write to us directly, use the <a href="{{contact_url}}">contact page</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How we use AI</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>FactFusion AI uses AI assistance for content organization, drafting headlines, short answers and explanations, and classification. All facts are verified by human editors before publishing.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>AI never adds a source, a link or an author, and never decides whether a fact is true. Each claim comes from its linked source.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>AI may draft a fact's headline, short answer and explanation from the claim. An editor reads them against the source before publishing, and the fact page says when AI was used.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>The random fact generator does not use AI. It picks from the facts already published.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Editorial policy</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>We state only what the source supports.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>We do not invent sources, authors, dates or quotations.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>We prefer primary sources and name every source we use.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>We correct mistakes openly and record when each fact was last verified.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>The site is free to read, and the fact widget and API are free to use.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>The editor is Ali Ahmad Chaudhry.{{profile_sentence}}</p>
<!-- /wp:paragraph -->
