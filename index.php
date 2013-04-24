<!DOCTYPE html>
<html>
<!--
    Created by Martin Giger
    Licensed under the GPL v3 license.
-->
<?php
    include_once 'filter.php';
    
    // this is the url for the comparis search page
    $filter = new Filter();
    initFilter($filter);
    
    foreach($_GET as $name => $value) {
        $aFilter->setProperty(ucfirst($name),$value);
    }
    
    $baseURL = "https://www.comparis.ch/immobilien/result?requestobject=".$filter->getComparisRequest()."&page=";
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

    function initFilter($aFilter) {
        $rootProperty = array(0);
        $propertyTypes = array();
        $aFilter->setProperty('DealType',10,FilterProperty::INTEGER,'comparis');
        $aFilter->setProperty('SiteId',0,FilterProperty::INTEGER,'comparis');
        $aFilter->setProperty('RootPropertyTypes',$rootProperty,FilterProperty::ARR,'comparis');
        $aFilter->setProperty('PropertyTypes',$propertyTypes,FilterProperty::ARR,'comparis');
        $aFilter->setProperty('RoomsFrom',NULL,FilterProperty::FLOAT,'comparis');
        $aFilter->setProperty('RoomsTo',NULL,FilterProperty::FLOAT,'comparis');
        $aFilter->setProperty('LivingSpaceFrom',NULL,FilterProperty::FLOAT,'comparis');
        $aFilter->setProperty('LivingSpaceTo',NULL,FilterProperty::FLOAT,'comparis');
        $aFilter->setProperty('PriceFrom',NULL,FilterProperty::FLOAT,'comparis');
        $aFilter->setProperty('PriceTo',NULL,FilterProperty::FLOAT,'comparis');
        $aFilter->setProperty('ComparisPointsMin',0,FilterProperty::INTEGER,'comparis');
        $aFilter->setProperty('AdAgeMax',0,FilterProperty::INTEGER,'comparis'); // in days
        $aFilter->setProperty('AdAgeInHoursMax',NULL,FilterProperty::STRING,'comparis');
        $aFilter->setProperty('Keyword',NULL,FilterProperty::STRING,'comparis');
        $aFilter->setProperty('WithImagesOnly',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('WithPointsOnly',NULL,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('Radius',NULL,FilterProperty::INTEGER,'comparis');
        $aFilter->setProperty('MinAvailableDate',NULL,FilterProperty::DATE,'comparis'); // NULL
        $aFilter->setProperty('MinChangeDate',NULL,FilterProperty::DATE,'comparis'); //now
        $aFilter->addProperty(FilterProperty::STRING,'LocationSearchString','comparis');
        $aFilter->setProperty('Sort',3,FilterProperty::INTEGER,'comparis');
        $aFilter->setProperty('HasBalcony',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('HasTerrace',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('HasFireplace',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('HasDishwasher',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('HasWashingMachine',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('HasLift',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('HasParking',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('PetsAllowed',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('MinergieCertified',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('WheelchairAccessible',false,FilterProperty::BOOL,'comparis');
        $aFilter->setProperty('LowerLeftAltitude',NULL,FilterProperty::STRING,'comparis');
        $aFilter->setProperty('LowerLeftLongitude',NULL,FilterProperty::STRING,'comparis');
        $aFilter->setProperty('UpperRightLatitude',NULL,FilterProperty::STRING,'comparis');
        $aFilter->setProperty('UpperRightLongitude',NULL,FilterProperty::STRING,'comparis');
        
        $aFilter->addProperty('TransportDestination','Rämistrasse 101 8006 Zürich',FilterProperty::STRING,'google');
        $aFilter->addProperty('TransportType','transit',FilterProperty::STRING,'google');
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
    function getPTinfo($address,$destination,$mode) {
        $address = preg_replace("/\s/","+",$address);
        $baseURL = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=".$address."&destinations=".$destination."&sensor=false&mode=".$mode."&language=de&units=metric";
        
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
                return preg_replace('/(\r|\f|\e|\n|'.$addss[1].'\s|^\s|\s$)+/','',preg_replace('/\s+/',' ',$row->nodeValue));
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
                    echo "<li><a href='".$place->url."'>".$place->title."</a><br>".$place->address."<br>".getPTinfo($place->address,$filter->getProperty('TransportDestination'),$filter->getProperty('TransportMode'))."</li>";
                }
            ?>
        </ul>
    </body>
</html>
