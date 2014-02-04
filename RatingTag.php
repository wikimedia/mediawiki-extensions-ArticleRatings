<?php

function wfRatingRender( $input, array $args, Parser $parser, PPFrame $frame ) {
	global $wgUser, $wgArticlePath;

	$out = "<div class='mw-rating-tag'>";
	if ( isset( $args['page'] ) && $args['page'] ) {

    	$title = Title::newFromText( $args['page'] );

    	if ( $title && $title->exists() ) {
			$out .= '<span class="mw-rating-rating-for">Rating for "' . $title->getFullText() . '"</span>: ';
    	} else {
			return 'The page "' . $args['page'] . '" does not exist.';
    	}
	} else {
		$title = $parser->getTitle();
	}

    if ( isset( $args['initial-rating'] ) ) {
    	$initrating = $args['initial-rating'];
    }

    $dbr = wfGetDB( DB_SLAVE );

    $res = $dbr->select(
    	'ratings',
    	array('ratings_rating', 'ratings_title'),
    	array(
    		'ratings_title' => $title->getDBkey(),
    		'ratings_namespace' => $title->getNamespace(),
    	)
	);

	$row = $res->fetchRow();

	if( !$row ) { //create rating
		$ratings = RatingData::getAllRatings();

		if( isset( $args['initial-rating'] ) && in_array( $initrating, $ratings ) ) {
			$userating = $initrating;
		} else {
			$userating = 'un';
		}

		$dbw = wfGetDB( DB_MASTER );

        $dbw->insert(
        	'ratings',
        	array( 'ratings_rating' => $userating,
        		'ratings_title' => $title->getDBkey(),
        		'ratings_namespace' => $title->getNamespace()
        	)
		);
		$field = 'un';
	} else {
		$field = $row['ratings_rating'];
	}

	$rating = new RatingData( $field );

  	$out .= $rating->getAboutLink() . $rating->getImage() . "</div>";

	return $out;
}