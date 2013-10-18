bayesEmail
==========

A script for determining the spamminess of a list of addresses.

Usage:  php ./bayesEmail.php [OPTION...]
	php ./bayesEmail.php -d -g=INT -b=INT --tp=INT --fp=INT
	php ./bayesEmail.php -e=STRING 
	php ./bayesEmail.php -e=STRING -g=INT -b=INT

  -h, --help		Display this help text

  -e   =STRING		Email address (or hash) to search for among the lists
			This option is exclusive of -d (demo calculation mode).

  -g   =INTEGER		Number of "good" (non-spammy) lists to use
			If not provided, will be counted from contents of $GOODDIR .

  -b   =INTEGER		Number of "bad" (spammy) lists to use
			If not provided, will be counted from contents of $BADDIR . 

  -t   =FLOAT		Notification threshold. If posterior probability is over threshold, 
			will print a list of the false positive matches.  Over multiple searches, 
			can be a good indicator of lists that may warrant recategorizing.  

  -d			Demo calculation mode. Will skip searching and do calculations based on 
			the provided True Positive (--tp) and False Positive (--fp) quantities.
			This option is exclusive of -e (email searching).

  --tp =INTEGER		Number of True Positives to be used in demo calculation mode

  --fp =INTEGER		Number of False Positives to be used in demo calculation mode

