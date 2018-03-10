<?php
/**
 * Produce a total score. Call with parameter 'static' for
 * output suitable for static HTML pages.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = "Scoreboard";
$isstatic = @$_SERVER['argv'][1] == 'static' || isset($_REQUEST['static']);

// set auto refresh
$refresh = array(
	"after" => "30",
	"url" => "./",
);
if ( $isstatic ) {
	$refresh['url'] .= '?static=1';
}

// This reads and sets a cookie, so must be called before headers are sent.
$filter = initScorefilter();

$menu = !$isstatic;
require(LIBWWWDIR . '/header.php');

if ( $isstatic ) {
	echo '<img style="width:98%; height: auto; display: block; margin: -3rem auto 0 auto;" ' .
		'src="../images/banner.png" />';
	// FIXME: Requested by John, remove before finals :-)
	echo '<div class="alert alert-danger" role="alert">' .
		'This is just a test contest with random submissions, no real data.' .
		'</div>';
	if ( isset($_REQUEST['contest']) ) {
		foreach ( $cdatas as $c ) {
			if ( $c['externalid'] == $_REQUEST['contest'] ) {
				$cdata = $c;
				break;
			}
		}
	}
}

// call the general putScoreBoard function from scoreboard.php
putScoreBoard($cdata, null, $isstatic, $filter);

echo "<script type=\"text/javascript\">initFavouriteTeams();</script>";

require(LIBWWWDIR . '/footer.php');
