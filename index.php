<?php
    //              _       _   _     
    //  ___ ___ ___| |_ ___| |_| |___ 
    // |  _| -_|   |  _| .'| . | | -_|
    // |_| |___|_|_|_| |__,|___|_|___|
    // 
    // version 1.0

    // configuration #todo make this a properties file
    define('SPREADSHEET_KEY', 
        '0AhlhQsr_yVQ4dFFOTVEtX3hKU0Q2azcyczNUckNybVE');
    define('MAX_SHEETS', 1000);
    define('CACHE_DIR', 'cache');

    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('auto_detect_line_endings', true);    
   
    require_once 'vendor/autoload.php';

    use \Slim\Slim;
    use \Slim\Extras\Views\Twig;
    use \Slim\Extras\Log\DateTimeFileWriter;

    use RedBean_Facade as R;
    // R::setup();
    R::setup('sqlite:db/rentable.db', '', '');

    function startsWith($haystack, $needle)
    {
        return !strncmp($haystack, $needle, strlen($needle));
    }

    $twig = new Twig();
    // setup application
    $app = new Slim(array(
        'debug' => false,
        'templates.path' => 'templates',
        'log.level' => 4,
        'log.enabled' => false,
        'log.writer' => new DateTimeFileWriter(array(
            'path' => 'logs',
            'name_format' => 'Y-m-d'
        )),        
        'view' => $twig,
    ));

    // urlFor fix; usually this should be done with:
    //     Twig::$twigExtensions = array('Twig_Extensions_Slim');
    // but that would not work; #todo
    $env = $app->view()->getEnvironment();
    $fn = new Twig_SimpleFunction('urlFor', function ($name, $params = array(), 
        $appName = 'default') {
            return Slim::getInstance($appName)->urlFor($name, $params);
    });
    $env->addFunction($fn);

    $escaper = new Twig_Extension_Escaper(true);
    $env->addExtension($escaper);

    // helper
    function getCityForZipcode($zipcode, $country = "DE") {
        $url = "http://api.geonames.org/postalCodeLookupJSON?postalcode=" . $zipcode . "&country=" . $country . "&username=cc5geo1";
        $cache_file = 'cache/' . sha1($url);
        if (!file_exists($cache_file)) {
            $ch = curl_init();
            // Set query data here with the URL
            curl_setopt($ch, CURLOPT_URL, $url); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $content = trim(curl_exec($ch));
            curl_close($ch);
            $result = json_decode($content, true);
            if (array_key_exists("postalcodes", $result)) {
                if (count($result["postalcodes"]) > 0) {
                    file_put_contents($cache_file, $result["postalcodes"][0]["placeName"]);
                }
            }              
        }
        if (file_exists($cache_file)) {
            return file_get_contents($cache_file);    
        } else {
            return "...";
        }
    }

    // routes 
    $app->get('/', function() use ($app) {
        $log = $app->getLog();
        $log->debug("Welcome to rentable 1.0");
        $app->render('index.html');
    })->name('index');

    $app->get('/reservations', function() use ($app) {
        $log = $app->getLog();
        $oid = $app->request()->get('oid');
        $result = R::getAll('select * from reservation where oid = :oid', array(":oid" => $oid));
        header("Content-Type: application/json");
        echo json_encode($result);
        exit;        
    })->name("reservations");

    // convert some string to latitude and longitude
    $app->get('/tocoords', function() use ($app) {
        $log = $app->getLog();
        
        $qs = $app->request()->get('q');
        $parts = preg_split('/\s+/', $qs);
        $qs = implode('+', $parts);
        
        $url = "http://api.geonames.org/searchJSON?q=" . $qs . "&maxRows=10&username=cc5geo1";
        $log->debug("URL:" . $url);
        $cache_file = 'cache/' . sha1($url);
        if (!file_exists($cache_file)) {
            $ch = curl_init();
            // Set query data here with the URL
            curl_setopt($ch, CURLOPT_URL, $url); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $content = trim(curl_exec($ch));
            curl_close($ch);
            file_put_contents($cache_file, $content);
        }
        $result = array();
        if (file_exists($cache_file)) {
            $content = json_decode(file_get_contents($cache_file), true);
            if (array_key_exists("geonames", $content)) {
                if (count($content["geonames"]) > 0) {
                    $result["latitude"] = $content["geonames"][0]["lat"];
                    $result["longitude"] = $content["geonames"][0]["lng"];
                }
            }
        } 
        header("Content-Type: application/json");
        echo json_encode($result);
        exit;        
    })->name("tocoords");

    $app->get('/count', function() use ($app) {
        $rentables = R::getAll('select * from rentable');
        header("Content-Type: application/json");
        echo json_encode(array("count" => count($rentables)));
        exit;                
    })->name("count");

    $app->get('/sync', function () use ($app) {
        $log = $app->getLog();

        // a list of descriptors for the objects
        $descriptors = array();
        // download all worksheets
        for ($i = 0; $i < MAX_SHEETS; $i++) {
            $item = array();
            $item['ccc'] = "https://spreadsheets.google.com/ccc?key=" . 
                SPREADSHEET_KEY . "#gid=" . $i;
            $item['url'] = "https://spreadsheets.google.com/pub?key=" . 
                SPREADSHEET_KEY . "&gid=" . $i . "&output=csv";
            $item['fgid'] = sprintf("%04d", $i);
            $item['target'] = CACHE_DIR . '/' . SPREADSHEET_KEY . 
                '-' . $item['fgid'] . '.csv';
            $item['id'] = SPREADSHEET_KEY . '-' . $item['fgid'];
            $item['last-access'] = date("c", time());

            // if (!file_exists($item['target'])) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $item['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            curl_setopt($ch, CURLOPT_FAILONERROR, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            $serialized = curl_exec($ch);

            // check the content type of the response, 
            // whether we downloaded all worksheets
            $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            if ($content_type != 'text/csv') {
                $log->debug('no text/csv reponse, stopping at ' . $i);
                break;
            }  else {
                file_put_contents($item['target'], $serialized);
                chmod($item['target'], 0664);
                $log->debug($item['target'] . ' downloaded');
            }
            // }
                
            array_push($descriptors, $item);
        }
        // dump the descriptors
        file_put_contents("cache/descriptors.json", json_encode($descriptors));

        // delete all rentables and reservations
        R::wipe('rentable');
        R::wipe('reservation');

        // update rentables and reservations
        foreach ($descriptors as $item) {
            $rentable = R::dispense('rentable');
            $rentable->oid = $item['id'];
            $rentable->ccc = $item['ccc'];
            // read CSV data into SQL
            if (($handle = fopen($item['target'], "r")) !== FALSE) {
                while (($line = fgetcsv($handle)) !== FALSE) {
                    if (startsWith($line[0], "Bezeichnung")) {
                        $rentable->description = $line[1];
                    } elseif (startsWith($line[0], "Straße")) {
                        $rentable->street = $line[1];
                    } elseif (startsWith($line[0], "PLZ")) {
                        $rentable->zipcode = $line[1];
                    } elseif (startsWith($line[0], "Lage")) {
                        $position = explode(",", $line[1]);
                        $rentable->longitude = $position[0];
                        $rentable->latitude = $position[1];
                    } elseif (preg_match('/[0-9]{2,2}.[0-9]{2,2}.[0-9]{4,4}/', $line[0])) {
                        $date = DateTime::createFromFormat('d.m.Y', 
                            trim($line[0]))->format('Y-m-d');
                        $reserved = (trim($line[1]) == 'x');
                        if ($reserved) {
                            $reservation = 
                                R::getRow('select * from reservation where oid = :oid and date = :date limit 1', 
                                    array(":oid" => $item['id'], ":date" => $date));    
                            if ($reservation == null) {
                                $reservation = R::dispense('reservation');
                                $reservation->oid = $item['id'];
                                $reservation->date = $date;
                                $id = R::store($reservation);
                                $log->debug('added reservation ' . $item['id'] . ", " . $date);
                            }
                        }
                    }
                }
                R::store($rentable);
                $log->debug('added rentable object: ' . $item['id']);
            } else {
                $log->error("could not read: " . $item['target']);
            }
            $log->debug($rentable);
        }
        $app->redirect($app->urlFor('index'));
    })->name('sync');


    $app->get('/geojson', function() use ($app, $env) {
        $log = $app->getLog();
        $rentables = R::getAll('select * from rentable');
        $result = array();
        foreach ($rentables as $rentable) {
            $item = array();
            $item["type"] = "Feature";
            $item["properties"] = array();
            $item["properties"]["description"] = $rentable["description"];
            $item["properties"]["oid"] = $rentable["oid"];

            // date_parse_from_format("Y-m-d", $date)
            $today = R::getAll('select * from reservation where oid = :oid and date = :date', 
                array(":oid" => $rentable["oid"], "date" => date("Y-m-d")));
            $available_today = (count($today) == 0 ? 'yes' : 'no');
            $item["properties"]["available_today"] = "<span class='available-" . $available_today . "'>". $available_today . "</span>";

            $tomorrow = R::getAll('select * from reservation where oid = :oid and date = :date', 
                array(":oid" => $rentable["oid"], "date" => date('Y-m-d', strtotime(date("Y-m-d") . ' + 1 day'))));
            $available_tomorrow = (count($tomorrow) == 0 ? 'yes' : 'no');
            $item["properties"]["available_tomorrow"] = "<span class='available-" . $available_tomorrow . "'>". $available_tomorrow . "</span>";

            // counters
            $available_next_10_days = 0;
            for ($i=1; $i < 11; $i++) { 

                $rsv = R::getAll('select * from reservation where oid = :oid and date = :date', 
                    array(":oid" => $rentable["oid"], "date" => date('Y-m-d', strtotime(date("Y-m-d") . ' + ' . $i . ' day'))));
                if (count($rsv) == 0) {
                    $available_next_10_days += 1;
                }
            }
            if ($available_next_10_days == 0) {
                $item["properties"]["available_next_10_days"] = "<span class='available-no'>no</span>";
            } elseif ($available_next_10_days == 10) {
                $item["properties"]["available_next_10_days"] = "<span class='available-yes'>yes</span>";
            } else {
                $item["properties"]["available_next_10_days"] = "<span class='available-maybe'>" . $available_next_10_days . "/10" . "</span>";
            }

            $log->debug(json_encode($item));

            $item["properties"]["zipcode"] = $rentable["zipcode"];
            $item["properties"]["street"] = $rentable["street"];
            $item["properties"]["styled"] = $env->render('marker.html', 
                array("description" => $rentable["description"], 
                    "zipcode" => $rentable["zipcode"],
                    "city" => getCityForZipcode($rentable["zipcode"]),
                    "street" => $rentable["street"],
                    "available_today" => $item["properties"]["available_today"],
                    "available_tomorrow" => $item["properties"]["available_tomorrow"],
                    "available_next_10_days" => $item["properties"]["available_next_10_days"],
                    "google_docs_url" => $rentable["ccc"]
                ));
            $log->debug($item["properties"]["styled"]);
            $item["geometry"] = array();
            $item["geometry"]["type"] = "Point";
            $item["geometry"]["coordinates"] = array($rentable["latitude"], $rentable["longitude"]);
            array_push($result, $item);
        }
        header("Content-Type: application/json");
        echo json_encode($result);
        exit;        
    })->name("geojson");

    // start the app
    $app->run();
?>
