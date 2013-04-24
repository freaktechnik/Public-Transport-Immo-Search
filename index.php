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
        $filter->setProperty(ucfirst($name),getParam($name,$filter));
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
    
    // set all the possible values to their default
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
        $aFilter->setProperty('MinChangeDate','today',FilterProperty::DATE,'comparis'); //now?
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
        
        $aFilter->setProperty('TransportDestination','Rämistrasse 101 8006 Zürich',FilterProperty::STRING,'google');
        $aFilter->setProperty('TransportType','transit',FilterProperty::STRING,'google');
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
                    $results[$j]->title = utf8_decode(getDescriptionLink($node)->nodeValue);
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
    function getParam($key,$filter) {
        $type = $filter->getProperty(ucfirst($key))->getType();
        $value = $_GET[$key];
        switch($type) {
            case FilterProperty::DATE:  return strtotime($value);
            case FilterProperty::ARR:   return array((int)$value);
            case FilterProperty::BOOL:  return $value==='true';
            case FilterProperty::INTEGER:   return (int)$value;
            case FilterProperty::FLOAT: return (float)$value;
            default: return $value;
        }
    }
    
    // load time from google
    function getPTinfo($address,$destination,$mode) {
        $address = preg_replace("/\s/","+",$address);
        $baseURL = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=".urlencode($address)."&destinations=".urlencode($destination)."&sensor=false&mode=".$mode."&language=de&units=metric";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $json = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($json);
        return $data->rows[0]->elements[0]->duration->text;
    }
    
    function getUPCinfo($address) {
        $values = preg_split('/\h+/',$address);
        $length = count($values);
        for($i = $length;$i>=0;$i--) {
            if(preg_match('/\d+/i',$values[$i])) {
                $plz = $values[$i];
                break;
            }
        }
        $values = preg_split('/\h*'.$plz.'\h*/i',$address);
        
        $place = $values[1];
        
        preg_match('/(\d*||\d+[a-z]*)$/i',$values[0],$number);
        $street = preg_replace('/\h*'.$number[0].'\h*/i','',$values[0]);
        $baseURL = 'http://www.upc-cablecom.ch/content/www-upc-cablecom-ch/config.services.aav.html?vtmission=sendrequest&language=de&strasse='.urlencode($street).'&nummer='.urlencode($number[0]).'&hispeedOrt='.urlencode($plz.' '.$place);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $json = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($json);
        
        return $data->result->aav_ifp150==='true'||$data->state==='select';
    }
    
    // extract address info from an ads cell
    function getAddressString($node) {
        foreach($node->getElementsByTagName('td') as $row) {
            if($row->getAttribute('valign')=='top'&&$row->getAttribute('align')=='left') {
                $ad = utf8_decode($row->nodeValue);
                $addss = preg_split('/\s+/',$ad);
                return utf8_decode(preg_replace('/(\r|\f|\e|\n|'.$addss[1].'\s|^\s|\s$)+/','',preg_replace('/\s+/',' ',$ad)));
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
            <input type="radio" name="dealType" value="10" id="rent" <?php if($_GET['dealType']!=='20') echo 'checked';?>><label for="rent"> mieten</label> <input type="radio" name="dealType" value="20" id="buy" <?php if($_GET['dealType']==='20') echo 'checked';?>><label for="buy"> kaufen</label>
            <label for="locationSearchString">Ort oder PLZ </label><input required type="text" id="locationSearchString" name="locationSearchString" value="<?php echo $_GET['locationSearchString']; ?>">
            <label for="radius">Umkreis </label><input type="range" min="0" max="20" step="1" id="radius" name="radius" <?php if(isset($_GET['radius'])) echo 'value="'.$_GET['radius'].'"'; ?>>
            <label for="type">Objektart </label><select id="type" name="rootPropertyTypes">
                <option value="0" <?php if($_GET['rootPropertyTypes']==='0') echo 'selected'; ?>>egal</option>
                <option value="1" <?php if($_GET['rootPropertyTypes']==='1') echo 'selected'; ?>>Whonung</option>
                <option value="2" <?php if($_GET['rootPropertyTypes']==='2') echo 'selected'; ?>>M&ouml;blierte Wohnung</option>
                <option value="3" <?php if($_GET['rootPropertyTypes']==='3') echo 'selected'; ?>>WG-Zimmer</option>
                <option value="4" <?php if($_GET['rootPropertyTypes']==='4') echo 'selected'; ?>>Einfamilienhaus</option>
                <option value="5" <?php if($_GET['rootPropertyTypes']==='5') echo 'selected'; ?>>Mehrfamilienhaus</option>
                <option value="6" <?php if($_GET['rootPropertyTypes']==='6') echo 'selected'; ?>>Ferienimmobilie</option>
                <option value="7" <?php if($_GET['rootPropertyTypes']==='7') echo 'selected'; ?>>Grundstück</option>
                <option value="8" <?php if($_GET['rootPropertyTypes']==='8') echo 'selected'; ?>>Parkplatz, Garage</option>
                <option value="9" <?php if($_GET['rootPropertyTypes']==='9') echo 'selected'; ?>>Gewerberaum</option>
                <option value="26" <?php if($_GET['rootPropertyTypes']==='26') echo 'selected'; ?>>Bastelraum</option>
                <option value="10" <?php if($_GET['rootPropertyTypes']==='10') echo 'selected'; ?>>Diverses</option>
            </select>
            <label for="roomsFrom">Zimmer </label><input type="number" id="roomsFrom" name="roomsFrom" <?php if(isset($_GET['roomsFrom'])) echo 'value="'.$_GET['roomsFrom'].'"'; ?>> bis <input type="number" id="roomsTo" name="roomsTo" <?php if(isset($_GET['roomsTo'])) echo 'value="'.$_GET['roomsTo'].'"'; ?>>
            <label for="priceFrom">Preis </label><input type="number" id="priceFrom" name="priceFrom" <?php if(isset($_GET['priceFrom'])) echo 'value="'.$_GET['priceFrom'].'"'; ?>> CHF bis <input type="number" id="priceTo" name="priceTo" <?php if(isset($_GET['priceTo'])) echo 'value="'.$_GET['priceTo'].'"'; ?>> CHF
            <label for="livingSpaceFrom">Wohnfl&auml;che </label><input type="number" id="livingSpaceFrom" name="livingSpaceFrom" <?php if(isset($_GET['livingSpaceFrom'])) echo 'value="'.$_GET['livingSpaceFrom'].'"'; ?>>m<sup>2</sup> bis <input type="number" id="livingSpaceTo" name="livingSpaceTo" <?php if(isset($_GET['livingSpaceTo'])) echo 'value="'.$_GET['livingSpaceTo'].'"'; ?>>m<sup>2</sup>
            <label for="minAvailableDate">Einzug ab (YYYY-mm)</label><input type="month" id="minAvailableDate" name="minAvailableDate" getParam>
            <label for="adAgeMax">Inserat j&uuml;nger als</label><input id="adAgeMax" name="adAgeMax" type="number" pattern="[0-2]?[0-9]||30" value="<?php if(isset($_GET['adAgeMax'])) echo $_GET['adAgeMax']; else echo '0'; ?>"> Tage
            <label for="comparisRank">Mindest Comparis-Note</label><input name="comparisPointsMin" id="comparisRank" type="range" min="0" step="1" max="6" value="<?php if(isset($_GET['comparisPointsMin'])) echo $_GET['comparisPointsMin']; else echo '0'; ?>">
            <input type="checkbox" name="hasBalcony" id="hasBalcony" value="true" <?php if($_GET['hasBalcony']=='true') echo 'checked'; ?>><label for="hasBalcony"> mit Balkon</label>
            <input type="checkbox" name="hasTerace" id="hasTerace" value="true" <?php if($_GET['hasTerace']=='true') echo 'checked'; ?>><label for="hasTerace"> mit Terasse</label>
            <input type="checkbox" name="hasWashingMachine" id="hasWashingMachine" value="true" <?php if($_GET['hasWashingMachine']==='true') echo 'checked'; ?>><label for="hasWashingMachine"> mit Waschmaschine</label>
            <input type="checkbox" name="hasLift" id="hasLift" value="true" <?php if($_GET['hasLift']==='true') echo 'checked'; ?>><label for="hasLift"> mit Lift</label>
            <input type="checkbox" name="hasParking" id="hasParking" value="true" <?php if($_GET['hasParking']==='true') echo 'checked'; ?>><label for="hasParking"> mit Parkplatz</label>
            <input type="checkbox" name="petsAllowed" id="petsAllowed" value="true" <?php if($_GET['petsAllowed']==='true') echo 'checked'; ?>><label for="petsAllowed"> Haustiere erlaubt</label>
            <input type="checkbox" name="minergieCertified" id="minergieCertified" value="true" <?php if($_GET['minergieCertified']==='true') echo 'checked'; ?>><label for="minergieCertified"> Minerdie-zertifiziert</label>
            <input type="checkbox" name="WheelchairAccessible" id="WheelchairAccessible" value="true" <?php if($_GET['WheelchairAccessible']==='true') echo 'checked'; ?>><label for="WheelchairAccessible"> Rollstuhlg&auml;ngig</label>
            <input type="checkbox" name="hasFireplace" id="hasFireplace" value="true" <?php if($_GET['hasFireplace']==='true') echo 'checked'; ?>><label for="hasFireplace"> mit Kamin</label>
            <label for="keywords">Inserattext Suche </label><input type="text" id="keywords" name="keyword" <?php if(isset($_GET['keyword'])) echo 'value="'.$_GET['keyword'].'"'; ?>>
            <input type="checkbox" name="withImagesOnly" id="withImagesOnly" value="true" <?php if($_GET['withImagesOnly']==='true') echo 'checked'; ?>><label for="withImagesOnly"> Nur Inserate mit Bildern</label>
            
            <label for="destination">Reiseziel </label><input type="text" id="destination" id="transportDestination" <?php if(isset($_GET['transportDestination'])) echo 'value="'.$_GET['transportDestination'].'"'; ?>>
            <label for="transportType">Verkehrsmittel </label><select id="transportType" name="transportType">
                <option value="transit" <?php if($_GET['transportType']==='transit'||!isset($_GET['transportType'])) echo 'selected'; ?>>&ouml;V</option>
                <option value="driving" <?php if($_GET['transportType']==='driving') echo 'selected'; ?>>Auto</option>
                <option value="bicycling" <?php if($_GET['transportType']==='bicycling') echo 'selected'; ?>>Velo</option>
                <option value="walking" <?php if($_GET['transportType']==='walking') echo 'selected'; ?>>zu Fuss</option>
            </select>
            
            <input type="submit" value="Suchen">
        </form>
        <ul>
            <?php
                foreach($places as $place) {
                    echo "<li><a href='".$place->url."'>".htmlentities($place->title)."</a><br>".htmlentities($place->address)."<br>".getPTinfo($place->address,$filter->getProperty('TransportDestination'),$filter->getProperty('TransportMode'))."; UPC-Highspeed: ".(getUPCinfo($place->address)?'Ja':'Nein')."</li>";
                }
            ?>
        </ul>
    </body>
</html>
