<?php

require_once 'HTTP/Request2.php';

header("Content-type: image/png");
header("refresh: 1800");

$width = 800;
$height = 600;
$font = './Gabriola.ttf';

if (array_key_exists('zipcode', $_GET))
   $zipcode = $_GET['zipcode'];
else
   ReturnError('Specify a zip code as a parameter in the URL that\'s in the weatherurl file on the Kindle (e.g. http://<your server>/ iweather.php?zipcode=10001& wundergroundapikey=<key>)');

if (array_key_exists('wundergroundapikey', $_GET))
   $apiKey = $_GET['wundergroundapikey'];
else
ReturnError('Get an API key from wunderground.com and specify it as a parameter in the URL that\'s in the weatherurl file on the Kindle  (e.g. http://<your server>/ iweather.php?zipcode=10001& wundergroundapikey=<key>)');

try
{
   $im = imagecreatetruecolor($width, $height);
   $white = imagecolorallocate($im, 255, 255, 255);
   $black = imagecolorallocate($im, 0, 0, 0);
   imagefill($im, 0, 0, $white);

   if (strlen($apiKey) == 0)
   {
      ReturnError('Get an API key from api.wunderground.com and assign it to $apiKey in iweather.php');
   }

   $numberOfDays = 4;
   $numberOfHours = 6;

   $weather = GetWeather($zipcode);
   date_default_timezone_set($weather->current_observation->local_tz_long);
   $moon = $weather->moon_phase->ageOfMoon;

   $todaysConditionsWidth = (int) ($width * 0.722);
   $todaysHeight = (int) ($height * 0.7);
   $tc = TodaysConditions($weather, $moon, $todaysConditionsWidth, $todaysHeight, $biggestFontSize);
   imagecopy($im, $tc, 0, 0, 0, 0, imagesx($tc), imagesy($tc));
   imagedestroy($tc);

   imagesetthickness($im, 1);

   // Draw the hours
   $gray = imagecolorallocate($im, 128, 128, 128);
   $left = $todaysConditionsWidth + 1;
   $right = $width;
   $hourWidth = $right - $left;
   $hourHeight = $todaysHeight / $numberOfHours;
   for ($h = 0; $h < min($numberOfHours, count($weather->hourly_forecast)); ++$h)
   {
      $top = (int) ($todaysHeight / $numberOfHours * $h);
      $hour = HourConditions($weather->hourly_forecast[$h], $hourWidth, $hourHeight, $biggestFontSize);
      imagecopy($im, $hour, $left, $top + 5, 0, 0, imagesx($hour), imagesy($hour));
      imagedestroy($hour);

      if ($h < $numberOfHours - 1)
      {
         imageline($im, $left, $top + $hourHeight, $right, $top + $hourHeight, $gray);
      }
   }

   // Draw the future days
   imageline($im, 0, $todaysHeight, $width, $todaysHeight, $black);
   $bottom = $height;
   $top = $todaysHeight + 1;
   $left = 0;
   $dayHeight = $bottom - $top;
   // Figure out tha font size we should use for the stats
   $finalStatsFontSize = 200;
   $finalIconSize = 200;
   for ($i = 1; $i <= $numberOfDays; ++$i)
   {
      $right = (int) ($width / $numberOfDays * $i);
      $dayWidth = $right - $left;
      $future = FutureConditions($weather->forecast->simpleforecast->forecastday[$i], $dayWidth, $dayHeight, $statsFontSize, $iconSize, true);
      if ($statsFontSize < $finalStatsFontSize)
         $finalStatsFontSize = $statsFontSize;
      if ($iconSize < $finalIconSize)
         $finalIconSize = $iconSize;
      imagedestroy($future);
      $left = $right + 1;
   }
   $left = 0;
   for ($i = 1; $i <= $numberOfDays; ++$i)
   {
      $right = (int) ($width / $numberOfDays * $i);
      $dayWidth = $right - $left;
      $future = FutureConditions($weather->forecast->simpleforecast->forecastday[$i], $dayWidth, $dayHeight, $finalStatsFontSize, $finalIconSize, false);
      imagecopy($im, $future, $left, $top, 0, 0, $dayWidth, $dayHeight);
      imagedestroy($future);

      if ($i < $numberOfDays)
      {
         imageline($im, $right, $todaysHeight, $right, $height, $black);
      }
      $left = $right + 1;
   }

   // Convert image color space to grayscale so the Kindle can draw it
   if (class_exists('Imagick'))
   {
      $file = tempnam('.', 'png');
      imagepng($im, $file);
      imagedestroy($im);

      $im = new Imagick($file);
      $im->transformImageColorspace(imagick::COLORSPACE_GRAY);
      echo $im;
      unlink($file);
   }
   // ImageMagick isn't installed in our debug environment, but there's
   // no Kindle there either so it's OK
   else
   {
	ReturnError('ImageMagick class does not exists');
      //imagepng($im);
      //imagedestroy($im);
   }
}
catch (Exception $e)
{
   $file = basename($e->getFile());
   ReturnError('Exception thrown: ' . $e->getMessage() . ' at line ' . $e->getLine() . ' in file ' . $file);
}

function GetWeather($zipcode)
{
   global $apiKey;
   $ex = null;

   for ($retry = 0; $retry < 3; ++$retry)
   {
      try
      {
         // Get the weather data
         $query = new HTTP_Request2("http://api.wunderground.com/api/$apiKey/forecast10day/alerts/astronomy/hourly/tide/yesterday/conditions/q/$zipcode.json", HTTP_Request2::METHOD_GET,
            array('connect_timeout' => 5, 'timeout' => 10));
         $query->setHeader(array(
            'Host' => 'api.wunderground.com',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:28.0) Gecko/20100101 Firefox/28.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Connection' => 'keep-alive',
         ));
         $response = $query->send();
         $status = $response->getStatus();

         if ($status != 200) throw new Exception("Attempt to load weather returned $status");

         $body = $response->getBody();
         $weather = json_decode($body);
         return $weather;
      }
      catch (Exception $e)
      {
         $ex = $e;
      }
   }
   throw $ex;
}

function TodaysConditions($weather, $moon, $width, $height, &$headerFontSize)
{
   global $font;

   $sideMargins = 20;
   $verticalMargins = 5;

   $im = imagecreatetruecolor($width, $height);
   $white = imagecolorallocate($im, 255, 255, 255);
   $black = imagecolorallocate($im, 0, 0, 0);
   imagefill($im, 0, 0, $white);

   $icon = IconName($weather->current_observation->icon_url);
   $currentTemp = round($weather->current_observation->temp_f);

   // Summarize the conditions by removing anything about winds or temps
   /*$conditions = explode('.', $weather->forecast->txt_forecast->forecastday[0]->fcttext);
   $summary = '';
   foreach ($conditions as $condition)
   {
   $condition = trim($condition);
   if ($summary == '' ||
   (substr($condition, 0, 5) != 'Winds' && substr($condition, 0, 3) != 'Low' &&
   substr($condition, 0, 4) != 'High' && $condition != '' &&
   strpos($condition, '%') === null))
   {
   $summary .= $condition . '. ';
   }
   }*/

   // Write day and date
   $headerFontSize = GetBestFontSize("Wednesday, December 28", $width - $sideMargins, 0);
   $text = $weather->forecast->simpleforecast->forecastday[0]->date->weekday . ', ' .
   $weather->forecast->simpleforecast->forecastday[0]->date->monthname . ' ' .
   $weather->forecast->simpleforecast->forecastday[0]->date->day;
   $box = imagettfbbox($headerFontSize, 0, $font, $text);
   $textHeight = BoxHeight($box);
   $bottom = $textHeight;
   $box = imagettftext($im, $headerFontSize, 0, $sideMargins, $bottom, $black, $font, $text);

   // Write location
   $text = $weather->current_observation->display_location->full;
   $locationFontSize = min(GetBestFontSize($text, $width, 0), $headerFontSize * 0.66);
   $box = imagettfbbox($locationFontSize, 0, $font, $text);
   $bottom += $verticalMargins + BoxHeight($box);
   $box = imagettftext($im, $locationFontSize, 0, $sideMargins, $bottom, $black, $font, $text);

   // Draw the weather icon
   $path = "icons/$icon.png";
   $icon = imagecreatefrompng($path);
   $scaledIcon = ScaleImage($icon, (int) ($width * 0.2));
   imagedestroy($icon);
   $iconWidth = imagesx($scaledIcon);
   $iconHeight = imagesy($scaledIcon);
   imagecopy($im, $scaledIcon, (int) (($width - $iconWidth) / 2), $box[1], 0, 0, $iconWidth, $iconHeight);
   imagedestroy($scaledIcon);
   $bottom += $iconHeight;

   $bottom += $verticalMargins * 1;

   $statsWidth = $width - $sideMargins * 2;
   // We'll use the same font for the high/low temperatures and the precipitation, so
   // try multiple font sizes
   $highTempFontSize = GetBestTemperatureFontSize($weather->forecast->simpleforecast->forecastday[0]->high->fahrenheit, $statsWidth / 9, 0);
   $lowTempFontSize = GetBestTemperatureFontSize($weather->forecast->simpleforecast->forecastday[0]->low->fahrenheit, $statsWidth / 9, 0);
   $popFontSize = GetBestPrecipFontSize($weather->forecast->simpleforecast->forecastday[0]->pop, $statsWidth / 7, 0);
   $smallFontSize = min($highTempFontSize, $lowTempFontSize, $popFontSize);
   // Draw the high/low temperatures.
   $range = TemperatureRange($weather->forecast->simpleforecast->forecastday[0]->high->fahrenheit,
      $weather->forecast->simpleforecast->forecastday[0]->low->fahrenheit,
      $smallFontSize, $rangeWidth, $rangeHeight);

   // Draw the temperature
   $bigFontSize = GetBestTemperatureFontSize($currentTemp, $statsWidth / 2 * 0.2, 0);
   $temp = RenderTemperature($currentTemp, $bigFontSize, $tempWidth, $tempHeight);

   // Draw the precipitation
   $precip = RenderPrecipitation($weather->forecast->simpleforecast->forecastday[0]->pop, $smallFontSize, $precipWidth, $precipHeight);

   $stats = Merge($range, $temp, $precip, $statsWidth, 'middle', 'middle', 'mlr');
   imagecopy($im, $stats, (int) $sideMargins, $bottom, 0, 0, imagesx($stats), imagesy($stats));

   $bottom += imagesy($stats);
   imagedestroy($stats);
   $bottom += $verticalMargins;

   // Draw the conditions
   //$conditions = RenderMultilineText($summary, $smallFontSize, $statsWidth, $textHeight);
   // Draw the astro information
   $astro = RenderAstro($weather, $moon, $statsWidth, $height - $bottom);
   $astroHeight = imagesy($astro);
   $astroY = ($height - $bottom - $astroHeight) / 2 + $bottom;

   //imagecopy($im, $conditions, $sideMargins, ($astroY - $bottom - $textHeight) / 2 + $bottom, 0, 0, imagesx($conditions), imagesy($conditions));
   //imagedestroy($conditions);

   imagecopy($im, $astro, $sideMargins, $astroY, 0, 0, imagesx($astro), $astroHeight);
   imagedestroy($astro);

   return $im;
}

function HourConditions($weather, $width, $height, $biggestFontSize)
{
   global $font;

   $time = $weather->FCTTIME->civil;
   $temperature = $weather->temp->english;
   $icon = IconName($weather->icon_url);
   $precip = $weather->pop;

   // Draw the time
   $fontSize = min(GetBestFontSize('12:00 AM', $width, $height / 3.5), $biggestFontSize);
   $time = RenderText($time, $fontSize, $textWidth, $textHeight);

   // Draw the weather icon
   $path = "icons/$icon.png";
   $icon = imagecreatefrompng($path);
   $scaledIcon = ScaleImage($icon, (int) ($width / 6));
   imagedestroy($icon);

   $tempFontSize = GetBestTemperatureFontSize($temperature, (int) ($width * 0.4), 0);
   $popFontSize = GetBestPrecipFontSize($precip, (int) ($width * 0.4), 0);
   $fontSize = min($tempFontSize, $popFontSize);
   // Draw the temperature
   $temp = RenderTemperature($temperature, $fontSize  / 2, $tempWidth, $tempHeight);

   // Draw the precipitation
   $precip = RenderPrecipitation($precip, $fontSize / 2, $precipWidth, $precipHeight);

   $stats = Merge($temp, $scaledIcon, $precip, $width, 'middle', 'middle');

   return Stack($time, $stats, ($height - imagesy($time) - imagesy($stats)) / 2);
}

function IconName($url)
{
   $name = basename(parse_url($url, PHP_URL_PATH));
   return substr($name, 0, strpos($name, '.'));
}

function FormatTime($hour, $minute)
{
   if ($hour > 12)
   {
      $hour -= 12;
      $ampm = 'PM';
   }
   else
      $ampm = 'AM';
   return sprintf('%2d:%02d %s', $hour, $minute, $ampm);
}

function TemperatureRange($high, $low, $fontSize, &$width, &$height)
{
   $high = RenderTemperature($high, $fontSize, $width, $height);
   $highWidth = $width;
   $highHeight = $height;
   $low = RenderTemperature($low, $fontSize, $width, $height);
   $lowWidth = $width;
   $lowHeight = $height;

   $width = max($highWidth, $lowWidth) + 1;
   $height = $highHeight + $lowHeight + 5;
   $im = imagecreatetruecolor($width, $height);
   $white = imagecolorallocate($im, 255, 255, 255);
   $black = imagecolorallocate($im, 0, 0, 0);
   imagefill($im, 0, 0, $white);
   imagecopy($im, $high, $width - $highWidth - 1, 0, 0, 0, imagesx($high), imagesy($high));
   imagecopy($im, $low, $width - $lowWidth - 1, $height - $lowHeight - 1, 0, 0, imagesx($low), imagesy($low));
   imagesetthickness($im, 2);
   $mid = (int) ($height / 2);
   imageline($im, 0, $mid, $width, $mid, $black);
   imagedestroy($high);
   imagedestroy($low);

   return $im;
}

function RenderTemperature($temp, $fontSize, &$width, &$height)
{
   // Put a space in front the temperature to avoid truncating the beginning
   // a character (as imagettftext is wont to do). Don't append the space
   // if we're displaying a 3 digit temperature.
   if ($temp < 100 && $temp > -10)
      return RenderText(' ' . $temp . '°', $fontSize, $width, $height);
   else
      return RenderText($temp . '°', $fontSize, $width, $height);
}

function RenderPrecipitation($precip, $fontSize, &$width, &$height)
{
   // Figure out how big to make the raindrop icon: use the size of a 0
   $dropChar = RenderText('1', $fontSize, $width, $height);
   $dropCharWidth = $width;
   imagedestroy($dropChar);
   // Figure out the space to leave between the raindrop and the number
   $spaceWidth = max(1, $fontSize / 10);
   // Put a space in front the precip to avoid truncating the beginning
   // character (as imagettftext is wont to do). Don't append the space
   // if we're displaying a 3 digit precip.
   $text = RenderText($precip, $fontSize, $textWidth, $height);
   if ($precip < 100)
   {
      imagedestroy($text);
      $text = RenderText(' ' . $precip, $fontSize, $textWidth, $height);
   }
   $text = TrimImage($text, 1);
   $textWidth = imagesx($text);
   $percent = RenderText('%', $fontSize / 2, $percentWidth, $percentHeight);
   $text = Merge($text, null, $percent, $textWidth + $percentWidth, 'top');
   $textWidth += $percentWidth;
   $path = "icons/raindrop.png";
   $icon = imagecreatefrompng($path);
   $scaledIcon = ScaleImage($icon, (int) $dropCharWidth);
   imagedestroy($icon);

   $width = $textWidth + imagesx($scaledIcon) + $spaceWidth;
   $im = Merge($scaledIcon, null, $text, $width, 'bottom', 'middle', 'rml');
   $height = imagesy($im);
   return $im;
}

function RenderAstro($weather, $moon, $width, $height)
{
   $iconSize = (int) ($width / 2 / 4);
   $fontSize = GetBestFontSize(" Rise: 12:00 AM ", $width / 2 - $iconSize, 0);

   $sunrise = FormatTime($weather->sun_phase->sunrise->hour, $weather->sun_phase->sunrise->minute);
   $sunset = FormatTime($weather->sun_phase->sunset->hour, $weather->sun_phase->sunset->minute);
   $moonAgeString = sprintf('%02d', $moon);

   date_default_timezone_set($weather->current_observation->local_tz_long);
   $month = $weather->forecast->simpleforecast->forecastday[0]->date->month;
   $day = $weather->forecast->simpleforecast->forecastday[0]->date->day;
   $year = $weather->forecast->simpleforecast->forecastday[0]->date->year;
   $moonTimes = GetMoonTimes($year, $month, $day, $weather->current_observation->display_location->city, $weather->current_observation->display_location->state);
   $moonrise = date('g:i A', $moonTimes['rise']);
   $moonset = date('g:i A', $moonTimes['set']);
   if ($moonTimes['rise'] < $moonTimes['set'])
   {
      $firstMoonEvent = ' Rise: ';
      $secondMoonEvent = ' Set: ';
      $firstMoonTime = $moonrise;
      $secondMoonTime = $moonset;
   }
   else
   {
      $firstMoonEvent = ' Set: ';
      $secondMoonEvent = ' Rise: ';
      $firstMoonTime = $moonset;
      $secondMoonTime = $moonrise;
   }

   $astroWidth = $width / 2;
   $sun = AstroTimes('icons/sun.png', ' Rise: ', $sunrise, ' Set: ', $sunset, $astroWidth, $fontSize / 1.5);
   $moon = AstroTimes("moons/NH-moon{$moonAgeString}.gif", $firstMoonEvent, $firstMoonTime, $secondMoonEvent, $secondMoonTime, $astroWidth, $fontSize / 1.5);
   $astro = Merge($sun, null, $moon, imagesx($sun) + imagesx($moon) + 10);

   return $astro;
}

function AstroTimes($icon, $str1, $time1, $str2, $time2, $width, $fontSize)
{
   global $font;

   if (strpos($icon, '.gif'))
      $icon = imagecreatefromgif($icon);
   else
      $icon = imagecreatefrompng($icon);

   $str1box = imagettfbbox($fontSize, 0, $font, $str1);
   $time1box = imagettfbbox($fontSize, 0, $font, $time1);
   $str2box = imagettfbbox($fontSize, 0, $font, $str2);
   $time2box = imagettfbbox($fontSize, 0, $font, $time2);
   $textHeight = max(BoxHeight($str1box), BoxHeight($time1box)) + max(BoxHeight($str2box), BoxHeight($time2box));

   $str1 = RenderText($str1, $fontSize, $s1width, $s1height);
   $time1 = RenderText($time1, $fontSize, $t1width, $t1height);
   $event1 = Merge($str1, null, $time1, $width - imagesx($icon));

   $str2 = RenderText($str2, $fontSize, $s2width, $s2height);
   $time2 = RenderText($time2, $fontSize, $t2width, $t2height);
   $event2 = Merge($str2, null, $time2, $width - imagesx($icon));

   $events = Stack($event1, $event2, $fontSize / 2);

   return Merge($icon, null, $events, $width);
}

function FutureConditions($dayInfo, $dayWidth, $dayHeight, &$statsFontSize, &$iconSize, $calculatetSizes)
{
   $dayFontSize = GetBestFontSize(" Wednesday ", $dayWidth, $dayHeight / 5);

   $im = imagecreatetruecolor($dayWidth, $dayHeight);
   $white = imagecolorallocate($im, 255, 255, 255);
   imagefill($im, 0, 0, $white);

   $day = RenderText(' ' . $dayInfo->date->weekday . ' ', $dayFontSize, $textWidth, $textHeight);
   $verticalMargin = 3;
   imagecopy($im, $day, (int)(($dayWidth - $textWidth) / 2), $verticalMargin, 0, 0, $textWidth, $textHeight);
   $top = imagesy($day) + $verticalMargin;
   imagedestroy($day);

   $statsWidth = $dayWidth * 0.9;
   if ($calculatetSizes)
   {
      $highTempFontSize = GetBestTemperatureFontSize($dayInfo->high->fahrenheit, $statsWidth / 2, 0);
      $lowTempFontSize = GetBestTemperatureFontSize($dayInfo->low->fahrenheit, $statsWidth / 2, 0);
      $popFontSize = GetBestPrecipFontSize($dayInfo->pop, $statsWidth / 2, 0);
      $fontSize = $statsFontSize = min($highTempFontSize, $lowTempFontSize, $popFontSize);
   }
   else
      $fontSize = $statsFontSize;
   // Draw the high/low temperatures
   $range = TemperatureRange($dayInfo->high->fahrenheit, $dayInfo->low->fahrenheit,
      $fontSize / 2, $rangeWidth, $rangeHeight);

   // Draw the precipitation
   $precip = RenderPrecipitation($dayInfo->pop, $fontSize / 2, $precipWidth, $precipHeight);

   $stats = Merge($range, null, $precip, $statsWidth);
   $statsHeight = imagesy($stats);
   $statsY = $dayHeight - $statsHeight - $verticalMargin;
   imagecopy($im, $stats, (int) (($dayWidth - imagesx($stats)) / 2), $statsY, 0, 0, imagesx($stats), $statsHeight);
   imagedestroy($stats);

   $icon = IconName($dayInfo->icon_url);
   // Draw the weather icon
   $path = "icons/$icon.png";
   $icon = imagecreatefrompng($path);
   if ($calculatetSizes)
      $iconSize = min(($dayHeight - $top - $statsHeight - $verticalMargin) * 0.8, $dayWidth * 0.8);
   $scaledIcon = ScaleImage($icon, (int) $iconSize);
   imagedestroy($icon);
   $iconWidth = imagesx($scaledIcon);
   $iconHeight = imagesy($scaledIcon);
   imagecopy($im, $scaledIcon, (int) (($dayWidth - $iconWidth) / 2), ($statsY - $top - $iconHeight) / 2 + $top, 0, 0, $iconWidth, $iconHeight);
   imagedestroy($scaledIcon);
   $top += $iconHeight;

   return $im;
}

function Indent($image, $indent)
{
   $im = imagecreatetruecolor($indent, 1);
   $white = imagecolorallocate($im, 255, 255, 255);
   imagefill($im, 0, 0, $white);
   return Merge($im, null, $image, $indent + imagesx($image));
}

function Merge($left, $center, $right, $width, $valign = 'middle', $halign = 'middle', $order = 'lmr')
{
   $leftWidth = imagesx($left);
   $leftHeight = imagesy($left);
   if ($center)
   {
      $centerWidth = imagesx($center);
      $centerHeight = imagesy($center);
   }
   else
   {
      $centerWidth = 0;
      $centerHeight = 0;
   }
   $rightWidth = imagesx($right);
   $rightHeight = imagesy($right);

   $height = max($leftHeight, $centerHeight, $rightHeight);

   $im = imagecreatetruecolor($width, $height);
   $white = imagecolorallocate($im, 255, 255, 255);
   $black = imagecolorallocate($im, 0, 0, 0);
   imagefill($im, 0, 0, $white);
   if ($valign == 'middle')
   {
      $lefty = (int) (($height - $leftHeight) / 2);
      $righty = (int) (($height - $rightHeight) / 2);
      $centery = (int) (($height - $centerHeight) / 2);
   }
   else if ($valign == 'bottom')
   {
      $lefty = $height - $leftHeight;
      $righty = $height - $rightHeight;
      $centery = $height - $centerHeight;
   }
   else // top align
      $lefty = $righty = $centery = 0;

   if ($halign == 'middle')
   {
      $centerx = ($width - $centerWidth) / 2;
   }
   else if ($halign == 'gap') // equal gaps
   {
      $centerx = ($width - $leftWidth - $centerWidth - $rightWidth) / 2 + $leftWidth;
   }
   for ($i = 0; $i < 3; ++$i)
   {
      switch ($order[$i])
      {
         case 'l' :
            imagecopy($im, $left, 0, $lefty, 0, 0, $leftWidth, $leftHeight);
            imagedestroy($left);
            break;

         case 'm' :
            if ($center)
            {
               imagecopy($im, $center, (int) $centerx, $centery, 0, 0, $centerWidth, $centerHeight);
               imagedestroy($center);
            }
            break;

         case 'r' :
            imagecopy($im, $right, $width - $rightWidth, $righty, 0, 0, $rightWidth, $rightHeight);
            imagedestroy($right);
            break;
      }
   }

   return $im;
}

function Stack($top, $bottom, $spacing = 0)
{
   $topWidth = imagesx($top);
   $topHeight = imagesy($top);
   $bottomWidth = imagesx($bottom);
   $bottomHeight = imagesy($bottom);

   $width = max($topWidth, $bottomWidth);
   $height = $topHeight + $bottomHeight + $spacing;

   $im = imagecreatetruecolor($width, $height);
   $white = imagecolorallocate($im, 255, 255, 255);
   imagefill($im, 0, 0, $white);
   imagecopy($im, $top, 0, 0, 0, 0, $topWidth, $topHeight);
   imagecopy($im, $bottom, 0, $topHeight + $spacing, 0, 0, $bottomWidth, $bottomHeight);

   imagedestroy($top);
   imagedestroy($bottom);

   return $im;
}


function RenderText($text, $fontSize, &$width, &$height)
{
   global $font;

   $box = imagettfbbox($fontSize, 0, $font, $text);
   $width = BoxWidth($box);
   $height = BoxHeight($box);
   $im = imagecreatetruecolor($width + 1, $height + 1);
   $white = imagecolorallocate($im, 255, 255, 255);
   $black = imagecolorallocate($im, 0, 0, 0);
   imagefill($im, 0, 0, $white);
   imagettftext($im, $fontSize, 0, 0, abs($box[5]), $black, $font, $text);
   $im = TrimImage($im, 0);
   $width = imagesx($im);
   return $im;
}

function RenderMultilineText($text, $fontSize, $width, &$height)
{
   global $font;

   $words = explode(' ', $text);
   $lines = array();
   $height = 0;
   $line = '';
   while (count($words))
   {
      if ($line != '')
         $tryLine = trim($line . ' ' . $words[0]);
      else
         $tryLine = trim($words[0]);
      $box = imagettfbbox(' ' . $fontSize, 0, $font, $tryLine);
      // If the word fits on the line
      if (BoxWidth($box) < $width)
      {
         $line = $tryLine;
         array_splice($words, 0, 1);
      }
      // Else (doesn't fit)
      else
      {
         // If there's no room for this word even on an empty line
         if ($line == '')
         {
            // Give up
            $words = array();
            break;
         }
         // Else save the line and start building a new line
         else
         {
            $lines[] = array($line, $box);
            $height += BoxHeight($box);
            $line = '';
         }
      }
   }
   if ($line != '')
   {
      $lines[] = array($line, $box);
      $height += BoxHeight($box);
   }

   $im = imagecreatetruecolor($width + 1, $height + count($lines));
   $white = imagecolorallocate($im, 255, 255, 255);
   $black = imagecolorallocate($im, 0, 0, 0);
   imagefill($im, 0, 0, $white);
   $pos = 0;
   foreach ($lines as $line)
   {
      $box = imagettftext($im, $fontSize, 0, 0, $pos + abs($line[1][5]), $black, $font, ' ' . $line[0]);
      $pos += BoxHeight($box) + 1;
   }

   return $im;
}

function BoxWidth($box)
{
   return abs($box[4] - $box[0]);
}

function BoxHeight($box)
{
   return abs($box[5] - $box[1]);
}

function ScaleImage($image, $newWidth)
{
   $oldWidth = imagesx($image);
   $oldHeight = imagesy($image);
   $newHeight = (int) ($oldHeight * $newWidth / $oldWidth);
   $newWidth = (int) $newWidth;

   $im = imagecreatetruecolor($newWidth, $newHeight);
   $white = imagecolorallocate($im, 255, 255, 255);
   imagefill($im, 0, 0, $white);

   imagecopyresampled($im, $image , 0, 0, 0, 0, $newWidth, $newHeight, $oldWidth, $oldHeight);

   return $im;
}

// Find the font size that will fit in the given space. Width or height can be zero for "don't care"
function GetBestFontSize($text, $width, $height)
{
   global $font;

   $lowFontSize = 1;
   $highFontSize = 200;
   $fontSize = 12;
   while ($highFontSize - $lowFontSize > 1)
   {
      $box = imagettfbbox($fontSize, 0, $font, $text);
      if ($width != 0)
      {
         if (BoxWidth($box) < $width - 1)
         {
            $lowFontSize = $fontSize;
         }
         else if (BoxWidth($box) > $width)
         {
            $highFontSize = $fontSize;
         }
         else
            break;
         $fontSize = ($highFontSize - $lowFontSize) / 2 + $lowFontSize;
      }
   }

   if ($height != 0 && BoxHeight($box) > $height)
   {
      $highFontSize = $fontSize;
      $lowFontSize = 1;
      $fontSize = ($highFontSize - $lowFontSize) / 2;
      while ($highFontSize - $lowFontSize > 1)
      {
         $box = imagettfbbox($fontSize, 0, $font, $text);
         if (BoxHeight($box) < $height - 1)
         {
            $lowFontSize = $fontSize;
         }
         else if (BoxHeight($box) > $height)
         {
            $highFontSize = $fontSize;
         }
         else
            break;
         $fontSize = ($highFontSize - $lowFontSize) / 2 + $lowFontSize;
      }
   }

   return $fontSize;
}

// Find the font size that will fit in the given space. Width or height can be zero for "don't care"
function GetBestTemperatureFontSize($text, $width, $height)
{
   global $font;

   $lowFontSize = 1;
   $highFontSize = 200;
   $fontSize = ($highFontSize - $lowFontSize) / 2;
   if ($width != 0)
   {
      while ($highFontSize - $lowFontSize > 1)
      {
         $temp = RenderTemperature($text, $fontSize, $tempWidth, $tempHeight);
         imagedestroy($temp);
         if ($tempWidth < $width)
         {
            $lowFontSize = $fontSize;
         }
         else if ($tempWidth > $width)
         {
            $highFontSize = $fontSize;
         }
         else
            break;
         $fontSize = ($highFontSize - $lowFontSize) / 2 + $lowFontSize;
      }
   }

   if ($height != 0 && $tempHeight > $height)
   {
      $highFontSize = $fontSize;
      $lowFontSize = 1;
      $fontSize = ($highFontSize - $lowFontSize) / 2;
      while ($highFontSize - $lowFontSize > 1)
      {
         $temp = RenderTemperature($text, $fontSize, $tempWidth, $tempHeight);
         imagedestroy($temp);
         if ($tempHeight < $height)
         {
            $lowFontSize = $fontSize;
         }
         else if ($tempHeight > $height)
         {
            $highFontSize = $fontSize;
         }
         else
            break;
         $fontSize = ($highFontSize - $lowFontSize) / 2 + $lowFontSize;
      }
   }

   return $fontSize;
}

// Find the font size that will fit in the given space. Width or height can be zero for "don't care"
function GetBestPrecipFontSize($text, $width, $height)
{
   global $font;

   $lowFontSize = 1;
   $highFontSize = 200;
   $fontSize = 12;
   if ($width != 0)
   {
      while ($highFontSize - $lowFontSize > 1)
      {
         $pop = RenderPrecipitation($text, $fontSize, $popWidth, $popHeight);
         imagedestroy($pop);
         if ($popWidth < $width)
         {
            $lowFontSize = $fontSize;
         }
         else if ($popWidth > $width)
         {
            $highFontSize = $fontSize;
         }
         else
            break;
         $fontSize = ($highFontSize - $lowFontSize) / 2 + $lowFontSize;
      }
   }

   if ($height != 0 && $popHeight > $height)
   {
      $highFontSize = $fontSize;
      $lowFontSize = 1;
      $fontSize = ($highFontSize - $lowFontSize) / 2;
      while ($highFontSize - $lowFontSize > 1)
      {
         $pop = RenderPrecipitation($text, $fontSize, $popWidth, $popHeight);
         imagedestroy($pop);
         if ($popHeight < $height - 1)
         {
            $lowFontSize = $fontSize;
         }
         else if ($popHeight > $height)
         {
            $highFontSize = $fontSize;
         }
         else
            break;
         $fontSize = ($highFontSize - $lowFontSize) / 2 + $lowFontSize;
      }
   }

   return $fontSize;
}

function ReturnError($message)
{
   global $width;
   global $height;
   $text = RenderMultilineText($message, 36, $width, $height);
   $im = imagecreatetruecolor($width, $height);
   $white = imagecolorallocate($im, 255, 255, 255);
   $black = imagecolorallocate($im, 0, 0, 0);
   imagefill($im, 0, 0, $white);
   imagecopy($im, $text, 0, 0, 0, 0, imagesx($text), imagesy($text));

   // Convert image color space to grayscale so the Kindle can draw it
   if (class_exists('Imagick'))
   {
      $file = tempnam('.', 'png');
      imagepng($im, $file);
      imagedestroy($im);

      $im = new Imagick($file);
      $im->transformImageColorspace(imagick::COLORSPACE_GRAY);
      echo $im;
      unlink($file);
   }
   // ImageMagick isn't installed in our debug environment, but there's
   // no Kindle there either so it's OK
   else
   {
      imagepng($im);
      imagedestroy($im);
   }
   exit(0);
}

function GetMoonTimes($year, $month, $date, $city, $state)
{
   for ($retry = 0; $retry < 3; ++$retry)
   {
      try
      {
         $query =  new HTTP_Request2("http://aa.usno.navy.mil/rstt/onedaytable?ID=AA&year=$year&month=$month&day=$date&state=$state&place=$city", HTTP_Request2::METHOD_GET,
            array('connect_timeout' => 2, 'timeout' => 4));
         $query->setHeader(array(
            'Host' => 'aa.usno.navy.mil',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:28.0) Gecko/20100101 Firefox/28.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive'
         ));
         $response = $query->send();
         $status = $response->getStatus();
         if ($status == 200)
         {
            $body = $response->getBody();

            $rise = strtotime("$year-$month-$date " . ExtractTime($body, 'Moonrise'));
            $set = strtotime("$year-$month-$date " . ExtractTime($body, 'Moonset'));

            return array('rise' => $rise, 'set' => $set);
         }
      }
      catch (Exception $e)
      {
      }
   }
   return null;
}

function ExtractTime($body, $label)
{
   $pos = strpos($body, 'on preceding day');
   $pos = strpos($body, $label, $pos);
   $pos = strpos($body, '<td>', $pos) + 4;
   $end = strpos($body, '</td>', $pos);
   $time = substr($body, $pos, $end - $pos);
   $time = str_replace('a.m.', 'AM', $time);
   $time = str_replace('p.m.', 'PM', $time);
   return $time;
}

// Trims white space from the left and right sides of an image. Destroys the input
// image, returns the trimmed image
function TrimImage($im, $newMargin)
{
   $width = imagesx($im);
   $height = imagesy($im);

   $left = 0;
   while ($left < $width)
   {
      for ($row = 0; $row < $height; ++$row)
      {
         if (imagecolorat($im, $left, $row) != 0xffffff)
            break;
      }
      if ($row == $height)
         ++$left;
      else
         break;
   }
   $right = $width - 1;
   while ($right > $left)
   {
      for ($row = 0; $row < $height; ++$row)
      {
         if (imagecolorat($im, $right, $row) != 0xffffff)
            break;
      }
      if ($row == $height)
         --$right;
      else
         break;
   }

   $trimmed = imagecreatetruecolor($right - $left + 1 + $newMargin * 2, $height);
   $white = imagecolorallocate($trimmed, 255, 255, 255);
   imagefill($trimmed, 0, 0, $white);
   imagecopy($trimmed, $im, 0, $newMargin, $left, 0, $right - $left + 1, $height);

   imagedestroy($im);
   return $trimmed;
}

?>
