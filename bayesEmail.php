#!/usr/bin/php
<?php

/**
 * Define these
 */

$CORPUSDIR = "./corpus";
$GOODDIR = $CORPUSDIR . "/goodLists";
$BADDIR = $CORPUSDIR . "/badLists";
$DEFAULTTHRESHOLD = 0.5;

/**
 * Initial handling of CLI options
 */

$options = getopt("hdve::g::b::t::", array("help", "tp::", "fp::"));

if(isset($options['h']) || isset($options['help'])) {
	printHelp();
}

$email = isset($options['e']) ? $options['e'] : null;
$goodListCount = isset($options['g']) ? $options['g'] : null;
$badListCount = isset($options['b']) ? $options['b'] : null;
$threshold = isset($options['t']) ? $options['t'] : $DEFAULTTHRESHOLD;
$demoCalc = isset($options['d']);
$verbose = isset($options['v']);

/**
 * Main procedural body
 */

if($demoCalc) {
	$demoTruePositive = isset($options['tp']) ? $options['tp'] : null;
	$demoFalsePositive = isset($options['fp']) ? $options['fp']: null;
	if(is_null($demoTruePositive) || is_null($demoFalsePositive)) {
		notifyError("In demo mode, you must provide values for the number of True Positives and False Positives");
		printHelp();
	} 
	if(is_null($goodListCount) || is_null($badListCount)) {
		notifyError("In demo mode, you must provide values for the number of good lists and bad lists");
		printHelp();
	} 

	if($demoTruePositive > $badListCount) {
		notifyError("Your True Positive value cannot exceed the number of bad (spammy) lists");
		printHelp();
	}

	if($demoFalsePositive > $goodListCount) {
		notifyError("Your False Positive value cannot exceed the number of good (non-spammy) lists");
		printHelp();
	}

	if($verbose) { notify("Demo Calculation Mode"); }
}

// default initialization of this array
$listPopulation = array('bad' => $badListCount, 'good' => $goodListCount);

if($demoCalc) {
	$emailInLists = array('bad' => $demoTruePositive, 'good' => $demoFalsePositive);
        $score = bayesMe($listPopulation, $emailInLists, "demo@example.com", $verbose);
	exit;			
}


if(!empty($email)) {
        if($verbose) { notify("Searching for email address {$email} in corpus"); }
	
	$goodLists = glob($GOODDIR . '/*');        
	$badLists = glob($BADDIR . '/*');        
	
	if(!empty($goodLists)) {
		$goodListsWithEmail = findEmailAddress($email, $goodLists);
	} else {
		notifyError("No good (non-spammy) lists found");
		die;
	}
	if(!empty($badLists)) {
		$badListsWithEmail = findEmailAddress($email, $badLists);
	} else {
		notifyError("WARN: No bad (spammy) lists found");
		die;
	}
	
	if(empty($goodListCount) || empty($badListCount)) {
        	if($verbose) { notify("Counting lists"); }
	 
		$goodListCount = count($goodLists);
        	$badListCount = count($badLists);
		$listPopulation = array('bad' => $badListCount, 'good' => $goodListCount);
	} else {
        	if($verbose) { notify("Using provided values for list counts"); }
	}


	if(count($goodListsWithEmail) > 0 || count($badListsWithEmail) > 0) {
		$emailInLists = array('bad' => count($badListsWithEmail), 'good' => count($goodListsWithEmail));
                $score = bayesMe($listPopulation, $emailInLists, $email, $verbose);
                if($score > $threshold && !empty($goodListsWithEmail)) {
                        if($verbose) {
				notify("INFO: {$email} in GOOD lists \n" . var_export($goodListsWithEmail,true));
			}
                }
        } else {
                notifyError("\nWARN: $email not found");
        }
} else {
	notifyError("Not in demo calculation mode. You must provide an email address.");
	printHelp();
}



/**
 * Helper functions
 */

function bayesMe($priors, $conditionalData, $email, $verbose = false) {
        $badLists = $priors['bad'];
        $goodLists = $priors['good'];
        $knownLists = $badLists + $goodLists;

        $goodProportion = $goodLists/$knownLists;
        $badProportion = $badLists/$knownLists;

        $truePositive = $conditionalData['bad'];
        $falsePositive = $conditionalData['good'];
        $allPositive = $truePositive + $falsePositive;

        $truePosRate = $truePositive / $badLists;
        $falsePosRate = $falsePositive / $goodLists;
        if($falsePosRate > 0) {
                $likelihoodRatio = $truePosRate / $falsePosRate;
        } else {
		// this can be any large positive integer
                $likelihoodRatio = 100;
        }

        // Can also be represented as Prior Odds * Likelihood Ratio Odds
	$posterior = ($truePosRate * $badProportion) / ($truePosRate * $badProportion + $falsePosRate * $goodProportion);
        if($verbose) {
		notify("Lists: known {$knownLists}");
	        notify("Lists: good {$goodLists} ({$goodProportion}%)");
	        notify("Lists: bad {$badLists} ({$badProportion}%)");

	        notify("\nPrior Probability: {$badProportion} (likelihood of a bad list given the incidence in the population)\n");
		notify("Prior Odds:  {$badLists} : {$goodLists}\n");
        	notify("Detection: TruePosRate {$truePosRate} (bad email present on {$truePositive} bad lists)");
	        notify("Detection: FalsePosRate {$falsePosRate} (bad email present on {$falsePositive} good lists)");
        	notify("Detection: Likelihood Ratio {$likelihoodRatio}");
	        notify("Detection: Likelihood Ratio Odds {$truePosRate} : {$falsePosRate}");
		notify("Posterior Probability: {$posterior} (likelihood you are a bad list given email {$email} on {$allPositive} lists)");
	} else {
		notify("email={$email}|pp={$posterior}\n");
	}
        return $posterior;
}

function findEmailAddress($email, $lists) {
	$matches = array();
	foreach($lists as $list) {
		//echo "searching path $list for $email\n";
		$listData = file($list, FILE_IGNORE_NEW_LINES);
		if( in_array($email, $listData) ) {
			$matches[] = $list;
		}
	}
	return $matches;
}

function notify($message) {
	echo $message . "\n";
}

function notifyError($message) {
	$message = "ERROR: " . $message;
	notify($message);
}

function printHelp() {
	$usage = <<<EOT
Usage:  php ./bayesEmail.php [OPTION...]
	php ./bayesEmail.php -d -g=INT -b=INT --tp=INT --fp=INT
	php ./bayesEmail.php -e=STRING 
	php ./bayesEmail.php -e=STRING -g=INT -b=INT

  -h, --help		Display this help text

  -e   =STRING		Email address (or hash) to search for among the lists
			This option is exclusive of -d (demo calculation mode).

  -g   =INTEGER		Number of "good" (non-spammy) lists to use
			If not provided, will be counted from contents of \$GOODDIR .

  -b   =INTEGER		Number of "bad" (spammy) lists to use
			If not provided, will be counted from contents of \$BADDIR . 

  -t   =FLOAT		Notification threshold. If posterior probability is over threshold, 
			will print a list of the false positive matches.  Over multiple searches, 
			can be a good indicator of lists that may warrant recategorizing.  

  -d			Demo calculation mode. Will skip searching and do calculations based on 
			the provided True Positive (--tp) and False Positive (--fp) quantities.
			This option is exclusive of -e (email searching).

  --tp =INTEGER		Number of True Positives to be used in demo calculation mode

  --fp =INTEGER		Number of False Positives to be used in demo calculation mode

EOT;

	echo $usage;
	die;
}

?>
