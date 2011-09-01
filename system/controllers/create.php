<?php

if ( isset( $_POST['create'] ) ) {
	$req = Request::factory( URL_ROOT . '/api/create/' );
	$req->post = array_intersect_key( $_POST, array_flip( $vars['columns'] ) );
	$req->method = 'post';
	$resp = $req->execute();
	if ( $resp->status == 200 ) {
		$status_message->setStatus( 'success' );
		$status_message->setMessage( sprintf( '<p>%s</p>', $resp->data->message ) );
	}
	else {
		header("HTTP/1.1 500 Internal Server Error");
		$status_message->setStatuses( array( 'error', 'remain' ) );
		$status_message->setMessage( sprintf( '<p>%s</p>', $resp->data->message ) );
	}
}

require( DIR_VIEWS . '/pages/edit.php' );

