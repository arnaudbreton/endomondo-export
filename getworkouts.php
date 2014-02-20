<?php

/*  Script downloaded from:
 *
 *  https://www.bytopia.dk/blog/2012/11/30/download-all-workouts-from-endomondo/
 *
 *  This scripts downloads all your workouts from Endomondo, but first you need
 *  to login using one of the following methods:

 *  1. Put your username and password in a file called secret.txt where the first
 *     line is the username and the second line is the password. This does not work
 *     with Facebook logins and also requires OpenSSL support in your PHP installation.

 *  2. Get the cookie used to authenticate you and put the cookie (everything after
 *     "Cookie: " or the entire line, it doesn't matter) in a file called cookie.txt.

 *  You can get the cookie header in several ways: With Wireshark, Using Chrome:
 *  (F12, reload page in Endomondo, Network, Click on one of the resources under headers)
 *  or with some other extension like TamperData for Firefox.
 */

function getCookie() {
  global $cookie;

  $filename = "cookie.txt";
  if (!file_exists($filename)) {
    echo "Cookie file not found. Exiting.\n";
    exit(0);
  }
  $cookie = file_get_contents($filename);
  $cookie = trim(str_ireplace(array("\n", "\r", "Cookie:"), '', $cookie));
  if ($cookie == "") {
    echo "No cookie found in file. Exiting.\n";
    exit(0);
  }
  // $buf = getLocationHeader("/home");
  // if ($buf != "/home") {
  //   echo "Invalid cookie. Exiting.\n";
  //   exit(0);
  // }
}

function getUsernamePasswordAndLogin() {
  $filename = "secret.txt";

  if (!file_exists($filename)) {
    return false;
  }

  $fp = fopen($filename, "r");
  $username = trim(fgets($fp, 256));
  $password = trim(fgets($fp, 256));
  fclose($fp);
  
  if ($username == "" || $password == "") {
    echo "Invalid file format for the username and password file. Exiting.\n";
    exit(0);
  }

  if (!loginAndGetCookie($username, $password)) {
    echo "Login failed. Exiting.\n";
    exit(0);
  }

  return true;
}

function loginAndGetCookie($username, $password) {
  global $cookie;

  if (!in_array("tls", stream_get_transports())) {
    echo "No TLS support. Use cookie based login or install the OpenSSL extension for PHP. Exiting.\n";
    exit(0);
  }

  $buf = getUrl("/access", true);
  if (preg_match_all('/Set-Cookie: ([^;\r\n]+)/s', $buf, $matches)) {
    $cookie = implode("; ", $matches[1]);
  }
  $loginUrl = "/access".getDataFromBuffer($buf, '/<form class=\"signInForm\" name=\"signInForm\" id=\"[0-9a-z]+\" method=\"post\" action=\"([^\"]+)\"/');
  $buf = postUrl($loginUrl, "signInButton=x&rememberMe=on&email=".urlencode($username)."&password=".urlencode($password), true);
  if (preg_match('/Location: http(?:s)?:\/\/www\.endomondo\.com\/home/s', $buf)) {
    if (preg_match_all('/Set-Cookie: ([^;\r\n]+)/s', $buf, $matches)) {
      $authcookie = implode("; ", $matches[1]);
      $authcookie = trim(str_ireplace(array('EndomondoApplication_USER=""; ', 'EndomondoApplication_AUTH=""; '), '', $authcookie));
    }
    $cookie = $cookie."; ".$authcookie;
    return true;
  } else {
    return false;
  }
}

function getLatestWorkout() {
  $filename = "latest-workout.txt";
  if (file_exists($filename)) {
    $fp = fopen($filename, "r");
    if ($fp !== false && !feof($fp))
      $workout = trim(fgets($fp, 256));
    fclose($fp);
    return $workout;
  } else {
    return '';
  }
}

function updateLatestWorkout($workout) {
  $filename = "latest-workout.txt";
  $fp = fopen($filename, "w");
    fputs($fp, $workout."\n");
  fclose($fp);
}

function getUrl($url, $ssl = false) {
  global $cookie;

  $url = cleanUrl($url);
  $port = $ssl ? 443 : 80;
  $proto = $ssl ? "tls" : "tcp";
  $buf = '';
  $socket = stream_socket_client($proto."://www.endomondo.com:".$port, $errno, $errstr, 10);
  if ($socket) {
    stream_set_timeout($socket, 10);
    fwrite($socket, "GET ".$url." HTTP/1.0\r\n");
    fwrite($socket, "Host: www.endomondo.com\r\n");
    fwrite($socket, "User-Agent: Mozilla/5.0\r\n");
    fwrite($socket, "Connection: close\r\n");
    fwrite($socket, "Accept-Language: en-US\r\n");
    fwrite($socket, "Cookie: ".$cookie."\r\n\r\n");
    while (!feof($socket)) {
      $buf = $buf.fread($socket, 65536);
    }
    fclose($socket);
  } 
  return $buf;
}

function postUrl($url, $data, $ssl = false) {
  global $cookie;

  $url = cleanUrl($url);
  $port = $ssl ? 443 : 80;
  $proto = $ssl ? "tls" : "tcp";
  $buf = '';
  $socket = stream_socket_client($proto."://www.endomondo.com:".$port, $errno, $errstr, 10);
  if ($socket) {
    stream_set_timeout($socket, 10);
    fwrite($socket, "POST ".$url." HTTP/1.0\r\n");
    fwrite($socket, "Host: www.endomondo.com\r\n");
    fwrite($socket, "User-Agent: Mozilla/5.0\r\n");
    fwrite($socket, "Content-Length: ".strlen($data)."\r\n");
    fwrite($socket, "Content-Type:application/x-www-form-urlencoded\r\n");
    fwrite($socket, "Accept-Language: en-US\r\n");
    fwrite($socket, "Connection: close\r\n");
    fwrite($socket, "Cookie: ".$cookie."\r\n\r\n");
    fwrite($socket, $data);
    while (!feof($socket)) {
      $buf = $buf.fread($socket, 65536);
    }
    fclose($socket);
  }
  return $buf;
}

function cleanUrl($url) {
  $url = trim(str_ireplace(array('http://', 'https://', 'www.endomondo.com'), '', $url));
  $url = preg_replace("/[\/]+/", "/", $url);
  if (strlen($url) > 0 && $url[0] != '/') {
    $url = '/'.$url;
  }
  return $url;
}

function getBody($buf) {
  return trim(strstr($buf, "\r\n\r\n"));
}

function getLocationHeader($url) {
  $buf = getUrl($url);
  var_dump($buf);
  if (preg_match('/Location: ([^\r\n]+)/s', $buf, $matches)) {
    return cleanUrl($matches[1]);
  } else {
    return '';
  }
}

function getDataFromBuffer($buf, $regex) {
  if (preg_match($regex, $buf, $matches))
    return trim($matches[1]);
}

function downloadWorkout($workout, $type, $directory, $exit_if_file_exists) {
  echo "Attempting to download workout ".$workout."... ";
  $buf = getBody(getUrl("/workouts/".$workout));

  $sport = getDataFromBuffer($buf, '/<div class=\"sport-name\">([^<]+)<\/div>/');
  $datetime = getDataFromBuffer($buf, '/<div class=\"date-time\">([^<]+)<\/div>/');
  $titledefault = getDataFromBuffer($buf, '/<h1 class=\"title editable( default)[^>]+>[^>]+>[^<]+<\/span><\/h1>/');
  $title = ($titledefault == 'default')  ? '' : getDataFromBuffer($buf, '/<h1 class=\"title editable[^>]+>[^>]+>([^<]+)<\/span><\/h1>/');
  $descdefault = getDataFromBuffer($buf, '/<div class="notes editable( default)[^>]+>[^>]+>(?:<p>)?[^<]+(?:<\/p>)?<\/div>/');
  $desc = ($descdefault == 'default') ? '' : getDataFromBuffer($buf, '/<div class="notes editable[^>]+>[^>]+>(?:<p>)?([^<]+)(?:<\/p>)?<\/div>/');
  $downloadstatus = getDataFromBuffer($buf, '/<(?:a|span) class=\"(enabled|disabled) button export/');
  $exportlink = getDataFromBuffer($buf, '/<a class=\"enabled button export[^\?]+([^\']+)\'/');

  if ($downloadstatus == 'disabled') {
    echo "Workout \"".$sport."\" is not exportable.\n";
  } else if ($exportlink != '') {
    $filename = generateFileName($workout, $sport, $datetime, $title, $desc, $type);
    $buf = getBody(getUrl("/".$exportlink));
    $downloadlink = getDataFromBuffer($buf, '/<a href=\"(.+export'.ucfirst($type).'Link[^\"]+)\"/');
  
    if ($downloadlink != '') {
      $buf = getBody(getUrl("/".$downloadlink));
      $pos = stripos($buf, '<?xml version="1.0" encoding="UTF-8"?>');
      $directory = is_dir($directory) ? preg_replace('/([^\/]+)$/', '$1/', $directory) : "";
      // if (true) {
        if ($exit_if_file_exists && file_exists($directory.$filename)) {
          echo "File already exists. Exiting per user request.\n";
          return false;
        }
        $fp = fopen($directory.$filename, 'w');
        fwrite($fp, $buf);
        fclose($fp);
        echo "Done. Output in '".$filename."'\n";
      // } else {
      //   echo "Invalid or missing workout data for workout \"".$sport."\". This is normal for some workouts with no track information.\n";
      // }
    } else {
      echo "Could not find download link.\n";
      return false;
    }
  } else {
    echo "Could not find export link.\n";
    return false;
  }
  return true;
}

$workoutcounter = 0;
function getWorkoutId($url) {
  global $workoutcounter;

  $workoutcounter = $workoutcounter + 1;
  echo "Attempting to get ID of workout ".$workoutcounter."... ";
  $buf = getLocationHeader($url);
  if (preg_match('/\/workouts\/([0-9]+)/', $buf, $matches)) {
    $workoutid = trim($matches[1]);
    echo "Got ".$workoutid."\n";
    return $workoutid;
  } else {
    echo "Could not find workout ID.\n";
    return '';
  }
}

function getWorkoutsFromPage($pageurl, &$workouts, $endworkoutid) {
  $buf = getBody(getUrl($pageurl));

  $urls = array();
  if (preg_match('/<table class=\"workoutCompareListPanel\".*<\/table>/Us', $buf, $tablematches)) {
    if (preg_match_all('/<td id=\"[^\"]+\" onclick=\"var wcall=wicketAjaxGet\(\'(?:\.\.\/)?([^\']+)\'/', $tablematches[0], $matches)) {
      foreach($matches[1] as $url) {
        if (preg_match('/results:([0-9]+):/', $url, $urlmatches)) {
          $urls[$urlmatches[1]] = $url;
        }
      }
    }
  } else {
    echo "Could not get workout list.\n";
  }

  foreach($urls as $url) {
    $workoutid = getWorkoutId($url);
    if ($workoutid != '') {
      if ($workoutid != $endworkoutid) {
        // $workouts[] = $workoutid;
        downloadWorkout($workoutid, "gpx", "", false);
        downloadWorkout($workoutid, "tcx", "", false);
      } else {
        echo "Workout ID ".$workoutid." has been downloaded before. We're done.\n";
        return '';
      }
    } else {
      echo "Got an empty Workout ID. This should not happen so we're bailing out. Exiting.\n";
      exit(0);
    }
  }

  if (preg_match('/<a class=\"increment next\"[^\?]+([^\"]+)/', $buf, $matches)) {
    return cleanUrl($matches[1]);
  } else {
    return '';
  }
}

function getAllWorkoutIds() {
  $endworkoutid = getLatestWorkout();
  $workouts = array();
  $url = "/workouts/list";
  while ($url != '') {
    $url = getWorkoutsFromPage($url, $workouts, $endworkoutid);
    if ($url != '') {
      $url = getLocationHeader($url);
    }
  }
  return $workouts;
}

/*  Generate a filename for saving the workout to. Modify this one to suit your needs.
 *
 *  $workout:  The numerical ID of the workout.
 *  $sport:    The sport (i.e. Running).
 *  $datetime: The date and time of the workout (unformatted).
 *  $title:    The workout title if you provided any in Endomondo.
 *  $desc:     The workout description if you provided any in Endomondo.
 *  $type:     The file type. Currently only works with gpx.
 */

function generateFileName($workout, $sport, $datetime, $title, $desc, $type) {
  $filename = $workout.'.'.$type;
  return $filename;
}

/*  This function downloads all workouts. It takes four aguments:
 *
 *  $workouts:              The array containing all the workout IDs to download.
 *  $type:                  The output file type. Currently only works with 'gpx'.
 *  $directory:             The output directory ("" means the current directory).
 *  $exit_if_file_exists:   Stop the download if the file already exists.
 */

function downloadAllWorkouts($workouts, $type = 'gpx', $directory = "", $exit_if_file_exists = false) {
  if ($type != 'gpx' && $type != "tcx") {
    echo "The type must be 'gpx'. Exiting.\n";
    return;
  }
  if ($directory != "" && !is_dir($directory)) {
    echo "Output directory does not exist. Exiting.\n";
    return;
  }
  end($workouts);
  while (key($workouts) !== null) {
    $workout = current($workouts);
    if (downloadWorkout($workout, $type, $directory, $exit_if_file_exists)) {
      //updateLatestWorkout($workout);
    } else {
      break;
    }
    prev($workouts);
  }
}

if (!getUsernamePasswordAndLogin()) {
  getCookie();
  echo "Login method: Cookie\n\n";
} else {
  echo "Login method: Username and password\n\n";
}
$workouts = getAllWorkoutIds();
// downloadAllWorkouts($workouts, "gpx", "", false);
// downloadAllWorkouts($workouts, "tcx", "", false);

?>
