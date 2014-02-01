<?php

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['RatingData'] = $dir . 'RatingDataClass.php';

class SpecialMassRatings extends QueryPage {
	function __construct() {
		parent::__construct( 'MassRatings' );
	}

	function getQueryInfo() {
		global $wgRequest;

  		$where = '';
  		$selectedRatings = array();

        $ratings = RatingData::getAllRatings();

		foreach ( $ratings as $data ) {

			if ( $wgRequest->getVal( $data ) == 'true' ) {
				$selectedRatings[] = $data;
			}
  		}

  		if ( $selectedRatings ) {
  			$where = array ( 'ratings_rating' => $selectedRatings );
  		} else {
  			$where = array();
  		}

		return array(
			'tables' => 'ratings',
			'fields' => array(
				'namespace' => 'ratings_namespace',
				'title' => 'ratings_title',
				'value' => 'ratings_rating'
			),
			'conds' => $where
		);
	}

	function getOrderFields() {
		return array( 'ratings_title' );
	}

	function sortDescending() {
		return false;
	}

	function getPageHeader() {
		global $wgRequest;

  		$formbody = '';

  		$formhead = '<fieldset><legend>List pages by rating</legend><form action="" method="get">';

  		$formfoot = '<input type="submit" /></form></fieldset>';

        $ratings = RatingData::getAllRatings();

		foreach ( $ratings as $data ) {
			$rating = new RatingData( $data );

			$label = $rating->getAboutLink();
			$pic = $rating->getImage();

			if ( $wgRequest->getVal( $data ) == 'true' ) {
				$checked = ' checked="checked"';
			} else {
				$checked = '';
			}

			$input = '<input type="checkbox" value="true" name="' . $data . '"' . $checked . ' /> ';
  			$formbody .= $input . $pic . $label . '<br />';
		}

  		return $formhead . $formbody . $formfoot;
	}

	function formatResult( $skin, $page ) {
		$rating = new RatingData( $page->value );

		$pic = $rating->getImage();
		$label = $rating->getAboutLink();

		$title = Title::newFromText( $page->title );
		$link = Linker::link( $title );

		return $pic . $label . ' - ' . $link;
	}
}

?>