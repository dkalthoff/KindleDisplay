<?php

require_once 'HTTP/Request2.php';

header("Content-type: image/png");
header("refresh: 1800");

$width = 800;
$height = 600;
$font = './Gabriola.ttf';

$geoCode = "45.6811274,-94.5382767";
$apiKey = "-- api key here --";
$endpoint = "https://api.darksky.net/forecast/$apiKey/$geoCode";

try
{
   $im = imagecreatetruecolor($width, $height);
   $white = imagecolorallocate($im, 255, 255, 255);
   $black = imagecolorallocate($im, 0, 0, 0);
   imagefill($im, 0, 0, $white);

   $numberOfDays = 4;
   $numberOfHours = 6;

   $weather = GetForecast($geoCode, $apiKey, $endpoint);
   //$weather = GetForecastFromFile();

   date_default_timezone_set($weather->timezone);
   $moon = round($weather->daily->data[0]->moonPhase * 100 / 3.56);

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
   for ($h = 0; $h < min($numberOfHours, count($weather->hourly->data)); ++$h)
   {
      $hourlyData = $weather->hourly->data[$h];
      $top = (int) ($todaysHeight / $numberOfHours * $h);
      $hour = HourConditions($hourlyData, $hourWidth, $hourHeight, $biggestFontSize);
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
      $future = FutureConditions($weather->daily->data[$i], $dayWidth, $dayHeight, $statsFontSize, $iconSize, true);
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
      $future = FutureConditions($weather->daily->data[$i], $dayWidth, $dayHeight, $finalStatsFontSize, $finalIconSize, false);
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
	//ReturnError('ImageMagick class does not exists');
      imagepng($im);
      imagedestroy($im);
   }
}
catch (Exception $e)
{
   $file = basename($e->getFile());
   ReturnError('Exception thrown: ' . $e->getMessage() . ' at line ' . $e->getLine() . ' in file ' . $file);
}

function GetForecast($geoCode, $apiKey, $endpoint)
{
    $query = new HTTP_Request2($endpoint, HTTP_Request2::METHOD_GET,
    array('connect_timeout' => 5, 'timeout' => 10));
    $query->setHeader(array(
        'Host' => 'api.darksky.net',
        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.77 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Encoding' => 'gzip, deflate',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Connection' => 'keep-alive',
    ));
    $response = $query->send();
    $status = $response->getStatus();

    if ($status != 200) throw new Exception("Attempt to load weather returned $status");

    $body = $response->getBody();
    return json_decode($body);
}

function GetForecastFromFile()
{
    $fileContents = file_get_contents("./DarkSky_Forecast_Example.json", true); 
    $forecast = json_decode($fileContents);

    return $forecast;
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

   // Write date and weather summary
   $text = date("D, M j", $weather->currently->time) . " - " . $weather->currently->summary . " ";
   $headerFontSize = min(40, GetBestFontSize($text, $width - $sideMargins, 0));
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
   $icon = IconName($weather->currently->icon);
   $path = "icons/$icon.png";
   $icon = imagecreatefrompng($path);
   $scaledIcon = ScaleImage($icon, (int) ($width * 0.25));
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
   $highTemp = round($weather->daily->data[0]->temperatureHigh);
   $lowTemp = round($weather->daily->data[0]->temperatureLow);
   $precip = $weather->currently->precipProbability * 100;

   $highTempFontSize = GetBestTemperatureFontSize($highTemp, $statsWidth / 5, 0);
   $lowTempFontSize = GetBestTemperatureFontSize($lowTemp, $statsWidth / 5, 0);
   $popFontSize = GetBestPrecipFontSize($precip, $statsWidth / 5, 0);
   $smallFontSize = min($highTempFontSize, $lowTempFontSize, $popFontSize);
   
   // Draw the high/low temperatures.
   $range = TemperatureRange($highTemp, $lowTemp, $smallFontSize, $rangeWidth, $rangeHeight);

   // Draw the temperature
   $currentTemp = round($weather->currently->temperature);
   $bigFontSize = GetBestTemperatureFontSize(100, $statsWidth * 0.3, 0);
   $temp = RenderTemperature($currentTemp, $bigFontSize, $tempWidth, $tempHeight);

   // Draw the precipitation
   $precip = RenderPrecipitation($precip, $smallFontSize, $precipWidth, $precipHeight);

   $stats = Merge($range, $temp, $precip, $statsWidth, 'middle', 'middle', 'mlr');
   imagecopy($im, $stats, (int) $sideMargins, $bottom, 0, 0, imagesx($stats), imagesy($stats));

   $bottom += imagesy($stats);
   imagedestroy($stats);
   $bottom += $verticalMargins;

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

function HourConditions($hourlyData, $width, $height, $biggestFontSize)
{
   global $font;

   $time = date("g:i A", $hourlyData->time);
   $temperature = round($hourlyData->temperature);
   $icon = IconName($hourlyData->icon);
   $precip = $hourlyData->precipProbability * 100;

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

function IconName($icon)
{
   switch ($icon) {
      case "clear-day":
         return "clear";
      case "clear-night":
         return "nt_clear";
      case "cloudy":
         return "cloudy";
      case "fog":
         return "fog";
      case "partly-cloudy-day":
         return "partlycloudy";
      case "partly-cloudy-night":
         return "nt_partlycloudy";
      case "rain":
         return "rain";
      case "sleet":
         return "sleet";
      case "snow":
         return "snow";
      case "wind":
         return "wind";
      default:
         return "";
  }
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

   // Sunrise & Sunset
   $sunrise = date("g:i A", $weather->daily->data[0]->sunriseTime);
   $sunset = date("g:i A", $weather->daily->data[0]->sunsetTime);
   $sun = AstroTimes('icons/sun.png', ' Rise: ', $sunrise, ' Set: ', $sunset, $width / 2.6, $fontSize / 1.5);
   
   // Moon phase
   $moonAgeString = sprintf('%02d', $moon);

   // Wind & Humidity
   $wind = 'Wind: ' . round($weather->daily->data[0]->windSpeed) . ' mph';
   $humidity = 'Humidity: ' . ($weather->daily->data[0]->humidity * 100) . '%';
   $windGust = 'Gust: ' . round($weather->daily->data[0]->windGust) . ' mph';
   $windGustTime = 'Time: ' . date('g:i A', $weather->daily->data[0]->windGustTime);
   $moon = AstroTimes("moons/NH-moon{$moonAgeString}.gif", $wind, $humidity, $windGust, $windGustTime, $width / 1.7, $fontSize / 1.5);
   
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
   $weekDay = " " . date("D", $dayInfo->time) . " ";
   $highTemp = round($dayInfo->temperatureHigh);
   $lowTemp = round($dayInfo->temperatureLow);
   $precip = $dayInfo->precipProbability * 100;

   $dayFontSize = GetBestFontSize($weekDay, $dayWidth, $dayHeight / 5);

   $im = imagecreatetruecolor($dayWidth, $dayHeight);
   $white = imagecolorallocate($im, 255, 255, 255);
   imagefill($im, 0, 0, $white);
    
   $day = RenderText($weekDay, $dayFontSize, $textWidth, $textHeight);
   $verticalMargin = 3;
   imagecopy($im, $day, (int)(($dayWidth - $textWidth) / 2), $verticalMargin, 0, 0, $textWidth, $textHeight);
   $top = imagesy($day) + $verticalMargin;
   imagedestroy($day);

   $statsWidth = $dayWidth * 0.9;
   if ($calculatetSizes)
   {
      $highTempFontSize = GetBestTemperatureFontSize($highTemp, $statsWidth / 1.4, 0);
      $lowTempFontSize = GetBestTemperatureFontSize($lowTemp, $statsWidth / 1.4, 0);
      $popFontSize = GetBestPrecipFontSize($precip, $statsWidth / 1.4, 0);
      $fontSize = $statsFontSize = min($highTempFontSize, $lowTempFontSize, $popFontSize);
   }
   else
      $fontSize = $statsFontSize;

   // Draw the high/low temperatures
   $range = TemperatureRange($highTemp, $lowTemp, $fontSize / 2, $rangeWidth, $rangeHeight);

   // Draw the precipitation
   $precip = RenderPrecipitation($precip, $fontSize / 2, $precipWidth, $precipHeight);

   $stats = Merge($range, null, $precip, $statsWidth);
   $statsHeight = imagesy($stats);
   $statsY = $dayHeight - $statsHeight - $verticalMargin;
   imagecopy($im, $stats, (int) (($dayWidth - imagesx($stats)) / 2), $statsY, 0, 0, imagesx($stats), $statsHeight);
   imagedestroy($stats);

   $icon = IconName($dayInfo->icon);
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
