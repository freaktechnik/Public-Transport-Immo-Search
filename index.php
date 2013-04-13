<!--
    Created by Martin Giger
    Licensed under the GPL v3 license.
-->
<?php
    $baseURL = "https://www.comparis.ch/immobilien/result?requestobject={%22DealType%22%3A10%2C%22SiteId%22%3A0%2C%22RootPropertyTypes%22%3A[1]%2C%22PropertyTypes%22%3A[]%2C%22RoomsFrom%22:null,%22RoomsTo%22:null,%22LivingSpaceFrom%22:null,%22LivingSpaceTo%22:null,%22PriceFrom%22:null,%22PriceTo%22:null,%22ComparisPointsMin%22:0,%22AdAgeMax%22:0,%22AdAgeInHoursMax%22:null,%22Keyword%22:null,%22WithImagesOnly%22:false,%22WithPointsOnly%22:null,%22Radius%22:null,%22MinAvailableDate%22:null,%22MinChangeDate%22:%221753-01-01T00:00:00%22,%22LocationSearchString%22:".getParam('locationSearchString').",%22Sort%22:3,%22HasBalcony%22:false,%22HasTerrace%22:false,%22HasFireplace%22:false,%22HasDishwasher%22:false,%22HasWashingMachine%22:false,%22HasLift%22:false,%22HasParking%22:false,%22PetsAllowed%22:false,%22MinergieCertified%22:false,%22WheelchairAccessible%22:false,%22LowerLeftLatitude%22:null,%22LowerLeftLongitude%22:null,%22UpperRightLatitude%22:null,%22UpperRightLongitude%22:null}&page=";
    $places = array();
    
    $page = 0;
    
    if($_GET['locationSearchString']) {
        $placeBuffer = getComparisPage($baseURL.$page);
        while($placeBuffer) {
            $places = array_merge($places,$placeBuffer);
            $page = $page +1;
            $placeBuffer = getComparisPage($baseURL.$page);
        }
    }

    function getComparisPage($req) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $req);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $data = curl_exec($ch);
        curl_close($ch);
        
        $html = new DOMDocument();
        $html->loadHTML($data);
        
        $results = false;
        $j = 0;
        $rows = $html->getElementsByTagName('tr');
        if($rows->length>0&&$html->getElementById('pnlNoResult')==NULL) {
            $results = array();
            foreach($rows as $node) {
                if($node->getAttribute('class')=="RowResultGeneralData") {
                    $results[$j]->url = "https://www.comparis.ch". getDescriptionLink($node)->getAttribute('href');
                    $results[$j]->title = getDescriptionLink($node)->nodeValue;
                    $results[$j]->address = getAddressString($node->getElementsByTagName('table')->item(0));
                    $j = $j+1;
                }
            }
        }
        
        return $results;
    }
    
    function getDescriptionLink($node) {
        $i = 0;
        if($node->getElementsByTagName('img')->length>0) 
            $i = 1;
        return $node->getElementsByTagName('a')->item($i);
    }
    
    function getParam($key) {
        if($_GET[$key])
            return "%22".$_GET[$key]."%22";
        else
            return "null";
    }
    
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
    function getAddressString($node) {
        foreach($node->getElementsByTagName('td') as $row) {
            if($row->getAttribute('valign')=='top'&&$row->getAttribute('align')=='left') {
                return preg_replace('/\n/','',$row->nodeValue);
            }
        }
        return null;
    }
?>
<html>
    <head>
        <title>pt aware immo crawler</title>
    </head>
    <body>
        <form action="index.php" method="get">
            <label for="locationSearchString">Ort oder PLZ </label><input type="text" name="locationSearchString" value="<?php echo $_GET['locationSearchString']; ?>">
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