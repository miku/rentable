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

    // setup application
    $app = new Slim(array(
        'debug' => true,
        'templates.path' => 'templates',
        'log.level' => 4,
        'log.enabled' => true,
        'log.writer' => new DateTimeFileWriter(array(
            'path' => 'logs',
            'name_format' => 'Y-m-d'
        )),        
        'view' => new Twig(),
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

    // routes 
    $app->get('/', function() use ($app) {
        $log = $app->getLog();
        $log->debug("Welcome to rentable 1.0");
        $app->render('index.html');
    })->name('index');

    $app->get('/sync', function () use ($app) {
        $log = $app->getLog();

        // a list of descriptors for the objects
        $descriptors = array();

        // download all worksheets
        for ($i = 0; $i < MAX_SHEETS; $i++) {
            $item = array();
            $item['url'] = "https://spreadsheets.google.com/pub?key=" . 
                SPREADSHEET_KEY . "&gid=" . $i . "&output=csv";
            $item['fgid'] = sprintf("%04d", $i);
            $item['target'] = CACHE_DIR . '/' . SPREADSHEET_KEY . 
                '-' . $item['fgid'] . '.csv';
            $item['id'] = SPREADSHEET_KEY . '-' . $item['fgid'];
            $item['last-access'] = date("c", time());

            if (!file_exists($item['target'])) {
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
            }
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

    $app->get('/locations', function() use ($app) {
        $log = $app->getLog();
        // create OL locations file 
        $rentables = R::getAll('select * from rentable');
        // lat  lon title   description icon    iconSize    iconOffset
        $handle = fopen('cache/locations.tsv', 'w');
        fputcsv($handle, array('lat', 'lon', 'title', 'description', 'icon', 'iconSize', 'iconOffset'), chr(9));
        foreach ($rentables as $rentable) {
            $log->debug($rentable);
            fputcsv($handle, array(
                $rentable['longitude'],                
                $rentable['latitude'], 
                $rentable['description'], 
                $rentable['zipcode'] . ' ' . $rentable['street'],
                'assets/icons/villa.png',
                '37,32',
                '0,0'), chr(9));
        }
        fclose($handle);
        chmod('cache/locations.tsv', 0777);
        $response = $app->response();
        $response->status(200);
        $response->body(file_get_contents('cache/locations.tsv'));
        $response['Content-Type'] = 'text/plain';
        // $app->render('locations.tsv');
    })->name('locations');
    

    // start the app
    $app->run();
?>
