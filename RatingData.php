<?php

class RatingData {
	/**
	 * @return-taint escaped Kinda not true but we do need ->plain() here, using ->escaped() won't do it :-(
	 */
	public static function getJSON() {
		$msg = wfMessage( 'are-ratings' )->inContentLanguage();
		$json = $msg->plain();
		if ( $msg->isDisabled() ) {
			trigger_error( 'ARE Error: empty JSON' );
		}
		return json_decode( $json, true );
	}

	public static function getAllRatings() {
		$JSON = self::getJSON();

		$returners = [];

		foreach ( $JSON as $data ) {
			$returners[] = new Rating( $data['codename'] );
		}

		return $returners;
	}

	public static function getDefaultRating() {
		$JSON = self::getJSON();

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Let it be suspicious
		return new Rating( $JSON[0]['codename'] );
	}
}
