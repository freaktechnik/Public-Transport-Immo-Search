<!DOCTYPE html>
<html>
<!--
    Created by Martin Giger
    Licensed under the GPL v3 license.
-->
<?php
    // this is the url for the comparis search page
    $baseURL = "https://www.comparis.ch/immobilien/result?requestobject={%22DealType%22%3A10%2C%22SiteId%22%3A0%2C%22RootPropertyTypes%22%3A[1]%2C%22PropertyTypes%22%3A[]%2C%22RoomsFrom%22:null,%22RoomsTo%22:null,%22LivingSpaceFrom%22:null,%22LivingSpaceTo%22:null,%22PriceFrom%22:null,%22PriceTo%22:null,%22ComparisPointsMin%22:0,%22AdAgeMax%22:0,%22AdAgeInHoursMax%22:null,%22Keyword%22:null,%22WithImagesOnly%22:false,%22WithPointsOnly%22:null,%22Radius%22:null,%22MinAvailableDate%22:null,%22MinChangeDate%22:%221753-01-01T00:00:00%22,%22LocationSearchString%22:".getParam('locationSearchString').",%22Sort%22:3,%22HasBalcony%22:".getParam('hasBalcony').",%22HasTerrace%22:false,%22HasFireplace%22:false,%22HasDishwasher%22:false,%22HasWashingMachine%22:false,%22HasLift%22:false,%22HasParking%22:false,%22PetsAllowed%22:false,%22MinergieCertified%22:false,%22WheelchairAccessible%22:false,%22LowerLeftLatitude%22:null,%22LowerLeftLongitude%22:null,%22UpperRightLatitude%22:null,%22UpperRightLongitude%22:null}&page=";
    $places = array();

    $page = 0;

    // only start the query to comparis if we actually got any query
    if($_GET['locationSearchString']) {
        $placeBuffer = getComparisPage($baseURL.$page);
        // load all pages from comparis
        while($placeBuffer) {
            $places = array_merge($places,$placeBuffer);
            $page = $page +1;
            $placeBuffer = getComparisPage($baseURL.$page);
        }
    }

    function getComparisPage($req) {
        // load the page
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $req);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $data = curl_exec($ch);
        curl_close($ch);
        
        // parse the html of it
        $html = new DOMDocument();
        $html->loadHTML($data);
        
        $results = false;
        $j = 0;
        $rows = $html->getElementsByTagName('tr');
        if($rows->length>0&&$html->getElementById('pnlNoResult')==NULL) {
            $results = array();
            foreach($rows as $node) {
                if($node->getAttribute('class')=="RowResultGeneralData") {
                    // save every entry (more info could be extracted)
                    $results[$j]->url = "https://www.comparis.ch". getDescriptionLink($node)->getAttribute('href');
                    $results[$j]->title = getDescriptionLink($node)->nodeValue;
                    $results[$j]->address = getAddressString($node->getElementsByTagName('table')->item(0));
                    $j = $j+1;
                }
            }
        }
        
        return $results;
    }
    
    // gets the description link item, since its position varies based on whether the ad has an image
    function getDescriptionLink($node) {
        $i = 0;
        if($node->getElementsByTagName('table')->item(0)->getElementsByTagName('td')->item(0)->getElementsByTagName('img')->length>0)
            $i = 1;
        return $node->getElementsByTagName('a')->item($i);
    }
    
    // parse a parameter for the query
    function getParam($key) {
        if($_GET[$key]) {
            if(preg_match('/^has/i',$key))
                return 'true';

            return "%22".$_GET[$key]."%22";
        }
        else {
            if(preg_match('/^has/i',$key))
                return 'false';
            return "null";
        }
    }
    
    // load time from google
    function getPTinfo($address) {
        $address = preg_replace("/\s/","+",$address);
        $baseURL = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=".$address."&destinations=Rämistrasse+101+8006+Zürich&sensor=false&mode=transit&language=de&units=metric";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $json = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($json);
        return $data->rows[0]->elements[0]->duration->text;
    }
    
    // extract address info from an ads cell
    function getAddressString($node) {
        foreach($node->getElementsByTagName('td') as $row) {
            if($row->getAttribute('valign')=='top'&&$row->getAttribute('align')=='left') {
                $addss = preg_split('/\s+/',$row->nodeValue);
                return preg_replace('/(\n|'.$addss[1].'\s)+/','',$row->nodeValue);
            }
        }
        return null;
    }
?>
    <head>
        <title>pt aware immo crawler</title>
    </head>
    <body>
        <form action="index.php" method="get">
            <label for="locationSearchString">Ort oder PLZ </label><input type="text" name="locationSearchString" value="<?php echo $_GET['locationSearchString']; ?>">
            <input type="checkbox" name="hasBalcony" value="1" <?php if($_GET['hasBalcony']=='1') echo 'checked'; ?>><label for="hasBalcony"> mit Balkon</label>
            <input type="submit" value="Suchen">
        </form>
        <ul>
            <?php
                foreach($places as $place) {
                    echo "<li><a href='".$place->url."'>".$place->title."</a><br>".$place->address."<br>".getPTinfo($place->address)."</li>";
                }
            ?>
        </ul>
    </body>
</html>
