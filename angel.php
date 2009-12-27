#!/usr/bin/env php
<?
/**
* UWAngel-CLI is a CLI for navigating the University of Waterloo's
* UW-ACE/UW-Angel online system
*
* @version 0.1
* @author Jamie Wong <jamie.lf.wong@gmail.com>
* @requires phpcurl
* @requires stty
* @requires head
* @requires wget
*/

// Includes the class used to access all UWACE resources
include_once("AngelAccess.class.php");

// Includes the terminal codes for displaying color
include_once("cli_colours.php");

// Greet the user
print <<<EOT
Welcome to Angel Access
Login using your UW-ACE Username & Password
As you type your password, it will not be shown on screen,
but it is being entered.
--

EOT;

// Initialize the accesor
$cmd = new AngelAccess();

// Authentication
$cmd->Login();

/*
* The directory stack keeps track of the user's navigation.
* When the user decides to go back to a directory above the current,
* The stack will just pop off the current element and display the
* listing for the next element. This prevents some of the duplicate
* HTTP requests
*/
$dirstack = array();

// Retrieve the user's course listings
print "Retrieving courses....\r";
array_unshift($dirstack, $cmd->GetClasses());
system("clear");
print <<<EOT
You are now logged in to Angel Access
All information is in the format
[command| type] name
To access an item in a menu, simply type the corresponding command and enter toexecute;
For instance, q will quit the program.
--

EOT;

// These are the colours corresponding to each item to be displayed
$colortype = array(
	'Course' => $COLOR_LIGHTGRAY,
	'Folder' => $COLOR_BLUE,
	'File' => $COLOR_PURPLE,
	'Page' => $COLOR_CYAN,
);


while(1) {
	// The current directory to be displayed
	$curdir = $dirstack[0];
	
	// The maximum number of digits requires to display any menu command
	$numdigs = intval(log10(count($curdir))) + 1;

	// The format string to display the menu options
	$format = "%s[%".$numdigs."s|%8s] %s\n";


	// Whitespace used to erase status messages
	print "                                                  \n";
	foreach ($curdir as $key => $val) {
		if (isset($colortype[$val['type']])) {
			$color = $colortype[$val['type']];
		} else {
			$color = $COLOR_RED;
		}

		printf($format,
			$color,		
			$key,
			$val['type'],
			$val['name']
		);
	}

	print "\n";	

	// Don't let the use go back if they're in the topmost directory
	if (count($dirstack) > 1) {
		printf($format,$COLOR_DEFAULT,'b','Command','Go Back');
	}
	printf($format,$COLOR_DEFAULT,'q','Command','Quit');

	// Prompt the user for a menu selection
	do {
		$selection = strtolower(
			$cmd->Prompt("$COLOR_GREEN ?> $COLOR_DEFAULT")
		);
	} while(strlen($selection) == 0);
	system("clear");

	if ($selection == 'q') {
		break;
	} else if ($selection == 'b') {
		if (count($dirstack) > 1) {
			array_shift($dirstack);
		} else {
			print "ERROR - Cannot go back, already at root level.\n";
		}
	// Check to see if they entered only numbers
	} else if (preg_match("/[^0-9]/",$selection) == 0) {
		$selection = intval($selection);
		if ($selection >= count($curdir)) {
			print "ERROR - Selection out of bounds. Try a lower number.\n";
		}
		$curitem = $curdir[$selection];
		if ($curitem['type'] == "Course") {
			print "Retrieving course content...\r";
			array_unshift($dirstack,$cmd->BrowseClass($curitem['id']));
			
		} else if ($curitem['type'] == "Folder") {
			print "Retrieving folder contents...\r";
			array_unshift($dirstack,$cmd->BrowseFolder($curitem['id']));

		} else if ($curitem['type'] == "File") {
			print "Retrieving file location...\r";
			$fileurl = $cmd->GetFileUrl($curitem['id']);
			
			// Prompt for a filename to save their selection as
			$outname = $cmd->Prompt(
				sprintf("Save As [Default: %s] ?>",basename($fileurl))
			);

			if ($outname == "") $outname = basename($fileurl);

			$com = "wget --no-check-certificate -O \"$outname\" \"https://$fileurl\"";
			system($com);

		} else if ($curitem['type'] == "Page") {
			print "Retrieving page...\r";
			$pagetext = $cmd->GetPage($curitem['id']);
			print "                    ";
			print $pagetext;
			print "--\n";
			$cmd->Prompt("Press enter to continue... ",true);
			system("clear");

		} else {
			print "ERROR - No protocol for handling item type: {$curitem['type']}\n";
		}
	} else {
		print "ERROR - Unrecognized command: $selection\n";
	}
}

	
?>
