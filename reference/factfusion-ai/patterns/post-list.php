<?php
/**
 * Title: Post list
 * Slug: factfusion-ai/post-list
 * Description: Query loop shared by the index, archive and search templates.
 * Inserter: no
 *
 * @package FactFusion_AI
 */

?>
<!-- wp:query {"queryId":1,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true}} -->
<div class="wp-block-query">
	<!-- wp:post-template -->
		<!-- wp:group {"className":"ff-card","layout":{"type":"default"}} -->
		<div class="wp-block-group ff-card">
			<!-- wp:post-title {"isLink":true,"fontSize":"large"} /-->

			<!-- wp:post-date /-->

			<!-- wp:post-excerpt /-->
		</div>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
		<!-- wp:query-pagination-previous /-->

		<!-- wp:query-pagination-numbers /-->

		<!-- wp:query-pagination-next /-->
	<!-- /wp:query-pagination -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'Nothing was found here.', 'factfusion-ai' ); ?></p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
