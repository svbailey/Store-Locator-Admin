<?php require( DIR_VIEWS . '/header.php' ) ?>
<?php require( DIR_VIEWS . '/widgets/navigation.php' ) ?>

<div id="store_listing_header">
<h2>Store listing</h2>
<?php if( $vars->total_store_count ): ?>
<?php require( DIR_VIEWS . '/widgets/result_numbers.php' ) ?>
<?php require( DIR_VIEWS . '/widgets/pagination.php' ) ?>
</div>
<?php require( DIR_VIEWS . '/widgets/page_status_message.php' ) ?>
<?php require( DIR_VIEWS . '/widgets/store_listing.php' ) ?>
<?php else: ?>
</div>
<p class="no_result">No Stores</p>
<?php endif; ?>

<?php require( DIR_VIEWS . '/footer.php' ) ?>