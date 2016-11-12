mediawiki-extensions-ArticleRatings/ArticleRatings.php
c9ef78e  on Jun 24, 2014
 georgebarnick Rename Hooks.php to ArticleRatingsHooks.php
2 contributors @UltrasonicNXT @mary-kate
RawBlameHistory    
75 lines (60 sloc)  2.37 KB
<?php
/**
 * ArticleRating extension -- a complex interface for rating pages
 *
 * @file
 * @ingroup Extensions
 * @author Adam Carter (UltrasonicNXT)
 * @link https://www.mediawiki.org/wiki/Extension:ArticleRatings Documentation
 */
# Not an entry point
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}
# Load classes
$wgAutoloadClasses['RatingData'] = __DIR__ . '/RatingDataClass.php';
$wgAutoloadClasses['Rating'] = __DIR__ . '/RatingDataClass.php';
# Load hooks
$wgAutoloadClasses['AreHooks'] = __DIR__ . '/ArticleRatingsHooks.php';
$wgHooks['BaseTemplateToolbox'][] = 'AreHooks::onBaseTemplateToolbox';
$wgHooks['TitleMove'][] = 'AreHooks::onTitleMove';
$wgHooks['ParserFirstCallInit'][] = 'wfRatingParserInit';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'AreHooks::onLoadExtensionSchemaUpdates';
$wgHooks['ArticleDeleteComplete'][] = 'AreHooks::onArticleDeleteComplete';
include( __DIR__ . '/RatingTag.php' );
function wfRatingParserInit( Parser $parser ) {
	$parser->setHook( 'rating', 'wfRatingRender' );
	return true;
}
# Credits
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'ArticleRating',
	'version' => '2.4.0',
	'author' => 'UltrasonicNXT/Adam Carter',
	'url' => 'https://www.mediawiki.org/wiki/Extension:ArticleRatings',
	'descriptionmsg' => 'ratings-desc',
);
# Group the special pages under the correct headers in Special:SpecialPages
$wgSpecialPageGroups['ChangeRating'] = 'other';
$wgSpecialPageGroups['MassRatings'] = 'other';
# Set up Special:ChangeRating
$wgAutoloadClasses['SpecialChangeRating'] = __DIR__ . '/SpecialChangeRating.php';
$wgSpecialPages['ChangeRating'] = 'SpecialChangeRating';
# Set up Special:MassRatings
$wgAutoloadClasses['SpecialMassRatings'] = __DIR__ . '/SpecialMassRatings.php';
$wgSpecialPages['MassRatings'] = 'SpecialMassRatings';
# i18n
$wgExtensionMessagesFiles['ArticleRatings'] = __DIR__ . '/ArticleRatings.i18n.php';
$wgExtensionMessagesFiles['ArticleRatingsAlias'] = __DIR__ . '/ArticleRatings.alias.php';
# Logs
$wgLogTypes[] = 'ratings';
$wgLogActionsHandlers['ratings/*'] = 'LogFormatter';
# New user right
$wgAvailableRights[] = 'change-rating';
# TODO FIXME:
# this definition needs to be moved to Brickimedia's config file(s)
$wgGroupPermissions['reviewer']['change-rating'] = true;
# vars
$wgAREUseInitialRatings = false;
$wgARENamespaces = $wgContentNamespaces;
