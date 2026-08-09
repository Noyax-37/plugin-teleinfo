<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class teleinfo extends eqLogic
{

    public static function getTeleinfoInfo($_url)
    {
//        $returnSerial = self::deamon_infoSerial();
//        $returnMqtt = self::deamon_infoMqtt();
//        if ($returnSerial['state'] != 'ok' && $returnMqtt['state'] != 'ok') {
        $return = self::deamon_info();
        if ($return['state'] != 'ok'){
            return "";
        }
    }

    public static function cron()
    {
        self::calculatePAPP();
//        self::calculateOtherStats();
//        self::calculateTodayStats();
    }

    public static function cronHourly()
    {
        self::moyLastHour();
        cache::set('teleinfo::regenerateMonthlyStat', '0');
        log::add('teleinfo', 'debug', 'cronhourly ');
    }

    // Fonction pour exclure un sous répertoire de la sauvegarde
    public static function backupExclude() {
		return ['resources/venv'];
	}

    public static function changeLogLive($level)
    {
        $activation_Modem = (config::byKey('activation_Modem', 'teleinfo') == "") ? 1 : config::byKey('activation_Modem', 'teleinfo');
        $activation_Mqtt = (config::byKey('activation_Mqtt', 'teleinfo') == "") ? 0 : config::byKey('activation_Mqtt', 'teleinfo');
        $productionActivated = (config::byKey('port_modem2', 'teleinfo') == "") ? 0 : config::byKey('port_modem2', 'teleinfo');
        if (($activation_Modem=='0') && ($activation_Mqtt=='0')) {
            log::add('teleinfo', 'info', __('pas d envoi de message faute de configuration', __FILE__));
            return false;
        }
        sleep(1); // attend que le level ait eu le temps de s'écrire dans la bdd
        $value['cmd'] = 'changelog';
        $value['level'] = log::convertLogLevel(log::getLogLevel('teleinfo'));
        $socketport = config::byKey('socketport', __CLASS__, '55062');
        $value['apikey'] = jeedom::getApiKey(__CLASS__);
        if ($activation_Modem==1 && $productionActivated == 0){
            self::sendToDaemon($value,'serial', $socketport);
        }
        if ($activation_Mqtt==1){
            // $value = json_encode($value);
            self::sendToDaemon($value,'mqtt', $socketport + 2);
        }
        if ($activation_Modem==1 && $productionActivated == 1) {
            self::sendToDaemon($value,'prod', $socketport + 1);
        }
    }

    public static function sendToDaemon($params,$mode,$socketport) { // le mode peut être serial, mqtt ou prod
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] != 'ok') {
            throw new Exception(sprintf(__("Le démon %s n'est pas démarré", __FILE__), $mode));
        }
        $params['apikey'] = jeedom::getApiKey('teleinfo');
        $payLoad = json_encode($params);
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        socket_connect($socket, config::byKey('sockethost', 'teleinfo', '127.0.0.1'), $socketport);
        socket_write($socket, $payLoad, strlen($payLoad));
        socket_close($socket);
        return true;
    }

	/**
	 * Test si la version est béta
	 * @param bool $text
	 * @return $isBeta
	 */
    public static function isBeta($text = false) {
        $plugin = plugin::byId('teleinfo');
        $update = $plugin->getUpdate();
        $isBeta = false;
        if (is_object($update)) {
            $version = $update->getConfiguration('version');
            $isBeta = ($version && $version != 'stable');
        }
    
        if ($text) {
          return $isBeta ? 'beta' : 'stable';
        }
        return $isBeta;
      }
    
	/**
	 * Creation objet sur reception de trame
	 * @param string $adco
	 * @return eqLogic
	 */
    public static function createFromDef(string $adco)
    {
        $color = ['#D62828','#001219','#005F73','#0A9396','#94D2BD',
                    '#E9D8A6','#ee9b00','#ca6702','#bb3e03','#ae2012',
                    '#9b2226','#ed9448','#7cb5ec','#d62828','#00FF00'];
        $autorisationCreationObjet = config::byKey('createNewADCO', 'teleinfo');
        if ($autorisationCreationObjet != 1) {
            $teleinfo = teleinfo::byLogicalId($adco, 'teleinfo');
            if (!is_object($teleinfo)) {
                $eqLogic = (new teleinfo())
                        ->setName($adco);
            }
            $eqLogic->setLogicalId($adco)
                    ->setEqType_name('teleinfo')
                    ->setIsEnable(1)
                    ->setIsVisible(1)
                    ->setconfiguration('AutoCreateFromCompteur','1')
                    ->setconfiguration('color0',$color[0])
                    ->setconfiguration('color1',$color[1])
                    ->setconfiguration('color2',$color[2])
                    ->setconfiguration('color3',$color[3])
                    ->setconfiguration('color4',$color[4])
                    ->setconfiguration('color5',$color[5])
                    ->setconfiguration('color6',$color[6])
                    ->setconfiguration('color7',$color[7])
                    ->setconfiguration('color8',$color[8])
                    ->setconfiguration('color9',$color[9])
                    ->setconfiguration('color10',$color[10])
                    ->setconfiguration('color11',$color[11])
                    ->setconfiguration('color12',$color[12])
                    ->setconfiguration('color13',$color[13])
                    ->setconfiguration('color14',$color[14]);
            $eqLogic->save();
            return $eqLogic;
        } else {
            return null;
        }
    }
	/**
	 * Creation commande sur reception de trame
	 * @param $oADCO identifiant compteur
	 * @param $oKey etiquette
	 * @param $oValue valeur
	 * @return Commande
	 */

     public static function createCmdFromRest($teleinfo, $oKey){
        log::add('teleinfo', 'info', sprintf(__('création de la commande %s pour le compteur', __FILE__), $oKey) . ' ' . $teleinfo->getName());
        // On vérifie si la commande existe
        $cmd = $teleinfo->getCmd('info', $oKey);
        if (is_object($cmd)) return $cmd;

        $cmd = (new teleinfoCmd())
            ->setName($oKey)
            ->setLogicalId($oKey)
            ->setType('info')
            ->setSubType('numeric')
            ->setDisplay('generic_type', 'GENERIC_INFO');
        $cmd->setEqLogic_id($teleinfo->id);
        $cmd->setConfiguration('info_conso', $oKey);
        $cmd->setIsHistorized(1)
            ->setIsVisible(1);
        $cmd->save();
        return $cmd;
     }

    public static function createCmdFromDef($oADCO, $oKey, $oValue)
    {
        if (!isset($oKey) || !isset($oADCO)) {
            log::add('teleinfo', 'error', __('[TELEINFO]-----Information manquante pour ajouter l\'équipement :', __FILE__) . ' ' . print_r($oKey, true) . ' ' . print_r($oADCO, true));
            return false;
        }
        $teleinfo = teleinfo::byLogicalId($oADCO, 'teleinfo');
        if (!is_object($teleinfo)) {
            return false;
        }
        if ($teleinfo->getConfiguration('AutoCreateFromCompteur') == '1') {
            log::add('teleinfo', 'info', sprintf(__('Création de la commande %s sur l\'ADCO', __FILE__), $oKey) . ' ' . $oADCO);
            $cmd = (new teleinfoCmd())
                    ->setName($oKey)
                    ->setLogicalId($oKey)
                    ->setType('info');
            switch ($oKey) {
				case "ADSC":
                case "OPTARIF":
                case "PTEC":
                case "DEMAIN":
                case "MOTDETAT":
                case "HPHC":
                case "PPOT":
                case "NGTF":
                case "LTARF":
                case "STGE":
                case "STGE01":
                case "STGE02":
                case "STGE03":
                case "STGE04":
                case "STGE05":
                case "STGE06":
                case "STGE07":
                case "STGE08":
                case "STGE09":
                case "STGE10":
                case "STGE11":
                case "STGE12":
                case "STGE13":
                case "STGE14":
                case "STGE15":
                case "STGE16":
                case "STGE17":
                case "STGE18":
                case "STGE19":
                case "STGE20":
                case "DPM1":
                case "FPM1":
                case "DPM2":
                case "FPM2":
                case "DPM3":
                case "FPM3":
                case "MSG1":
                case "MSG2":
                case "PRM":
                case "NJOURF":
                case "NJOURF+1":
                case "PJOURF+1":
                case "PPOINTE":
                case "RELAIS":
                case "RELAIS01":
                case "RELAIS02":
                case "RELAIS03":
                case "RELAIS04":
                case "RELAIS05":
                case "RELAIS06":
                case "RELAIS07":
                case "RELAIS08":
                case "VTIC":
                    $cmd->setSubType('string')
                            ->setDisplay('generic_type', 'GENERIC_INFO');
                    break;
                default:
                    $cmd->setSubType('numeric')
                            ->setDisplay('generic_type', 'GENERIC_INFO');
                    break;
            }
			$cmd->setEqLogic_id($teleinfo->id);
            $cmd->setConfiguration('info_conso', $oKey);
            $cmd->setIsHistorized(1)->setIsVisible(1);
            $cmd->save();
            $cmd->event($oValue);
            return $cmd;
        }
    }

	/**
	 * Fonction de détection du type de compteur
	 * @param $port
	 * @return $return
	 */
    public static function findModemType(string $port, string $type)
    {
        $return = [
            'state'   => 'nok',
            'message' => '',
            'type'    => '',
            'vitesse' => '',
            'linky'   => false
        ];

        // Cas particulier modem 2 compteurs Cartelectronic
        if (config::byKey('2cpt_cartelectronic', 'teleinfo') == 1) {
            $return['message'] = __('Non disponible pour le modem 2 compteurs. Veuillez configurer manuellement dans la zone Configuration avancée.', __FILE__);
            return $return;
        }

        if ($type === 'usb') {
            $port = jeedom::getUsbMapping($port);
        }

        if (empty($port) || !file_exists($port)) {
            $return['message'] = __('Port série introuvable ou inaccessible.', __FILE__);
            return $return;
        }

        // Vérification si le port est déjà utilisé par le démon
        exec("fuser " . escapeshellarg($port) . " 2>&1", $fuserOutput, $fuserReturn);
        if ($fuserReturn === 0) {
            $return['message'] = __('Le port est déjà utilisé. Veuillez arrêter le démon Teleinfo avant de lancer la détection.', __FILE__);
            return $return;
        }

        log::add('teleinfo', 'debug', "Détection du type de compteur sur le port : " . $port);

        // =============================================
        // TEST 1 : MODE STANDARD / LINKY (9600 bauds)
        // =============================================
        exec('stty -F ' . escapeshellarg($port) . ' 9600 sane evenp parenb cs7 -crtscts 2>&1');
        exec("timeout 0.5s cat " . escapeshellarg($port) . " > /dev/null 2>&1"); // flush buffer

        $output = shell_exec('timeout 4s cat ' . escapeshellarg($port) . ' 2>/dev/null | tr -d "\0" | head -c 600');

        log::add('teleinfo', 'debug', "Test 9600 baud - Retour brut : " . trim($output));

        if (strpos($output, 'ADSC') !== false || strpos($output, 'DATE') !== false || strpos($output, 'ADCO') !== false) {
            $return['state']   = 'ok';
            $return['type']    = 'standard';
            $return['linky']   = true;
            $return['vitesse'] = '9600';
            $return['message'] = __('Détection réussie : Mode STANDARD / Linky (9600 baud).', __FILE__);
            return $return;
        }

        // =============================================
        // TEST 2 : MODE HISTORIQUE (1200 bauds)
        // =============================================
        exec('stty -F ' . escapeshellarg($port) . ' 1200 sane evenp parenb cs7 -crtscts 2>&1');
        exec("timeout 0.5s cat " . escapeshellarg($port) . " > /dev/null 2>&1"); // flush

        $output = shell_exec('timeout 5s cat ' . escapeshellarg($port) . ' 2>/dev/null | tr -d "\0" | head -c 600');

        log::add('teleinfo', 'debug', "Test 1200 baud - Retour brut : " . trim($output));

        if (strpos($output, 'ADCO') !== false) {
            $return['state']   = 'ok';
            $return['type']    = 'historique';
            $return['linky']   = false;
            $return['vitesse'] = '1200';
            $return['message'] = __('Détection réussie : Mode HISTORIQUE (1200 baud).', __FILE__);
            return $return;
        }

        // Échec
        $return['message'] = __('Impossible de détecter des données valides (ADCO/ADSC/DATE). Vérifiez le câblage et que le démon est arrêté.', __FILE__);

        log::add('teleinfo', 'warning', "Détection échouée sur " . $port . " (ni 9600 ni 1200 n'a donné de trames valides)");

        return $return;
    }

	/**
     *
     * @param type $debug
     * @param type $type
     * @return boolean
     */
    public static function runDeamon($debug = false, $type = 'conso', $mqtt = false)
    {
        $pidFile = jeedom::getTmpFolder('teleinfo') . '/teleinfo';
        $teleinfoPath         	  = realpath(dirname(__FILE__) . '/../../resources');
        $activation_Modem = (config::byKey('activation_Modem', 'teleinfo') == "") ? 1 : config::byKey('activation_Modem', 'teleinfo');
        if ($activation_Modem==''){
            $activation_Modem = 1;
            log::add('teleinfo', 'info', '---------- Activation Modem 1---------');
        }
        log::add('teleinfo', 'info', '[' . $type . __('] Démarrage daemon', __FILE__));

        if ($type!='rien'){
            log::add('teleinfo', 'info', '[' . $type . __('] Démarrage compteur', __FILE__));
            if ($type == 'conso') {
                $twoCptCartelectronic = config::byKey('2cpt_cartelectronic', 'teleinfo');
                $linky                = config::byKey('linky', 'teleinfo');
                $modemVitesse         = config::byKey('modem_vitesse', 'teleinfo');
                $socketPort			  = config::byKey('socketport', 'teleinfo', '55062');
                if (config::byKey('port', 'teleinfo') == "serie") {
                    $port = config::byKey('modem_serie_addr', 'teleinfo');
                }
                else {
                    $port = jeedom::getUsbMapping(config::byKey('port', 'teleinfo'));
                    if ($twoCptCartelectronic == 1) {
                        $port = '/dev/ttyUSB1';
                    } else {
                        if (is_string($port)) {
                            if (!file_exists($port)) {
                                log::add('teleinfo', 'error', '[TELEINFO]-----[' . $type . sprintf(__('] Le port1 %s n\'existe pas', __FILE__), $port));
                                return false;
                            }
                        } else {
                            log::add('teleinfo', 'error', '[TELEINFO]-----[' . $type . __('] Le port1 n\'est pas configuré', __FILE__));
                            log::add('teleinfo', 'error', sprintf(__('[TELEINFO]----- Le démon %s ne sera pas lancé', __FILE__), $type));
                            return false;
                        }
                    }
                }
            }
            if ($type == 'prod') {
                $twoCptCartelectronic = config::byKey('2cpt_cartelectronic_production', 'teleinfo');
                $linky                = config::byKey('linky_prod', 'teleinfo');
                $modemVitesse         = config::byKey('modem_compteur2_vitesse', 'teleinfo');
                $socketPort			  = config::byKey('socketport', 'teleinfo', '55062') + 1;
                if (config::byKey('port_modem2', 'teleinfo') == "serie") {
                    $port = config::byKey('modem_serie_compteur2_addr', 'teleinfo');
                } else {
                    $port = jeedom::getUsbMapping(config::byKey('port_modem2', 'teleinfo'));
                    if ($twoCptCartelectronic == 1) {
                        $port = '/dev/ttyUSB1';
                    } else {
                        if (is_string($port)) {
                            if (!file_exists($port)) {
                                log::add('teleinfo', 'error', '[TELEINFO]-----[' . $type . sprintf(__('] Le port2 %s n\'existe pas', __FILE__), $port));
                                return false;
                            }
                        } else {
                            log::add('teleinfo', 'error', '[TELEINFO]-----[' . $type . __('] Le port2 n\'est pas configuré', __FILE__));
                            log::add('teleinfo', 'error', sprintf(__('[TELEINFO]----- Le démon %s ne sera pas lancé', __FILE__), $type));
                            return false;
                        }
                    }
                }
            }
            if ($linky == 1) {
                $mode = 'standard';
                if ($modemVitesse == "") {
                    $modemVitesse = '9600';
                }
            } else {
                $mode = 'historique';
                if ($modemVitesse == "") {
                    $modemVitesse = '1200';
                }
            }

            exec('sudo chmod 777 ' . (string)$port . ' > /dev/null 2>&1');


            log::add('teleinfo', 'info', __('---------- Informations de lancement ---------', __FILE__));
            log::add('teleinfo', 'info', __('Port modem : ', __FILE__) . (string)$port);
            log::add('teleinfo', 'info', 'Socket : ' . $socketPort);
            log::add('teleinfo', 'info', 'Type : ' . $type);
            log::add('teleinfo', 'info', 'Mode : ' . $mode);
            log::add('teleinfo', 'info', '---------------------------------------------');

            if ($twoCptCartelectronic == 1) {
                log::add('teleinfo', 'info', '[' . $type . __('] Fonctionnement en mode 2 compteur', __FILE__));
                $cmd          = 'sudo nice -n 19 ' . $teleinfoPath . '/venv/bin/python3 ' . $teleinfoPath . '/teleinfo_2_cpt.py';
                //$cmd          = 'sudo nice -n 19 /usr/bin/python3 ' . $teleinfoPath . '/teleinfo_2_cpt.py';
            }
            else {
                log::add('teleinfo', 'info', '[' . $type . __('] Fonctionnement en mode 1 compteur', __FILE__));
                $cmd          = 'nice -n 19 ' . $teleinfoPath . '/venv/bin/python3 ' . $teleinfoPath . '/teleinfo.py';
                //$cmd          = 'nice -n 19 /usr/bin/python3 ' . $teleinfoPath . '/teleinfo.py';
                $cmd         .= ' --type ' . $type;
            }
            $cmd         .= ' --port ' . (string)$port;
            $cmd         .= ' --vitesse ' . $modemVitesse;
            $cmd         .= ' --apikey ' . jeedom::getApiKey('teleinfo');
            $cmd         .= ' --mode ' . $mode;
            $cmd         .= ' --socketport ' . $socketPort;
            $cmd         .= ' --cycle ' . config::byKey('cycle', 'teleinfo','0.3');
            $cmd         .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/teleinfo/core/php/jeeTeleinfo.php';
            $cmd         .= ' --loglevel '. log::convertLogLevel(log::getLogLevel(__CLASS__));
            $cmd         .= ' --cyclesommeil ' . config::byKey('cycle_sommeil', 'teleinfo', '0.5');
            $cmd         .= ' --pidfile '. $pidFile;

            log::add('teleinfo', 'info', '[' . $type . __('] Exécution du service :', __FILE__) . ' ' . $cmd);
            $result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('teleinfo_deamon_' . $type) . ' 2>&1 &');
            if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
                log::add('teleinfo', 'error', '[TELEINFO]-----' . $result);
                return false;
            }
            sleep(2);
            if (!self::deamonRunning('')) {
                sleep(10);
                if (!self::deamonRunning('')) {
                    log::add('teleinfo', 'error', '[TELEINFO_' . $type . __('] Impossible de lancer le démon téléinfo, vérifiez la configuration.', __FILE__), 'unableStartDeamon');
                    return false;
                }
            }
            message::removeAll('teleinfo', 'unableStartDeamon');
            log::add('teleinfo', 'info', '[' . $type . '] Service OK');
            log::add('teleinfo', 'info', '---------------------------------------------');
        }
    }
    
    public static function runDeamonMqtt($debug = false, $type = 'mqtt'){
  
        $teleinfoPath   = realpath(dirname(__FILE__) . '/../../resources');
        $socketPort 	= config::byKey('socketport', 'teleinfo', '55062') + 2;
        $socketHost 	= config::byKey('socketHost', 'teleinfo', '127.0.0.1');
        $mqtt_broker 	= config::byKey('mqtt_broker', 'teleinfo', '127.0.0.1');
        $mqtt_port 	    = config::byKey('mqtt_port', 'teleinfo', '1883');
        $mqtt_topic 	= config::byKey('mqtt_topic', 'teleinfo', '#');
        $mqtt_username 	= config::byKey('mqtt_username', 'teleinfo', 'aucun_pour_etre_certain');
        $mqtt_password 	= config::byKey('mqtt_password', 'teleinfo', 'aucun_pour_etre_certain');
        $keep_alive     = 45; # interval en seconde
        log::add('teleinfo', 'info', '---------------------------------------------');
        log::add('teleinfo', 'info', __('[MQTT] Démarrage service MQTT ', __FILE__));
        log::add('teleinfo', 'info', "SocketHost : " . $socketHost);
        log::add('teleinfo', 'info', "Socketport : " . $socketPort);
        log::add('teleinfo', 'info', "Broker : " . $mqtt_broker);
        log::add('teleinfo', 'info', __("Port du Broker :", __FILE__) . ' ' . $mqtt_port);
        log::add('teleinfo', 'info', "topic : " . '"' . $mqtt_topic . '"');
        log::add('teleinfo', 'info', '---------------------------------------------');
        $cmd          = 'nice -n 19 ' . $teleinfoPath . '/venv/bin/python3 ' . $teleinfoPath . '/teleinfo_mqtt.py';
        $cmd         .= ' --socketport ' . $socketPort;
        $cmd         .= ' --mqtt True';
        $cmd         .= ' --mqtt_broker ' . $mqtt_broker;
        $cmd         .= ' --mqtt_port ' . $mqtt_port;
        $cmd         .= ' --apikey ' . jeedom::getApiKey('teleinfo');
        $cmd         .= ' --mqtt_keepalive ' . $keep_alive;
        $cmd         .= ' --mqtt_username ' . $mqtt_username;
        $cmd         .= ' --mqtt_password ' . $mqtt_password;
        $cmd         .= ' --modem aucun';
        $cmd         .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/teleinfo/core/php/jeeTeleinfo.php';
        $cmd         .= ' --loglevel '. log::convertLogLevel(log::getLogLevel(__CLASS__));
        $cmd         .= ' --mqtt_topic ' . '"' . $mqtt_topic . '"';
        log::add('teleinfo', 'info', __('[découverte MQTT] Exécution du service :', __FILE__) . ' ' . $cmd);
        $result = exec($cmd . ' >> ' . log::getPathToLog('teleinfo_deamon_Mqtt') . ' 2>&1 &');
        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
            log::add('teleinfo', 'error', $result);
            return false;
        }
        sleep(2);
        if (!self::runningMqtt('non')) {
            sleep(10);
            if (!self::runningMqtt('oui')) {
                log::add('teleinfo', 'error', __('[TELEINFO_mqtt] Impossible de lancer le démon téléinfo, vérifiez la configuration.', __FILE__), 'unableStartDeamon');
                return false;
            }
        }
        message::removeAll('teleinfo', 'unableStartDeamon');
        log::add('teleinfo', 'info', __('[mqtt] Service OK', __FILE__));
        log::add('teleinfo', 'info', __('[mqtt] Voir les logs MQTT dans le fichier correspondant', __FILE__));
        log::add('teleinfo', 'info', '---------------------------------------------');
    }

    /**
     *
     * @return boolean
     */
    public static function deamonRunning()
    {
            $twoCptCartelectronic = config::byKey('2cpt_cartelectronic', 'teleinfo');
            if ($twoCptCartelectronic == 1) {
                $result = exec("ps aux | grep teleinfo_2_cpt.py | grep -v grep | awk '{print $2}'");
                if ($result != "") {
                    return true;
                }
                log::add('teleinfo', 'info', '[deamonRunning] Vérification de l\'état du service : NOK ');
                return false;
            } else {
                $result = exec("ps aux | grep teleinfo.py | grep -v grep | awk '{print $2}'");
                if ($result != "") {
                    return true;
                }
                log::add('teleinfo', 'info', __('[deamonRunning] Vérification de l\'état du service : NOK ', __FILE__));
                return false;
            }
    }

    public static function deamonRunningMqtt($affiche = 'oui'){
        $result = exec("ps aux | grep teleinfo_mqtt.py | grep -v grep | awk '{print $2}'");
        if ($result != "") {
            return true;
        }
        if ($affiche != 'non'){
            log::add('teleinfo', 'info', __('[deamonRunningMqtt] Vérification de l\'état du service MQTT : NOK ', __FILE__));
        }
        return false;
    }

    public static function runningMqtt(){
        $result = exec("ps aux | grep teleinfo_mqtt.py | grep -v grep | awk '{print $2}'");
        if ($result != "") {
            return true;
        }
        log::add('teleinfo', 'info', __('[découverte Mqtt] Vérification de l\'état du service de découverte MQTT : NOK ', __FILE__));
        return false;
    }

    /**
     *
     * @return array
     */
    public static function deamon_info()
    {
        $activation_Modem = (config::byKey('activation_Modem', 'teleinfo') == "") ? 0 : config::byKey('activation_Modem', 'teleinfo');
        $activation_Mqtt = (config::byKey('activation_Mqtt', 'teleinfo') == "") ? 0 : config::byKey('activation_Mqtt', 'teleinfo');
        $consoPort = (config::byKey('port', 'teleinfo') == "") ? "" : config::byKey('port', 'teleinfo');
        $productionPort = (config::byKey('port_modem2', 'teleinfo') == "") ? "" : config::byKey('port_modem2', 'teleinfo');
        $twoCptCartelectronic = config::byKey('2cpt_cartelectronic', 'teleinfo');
        if ($productionPort != ""){
            $productionActivated = 1;
        } else {
            $productionActivated = 0;
        }
        if ($consoPort != "" || $twoCptCartelectronic == 1){
            $consoActivated = 1;
        } else {
            $consoActivated = 0;
        }
        $return               = array();
        $return['log']        = 'teleinfo';
        $return['state']      = 'nok';
        $returnmodem = 'sans';
        $returnmqtt = 'sans';
        $returnprod = 'sans';
        if ($consoActivated == 1 && $activation_Modem==1){
            log::add('teleinfo', 'debug', '[TELEINFO_deamon_infoserial] test pid');
            if ($twoCptCartelectronic == 1) {
                $pidFile = jeedom::getTmpFolder('teleinfo') . '/teleinfo2cpt.pid';
            } else {
                $pidFile = jeedom::getTmpFolder('teleinfo') . '/teleinfo_conso.pid';
            }
            if (file_exists($pidFile)) {
                if (posix_getsid(trim(file_get_contents($pidFile)))) {
                    log::add('teleinfo', 'debug', __('[TELEINFO_deamon_infoserial] démon port modem 1 ou 2cpt => ok', __FILE__));
                    $returnmodem = 'ok';
                } else {
                    log::add('teleinfo', 'error', __("[TELEINFO_deamon_infoserial] le deamon port modem 1 s'est éteint", __FILE__));
                    $returnmodem = 'nok';
                    shell_exec('sudo rm -rf ' . $pidFile . ' 2>&1 > /dev/null;rm -rf ' . $pidFile . ' 2>&1 > /dev/null;');
                }
            }else{
                log::add('teleinfo', 'error', __("[TELEINFO_deamon_infoserial] le deamon port modem 1 n'est pas démarré", __FILE__));
                $returnmodem = 'nok';
            }
        }
        if ($productionActivated == 1 && $activation_Modem==1){
            log::add('teleinfo', 'debug', '[TELEINFO_deamon_infoprod] test pid');
            $pidFile = jeedom::getTmpFolder('teleinfo') . '/teleinfo_prod.pid';
            if (file_exists($pidFile)) {
                if (posix_getsid(trim(file_get_contents($pidFile)))) {
                    log::add('teleinfo', 'debug', __('[TELEINFO_deamon_infoserial] démon port modem 2 => ok', __FILE__));
                    $returnprod = 'ok';
                } else {
                    log::add('teleinfo', 'error', __("[TELEINFO_deamon_infoserial] le deamon port modem 2 s'est éteint", __FILE__));
                    $returnprod = 'nok';
                    shell_exec('sudo rm -rf ' . $pidFile . ' 2>&1 > /dev/null;rm -rf ' . $pidFile . ' 2>&1 > /dev/null;');
                }
            }else{
                log::add('teleinfo', 'error', __("[TELEINFO_deamon_infoserial] le deamon port modem 2 n'est pas démarré", __FILE__));
                $returnprod = 'nok';
            }
        }

        if (($consoPort == "" && $productionPort == "") && $activation_Modem == 1 && $twoCptCartelectronic != 1) {
            log::add('teleinfo', 'error', __("[TELEINFO_deamon_infoserial] Aucun port modem configuré, revoir votre config", __FILE__));
            $returnmodem = 'Aucun port modem configuré';
        }

        if ($activation_Mqtt==1){
            $pidFile = jeedom::getTmpFolder('teleinfo') . '/teleinfo_Mqtt.pid';
            log::add('teleinfo', 'debug', '[TELEINFO_deamon_infoMqtt] test pid');
            if (file_exists($pidFile)) {
                if (posix_getsid(trim(file_get_contents($pidFile)))) {
                    log::add('teleinfo', 'debug', __('[TELEINFO_deamon_infoMqtt] démon Mqtt => ok', __FILE__));
                    $returnmqtt = 'ok';
                } else {
                    $returnmqtt = 'nok';
                    log::add('teleinfo', 'error', __("[TELEINFO_deamon_infoMqtt] le deamon MQTT s'est éteint", __FILE__));
                    shell_exec('sudo rm -rf ' . $pidFile . ' 2>&1 > /dev/null;rm -rf ' . $pidFile . ' 2>&1 > /dev/null;');
                }
            }else{
                log::add('teleinfo', 'error', __("[TELEINFO_deamon_infoMqtt] le deamon MQTT n'est pas démarré", __FILE__));
                $returnmqtt = 'nok';
            }
        }
        $return['launchable'] = 'ok';
        if (($returnmodem != 'nok' && $returnmqtt != 'nok' && $returnprod != 'nok')&&($returnmodem != 'sans' || $returnmqtt != 'sans' || $returnprod != 'sans')){
            $return['state'] = 'ok';
            $return['deamon_modem'] = $returnmodem;
            $return['deamon_MQTT'] = $returnmqtt;
            $return['deamon_prod'] = $returnprod;
        }else{
            $return['state'] = 'nok';
            $return['deamon_modem'] = $returnmodem;
            $return['deamon_MQTT'] = $returnmqtt;
            $return['deamon_prod'] = $returnprod;
        }
        log::add('teleinfo', 'debug', '[TELEINFO_deamon_modem] état : ' . $returnmodem);
        log::add('teleinfo', 'debug', '[TELEINFO_deamon_MQTT] état : ' . $returnmqtt);
        log::add('teleinfo', 'debug', '[TELEINFO_deamon_prod] état : '. $returnprod);
        log::add('teleinfo', 'debug', '[TELEINFO_deamon] état global => retour: ' . $return['state']);
        return $return;
    }


    /**
     * appelé par jeedom pour démarrer le deamon
     */
    public static function deamon_start($debug = false)
    {
        $activation_Modem = (config::byKey('activation_Modem', 'teleinfo') == "") ? 1 : config::byKey('activation_Modem', 'teleinfo');
        $activation_Mqtt = (config::byKey('activation_Mqtt', 'teleinfo') == "") ? 0 : config::byKey('activation_Mqtt', 'teleinfo');
        $consoPort = (config::byKey('port', 'teleinfo') == "") ? "" : config::byKey('port', 'teleinfo');
        $productionPort = (config::byKey('port_modem2', 'teleinfo') == "") ? "" : config::byKey('port_modem2', 'teleinfo');
        $configure = 0;
        if ($productionPort != ""){
            $productionActivated = 1;
        } else {
            $productionActivated = 0;
        }
        if ($consoPort != ""){
            $consoActivated = 1;
        } else {
            $consoActivated = 0;
        }
        if ($activation_Modem == 1) {
            log::add('teleinfo', 'info', __('[deamon_start_modem] Démarrage du service', __FILE__));
            if (config::byKey('port', 'teleinfo') != "" || config::byKey('2cpt_cartelectronic', 'teleinfo') == 1) {    // Si un port est sélectionné
                if (!self::deamonRunning()) {
                    log::add('teleinfo', 'info', __('Lancement démon pour modem 1', __FILE__));
                    self::runDeamon($debug, 'conso');
                }
                message::removeAll('teleinfo', 'noTeleinfoPort');
            } else {
                log::add('teleinfo', 'info', __('Port du modem1 non configuré', __FILE__));
                $configure += 1;
            }
            if ($productionActivated == 1) {    // Si un port est sélectionné
                //if (!self::deamonRunning()) {
                    log::add('teleinfo', 'info', __('Lancement démon pour modem 2', __FILE__));
                    self::runDeamon($debug, 'prod');
                //}
                //message::removeAll('teleinfo', 'noTeleinfoPort');
            } else {
                log::add('teleinfo', 'info', __('Port du modem2 non configuré', __FILE__));
                $configure += 1;
            }
            if ($configure == 2) {
                log::add('teleinfo', 'error', __("Aucun port modem configuré ce n'est pas normal", __FILE__));
                log::add('teleinfo', 'error', __("Les démons modem ne seront pas lancés", __FILE__));
            }
        }
        if ($activation_Mqtt == 1){
            log::add('teleinfo', 'info', __('[deamon_start_MQTT] Démarrage du service', __FILE__));
            if (!self::deamonRunningMqtt('non')) {
                self::runDeamonMqtt($debug, 'rien');
                message::removeAll('teleinfo', 'noTeleinfoPort');
            }
        }

        if ($activation_Modem == 0 and $activation_Mqtt == 0){
            log::add('teleinfo', 'error', __('[TELEINFO_deamon] pas de modem ni de MQTT configuré => pas de démarrage du service', __FILE__));
        }

    }

    public static function start_Mqtt($debug = false, $socketPort, $socketHost, $modem, $mqtt, $mqtt_broker, $mqtt_port, $mqtt_topic, $mqtt_username, $mqtt_password){
        if (!self::deamonRunningMqtt()) {
            self::runDeamonMqtt($debug, 'rien', $socketPort, $socketHost, $modem, $mqtt, $mqtt_broker, $mqtt_port, $mqtt_topic, $mqtt_username, $mqtt_password);
            message::removeAll('teleinfo', 'noTeleinfoPort');
        }
    }
    /**
     * appelé par jeedom pour arrêter le deamon
     */
    public static function deamon_stop()
    {
        $activation_Modem = (config::byKey('activation_Modem', 'teleinfo') == "") ? 1 : config::byKey('activation_Modem', 'teleinfo');
        $activation_Mqtt = (config::byKey('activation_Mqtt', 'teleinfo') == "") ? 0 : config::byKey('activation_Mqtt', 'teleinfo');
        $consoPort = (config::byKey('port', 'teleinfo') == "") ? "" : config::byKey('port', 'teleinfo');
        $productionPort = (config::byKey('port_modem2', 'teleinfo') == "") ? "" : config::byKey('port_modem2', 'teleinfo');
        if ($productionPort != ""){
            $productionActivated = 1;
        } else {
            $productionActivated = 0;
        }
        if ($consoPort != ""){
            $consoActivated = 1;
        } else {
            $consoActivated = 0;
        }
        $deamonKill= false;
        $deamonInfo = self::deamon_info();
        // if ($activation_Modem==1){
        if ($deamonInfo['deamon_modem'] == 'ok' || $deamonInfo['deamon_prod'] == 'ok') {
            log::add('teleinfo', 'info', __("[deamon_stop_serial] Tentative d'arrêt du service", __FILE__));
            $twoCptCartelectronic = config::byKey('2cpt_cartelectronic', 'teleinfo');
            if ($twoCptCartelectronic == 1) {
                $pidFile = jeedom::getTmpFolder('teleinfo') . '/teleinfo2cpt.pid';
                if (file_exists($pidFile)) {
                    $pid  = intval(trim(file_get_contents($pidFile)));
                    $kill = posix_kill($pid, 15);
                    usleep(1000);
                    if ($kill) {
                        $deamonKill= true;
                        log::add('teleinfo', 'info', __("[deamon_stop_serial] arrêt du service 2cpt OK", __FILE__));
                    } else {
                        system::kill($pid);
                    }
                }
                //$result = exec("ps aux | grep teleinfo_2_cpt.py | grep -v grep | awk '{print $2}'");
                //system::kill($result);
                system::kill('teleinfo_2_cpt.py');
            } else {
                if ($deamonInfo['deamon_prod'] == 'ok') {
                    $pidFile = jeedom::getTmpFolder('teleinfo') . '/teleinfo_prod.pid';
                    if (file_exists($pidFile)) {
                        $pid  = intval(trim(file_get_contents($pidFile)));
                        $kill = posix_kill($pid, 15);
                        usleep(500);
                        if ($kill) {
                            $deamonKill = true;
                            log::add('teleinfo', 'info', __("[deamon_stop_serial] Arrêt du service Prod OK", __FILE__));
                        }else{
                            system::kill($pid);
                        }
                    }
                }
                if ($deamonInfo['deamon_modem'] == 'ok') {
                    $pidFile = jeedom::getTmpFolder('teleinfo') . '/teleinfo_conso.pid';
                    if (file_exists($pidFile)) {
                        $pid  = intval(trim(file_get_contents($pidFile)));
                        $kill = posix_kill($pid, 15);
                        usleep(500);
                        if ($kill) {
                            $deamonKill= true;
                            log::add('teleinfo', 'info', __("[deamon_stop_serial] Arrêt du service Conso OK", __FILE__));
                        } else {
                            system::kill($pid);
                        }
                    }
                }
                system::kill('teleinfo.py');
                $port = config::byKey('port', 'teleinfo');
                if ($port != "serie") {
                    $port = jeedom::getUsbMapping(config::byKey('port', 'teleinfo'));
                    system::fuserk(jeedom::getUsbMapping($port));
                    sleep(1);
                }
            }
        }
        //}

        //$deamonInfoMqtt = self::deamon_infoMqtt();
        if ($deamonInfo['deamon_MQTT'] == 'ok') {
            log::add('teleinfo', 'info', __("[deamon_stop_Mqtt] Tentative d'arrêt du service", __FILE__));
            $pidFile = jeedom::getTmpFolder('teleinfo') . '/teleinfo_Mqtt.pid';
            if (file_exists($pidFile)) {
                $pid  = intval(trim(file_get_contents($pidFile)));
                $kill = posix_kill($pid, 15);
                usleep(500);
                if ($kill) {
                    $deamonKill= true;
                    log::add('teleinfo', 'info', __("[deamon_stop_Mqtt] Arrêt du service Mqtt OK", __FILE__));
                } else {
                    system::kill($pid);
                }
                system::kill('teleinfo_mqtt.py');
            }
        }

        return $deamonKill;

    }


    public static function calculateTodayStats()
    {
        $indexConsoHP = config::byKey('indexConsoHP', 'teleinfo', 'EASF02,EASF04,EASF06,HCHP,BBRHPJB,BBRHPJW,BBRHPJR,EJPHPM');
        $indexConsoHC = config::byKey('indexConsoHC', 'teleinfo', 'EASF01,EASF03,EASF05,HCHC,BBRHCJB,BBRHCJW,BBRHCJR,EJPHN');
        $initialIndexProduction = config::byKey('indexProduction', 'teleinfo', 'EAIT');
        $indexConsoTotales = config::byKey('indexConsoTotales', 'teleinfo', 'BASE,EAST,HCHP,HCHC,BBRHPJB,BBRHPJW,BBRHPJR,BBRHCJB,BBRHCJW,BBRHCJR,EJPHPM,EJPHN');
        
        log::add('teleinfo', 'info', 'Stats : ----------- Calcul des statistiques temps réel -----------');
        
        $todayDate = date('Y-m-d');
        $startDateToday = (new DateTime())->setTimestamp(mktime(0, 0, 0, date("m"), date("d"), date("Y")));
        $endDateToday = (new DateTime())->setTimestamp(mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
        
        // === NETTOYAGE DES ANCIENNES CLÉS EN CONFIG (une seule fois par jour) ===
        $cleanupDone = cache::byKey('teleinfo::todayStats::cleanupDone::' . $todayDate);
        if (!is_object($cleanupDone) || $cleanupDone->getValue() !== '1') {
            $prefix = 'todayStats_';
            $todayPrefix = $prefix . $todayDate . '_';
            $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
            $yesterdayPrefix = $prefix . $yesterdayDate . '_';
            
            $configs = config::searchKey($prefix, 'teleinfo');
            $kept = 0;
            $removed = 0;
            foreach ($configs as $key => $value) {
                // Garder les clés du jour courant ET de la veille (pour la tendance)
                if (strpos($value['key'], $todayPrefix) === 0 || strpos($value['key'], $yesterdayPrefix) === 0) {
                    $kept++;
                    continue;
                }
                config::remove($value['key'], 'teleinfo');
                $removed++;
            }
            
            log::add('teleinfo', 'info', "Nettoyage todayStats : $kept conservées (jour courant + veille), $removed supprimées (anciennes)");
            
            // Marquer le nettoyage comme fait pour aujourd'hui
            cache::set('teleinfo::todayStats::cleanupDone::' . $todayDate, '1', 86400);
        }
        
        log::add('teleinfo', 'info', 'Stats : Date de début : ' . $startDateToday->format('Y-m-d 00:00:00'));
        log::add('teleinfo', 'info', 'Stats : Date de fin : ' . $endDateToday->format('Y-m-d H:i:s'));
        log::add('teleinfo', 'info', 'Stats : ---------- infos de la configuration ancienne méthode ----------');
        log::add('teleinfo', 'info', 'Stats : Liste index HP : ' . $indexConsoHP);
        log::add('teleinfo', 'info', 'Stats : Liste index HC : ' . $indexConsoHC);
        log::add('teleinfo', 'info', 'Stats : Liste index Production : ' . $initialIndexProduction);
        log::add('teleinfo', 'info', 'Stats : Liste index Conso Totale : ' . $indexConsoTotales);
        
        foreach (eqLogic::byType('teleinfo') as $eqLogic) {
            log::add('teleinfo', 'info', 'Stats : ---------------------------------------------------------------');
            log::add('teleinfo', 'info', 'Stats : Objet : ' . $eqLogic->getName());

            $indexProduction = $initialIndexProduction;
            if ($eqLogic->getConfiguration('ActivationProduction') == 1 && $indexProduction != 'EAIT') {
                $indexProduction = 'EAIT';
                log::add('teleinfo', 'info', "Stats : mise à jour de l'index production en EAIT");
            }

            $newIndex = intval($eqLogic->getConfiguration('newIndex',0));

            $eqId = $eqLogic->getId();
            $cacheKeyPrefix = 'teleinfo::todayStats::' . $todayDate . '::' . $eqId . '::';
            $configKeyPrefix = 'todayStats_' . $todayDate . '_' . $eqId . '_';
            
            // === FONCTION UTILITAIRE pour récupérer la valeur de départ (cache ou config ou ...) ===
            $getStartValue = function($cmdId, $keyName) use ($cacheKeyPrefix, $configKeyPrefix, $todayDate, $startDateToday, $endDateToday) {
                // 1. Essayer le cache
                $cacheKey = $cacheKeyPrefix . $keyName;
                $cachedValue = cache::byKey($cacheKey);
                if (is_object($cachedValue) && $cachedValue->getValue() !== '') {
                    log::add('teleinfo', 'debug', 'Stats : Valeur départ depuis cache pour ' . $keyName . ' : ' . $cachedValue->getValue());
                    return floatval($cachedValue->getValue());
                }
                
                // 2. Essayer la config (en cas de crash cache)
                $configKey = $configKeyPrefix . $keyName;
                $configDateKey = $configKeyPrefix . 'date';
                $configDate = config::byKey($configDateKey, 'teleinfo', '');
                
                if ($configDate === $todayDate) {
                    $configValue = config::byKey($configKey, 'teleinfo', '');
                    if ($configValue !== '') {
                        log::add('teleinfo', 'debug', 'Stats : Valeur départ depuis config pour ' . $keyName . ' : ' . $configValue);
                        // Reconstituer le cache
                        cache::set($cacheKey, $configValue, 86400);
                        return floatval($configValue);
                    }
                }
                
                // 3. Ni cache ni config : essayer le min de la journée (démarrage en cours de journée)
                $cmd = cmd::byId($cmdId);
                if (!is_object($cmd)) {
                    log::add('teleinfo', 'warning', 'Stats : Commande introuvable pour ' . $keyName . ' (ID: ' . $cmdId . ')');
                    return 0;
                }
                
                // Récupérer le minimum de la journée depuis l'historique
                $statMin = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'));
                $startValue = null;
                
                if (isset($statMin['min']) && $statMin['min'] !== null && $statMin['min'] !== '') {
                    $startValue = floatval($statMin['min']);
                    log::add('teleinfo', 'debug', 'Stats : Valeur départ depuis min journée pour ' . $keyName . ' : ' . $startValue);
                } else {
                    // valeur actuelle si pas d'historique (début de journée, pas encore de données)
                    $startValue = floatval($cmd->execCmd());
                    log::add('teleinfo', 'debug', 'Stats : Pas de min journée, utilisation valeur actuelle pour ' . $keyName . ' : ' . $startValue);
                }
                
                // Stocker dans le cache (48h)
                cache::set($cacheKey, $startValue, 86400);
                
                // Stocker dans la config (backup)
                config::save($configKey, $startValue, 'teleinfo');
                config::save($configDateKey, $todayDate, 'teleinfo');
                
                return $startValue;
            };
            
            // === FONCTION UTILITAIRE pour calculer la conso d'une liste de commandes ===
            $calculateConsoFromList = function($cmdIds, $keyPrefix) use ($getStartValue) {
                $totalConso = 0;
                $totalStart = 0;
                $totalCurrent = 0;
                
                foreach ($cmdIds as $cmdId) {
                    $cmd = cmd::byId($cmdId);
                    if (!is_object($cmd)) {
                        continue;
                    }
                    
                    $startValue = $getStartValue($cmdId, $keyPrefix . '_' . $cmdId);
                    $currentValue = floatval($cmd->execCmd());
                    
                    $totalStart += $startValue;
                    $totalCurrent += $currentValue;
                    $totalConso += ($currentValue - $startValue);
                    
                    log::add('teleinfo', 'debug', 'Stats : Cmd ' . $cmdId . ' - Départ: ' . $startValue . ', Actuel: ' . $currentValue . ', Conso: ' . ($currentValue - $startValue));
                }
                
                return $totalConso;
            };
            
            // === IDENTIFICATION DES COMMANDES ===
            $statHpToCumul = array();
            $statHcToCumul = array();
            $statProdToCumul = array();
            $statTotalToCumul = array();
            $typeTendance = -1;
            
            // Index configurés
            $configuredIndexes = array();

            // initialisation des variables
            $statTodayIndex00 = 0;
            $Coutindex00 = 0;
            $hasIndex00direct = false;

            if ($newIndex == 1) {
                for ($i = 0; $i <= 10; $i++) {
                    $indexNum = str_pad($i, 2, '0', STR_PAD_LEFT);
                    $indexName = ($i == 0) ? '' : $eqLogic->getConfiguration('index' . $indexNum, '');
                    $coutKwh = $eqLogic->getConfiguration('Coutindex' . $indexNum, 0);
                    
                    $configuredIndexes[$i] = array(
                        'name' => $indexName,
                        'cout_kwh' => floatval($coutKwh),
                        'cmd_id' => null,
                        'start_value' => null,
                        'current_value' => null,
                        'conso' => 0,
                        'cout' => 0
                    );
                }
            }
            
            // Parcourir les commandes pour identification
            foreach ($eqLogic->getCmd('info') as $cmd) {
                $infoConso = $cmd->getConfiguration('info_conso');
                $cmdType = $cmd->getConfiguration('type');
                
                // Commandes de données
                if ($cmdType == "data" || $cmdType == "") {
                    if (!empty($infoConso)) {
                        // Catégorisation pour ancienne méthode (modale)
                        if (strpos($indexConsoHP, $infoConso) !== false) {
                            array_push($statHpToCumul, $cmd->getId());
                        }
                        if (strpos($indexConsoHC, $infoConso) !== false) {
                            array_push($statHcToCumul, $cmd->getId());
                        }
                        if (strpos($indexProduction, $infoConso) !== false) {
                            array_push($statProdToCumul, $cmd->getId());
                        }
                        if (strpos($indexConsoTotales, $infoConso) !== false) {
                            array_push($statTotalToCumul, $cmd->getId());
                        }
                        
                        // Nouveaux Index:
                        if ($newIndex == 1){
                            //Index 00 : BASE ou EAST
                            if ($infoConso == 'BASE' || $infoConso == 'EAST') {
                                $configuredIndexes[0]['name'] = $infoConso;
                                $configuredIndexes[0]['cmd_id'] = $cmd->getId();
                                log::add('teleinfo', 'info', 'Stats : Index 00 détecté --> ' . $infoConso . ' (ID: ' . $cmd->getId() . ')');
                            }
                            
                            // Index 01-10
                            for ($i = 1; $i <= 10; $i++) {
                                $indexNum = str_pad($i, 2, '0', STR_PAD_LEFT);
                                if ($infoConso == $configuredIndexes[$i]['name']) {
                                    $configuredIndexes[$i]['cmd_id'] = $cmd->getId();
                                    log::add('teleinfo', 'debug', 'Stats : Index ' . $indexNum . ' détecté (ID: ' . $cmd->getId() . ')');
                                }
                            }
                        }
                    }
                }
                
                // Détection du type de tendance
                if ($infoConso == "TENDANCE_DAY") {
                    $raw = $cmd->getConfiguration('type_calcul_tendance');
                    if ($raw !== '' && is_numeric($raw)) {
                        $typeTendance = intval($raw);
                    }
                }
            }
            
            // === CALCUL DES CONSOMMATIONS ===
            // Nouveaux index:
            if ($newIndex == 1){
                //1. Calcul pour les index configurés (01-10)
                for ($i = 1; $i <= 10; $i++) {
                    $indexNum = str_pad($i, 2, '0', STR_PAD_LEFT);
                    
                    if (!empty($configuredIndexes[$i]['cmd_id'])) {
                        $cmdId = $configuredIndexes[$i]['cmd_id'];
                        $cmd = cmd::byId($cmdId);
                        
                        if (is_object($cmd)) {
                            $startValue = $getStartValue($cmdId, 'index' . $indexNum);
                            $currentValue = floatval($cmd->execCmd());
                            $conso = max(0, $currentValue - $startValue); // Protection contre valeurs négatives
                            $cout = $conso * $configuredIndexes[$i]['cout_kwh'] / 1000;
                            
                            $configuredIndexes[$i]['start_value'] = $startValue;
                            $configuredIndexes[$i]['current_value'] = $currentValue;
                            $configuredIndexes[$i]['conso'] = $conso;
                            $configuredIndexes[$i]['cout'] = $cout;
                            
                            // Cumul pour index00 (somme des index configurés)
                            $statTodayIndex00 += $conso;
                            $Coutindex00 += $cout;
                            
                            if ($conso != 0) log::add('teleinfo', 'info', 'Stats : Index ' . $indexNum . ' --> Conso: ' . $conso . ' Wh, Coût: ' . round($cout, 2) . ' €');
                        }
                    }
                }
                
                // 2. Calcul pour index00 (BASE/EAST)
                if (!empty($configuredIndexes[0]['cmd_id'])) {
                    $cmdId = $configuredIndexes[0]['cmd_id'];
                    $cmd = cmd::byId($cmdId);
                    
                    if (is_object($cmd)) {
                        $startValue = $getStartValue($cmdId, 'index00');
                        $currentValue = floatval($cmd->execCmd());
                        $consoIndex00Direct = max(0, $currentValue - $startValue);
                        $coutIndex00Direct = $consoIndex00Direct * $configuredIndexes[0]['cout_kwh'] / 1000;
                        
                        $configuredIndexes[0]['start_value'] = $startValue;
                        $configuredIndexes[0]['current_value'] = $currentValue;
                        $configuredIndexes[0]['conso'] = $consoIndex00Direct;
                        $configuredIndexes[0]['cout'] = $coutIndex00Direct;
                        $hasIndex00direct = true;
                        
                        log::add('teleinfo', 'info', 'Stats : Index 00 (BASE/EAST) --> Conso: ' . $consoIndex00Direct . ' Wh');
                    }
                }
            }
            
            // Ancienne méthode:
            
            // 3. Calcul HP/HC/Total/Prod
            $statTodayHp = $calculateConsoFromList($statHpToCumul, 'hp');
            $statTodayHc = $calculateConsoFromList($statHcToCumul, 'hc');
            $statTodayProd = $calculateConsoFromList($statProdToCumul, 'prod');
            $statTodayTotal = $calculateConsoFromList($statTotalToCumul, 'total');
            
            log::add('teleinfo', 'info', 'Stats : Total HP: ' . $statTodayHp . ' Wh');
            log::add('teleinfo', 'info', 'Stats : Total HC: ' . $statTodayHc . ' Wh');
            log::add('teleinfo', 'info', 'Stats : Total Conso: ' . $statTodayTotal . ' Wh');
            log::add('teleinfo', 'info', 'Stats : Total Prod: ' . $statTodayProd . ' Wh');
            
            // 4. Calcul tendance
            $statYesterday = 0;
            if ($typeTendance != -1) {
                $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
                
                if ($newIndex == 1) {
                    $cmdStatToday = $eqLogic->getCmd('info', 'STAT_TODAY_INDEX00');
                } else {
                    $cmdStatToday = $eqLogic->getCmd('info', 'STAT_TODAY');
                }
                
                if (is_object($cmdStatToday)) {
                    $cacheKey = 'teleinfo::yesterdayTotal::' . $yesterdayDate . '::' . $eqId;
                    
                    if ($typeTendance === 1) {
                        // Journée complète
                        $cachedValue = cache::byKey($cacheKey);
                        if (is_object($cachedValue) && $cachedValue->getValue() !== '') {
                            $statYesterday = floatval($cachedValue->getValue());
                            log::add('teleinfo', 'debug', 'Stats : Conso hier (journée) depuis cache : ' . $statYesterday);
                        } else {
                            // getStatistique max d'hier
                            $startdateyesterday = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m"), date("d") - 1, date("Y")));
                            $enddateyesterday = date("Y-m-d H:i:s", mktime(23, 59, 59, date("m"), date("d") - 1, date("Y")));
                            $stat = $cmdStatToday->getStatistique($startdateyesterday, $enddateyesterday);
                            $statYesterday = isset($stat['max']) ? floatval($stat['max']) : 0;
                            
                            log::add('teleinfo', 'info', 'Stats : Conso hier (journée) depuis getStatistique : ' . $statYesterday);
                            
                            // Enregistrer pour les prochains appels
                            cache::set($cacheKey, $statYesterday, 172800);
                        }
                    } else {
                        // à heure identique
                        $startdateyesterday = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m"), date("d") - 1, date("Y")));
                        $enddateyesterday = date("Y-m-d H:i:s", mktime(date("H"), date("i"), date("s"), date("m"), date("d") - 1, date("Y")));
                        $stat = $cmdStatToday->getStatistique($startdateyesterday, $enddateyesterday);
                        $statYesterday = isset($stat['max']) ? floatval($stat['max']) : 0;
                        
                        log::add('teleinfo', 'info', 'Stats : Conso hier à ' . date("H:i:s", mktime(date("H"), date("i"), date("s"), date("m"), date("d") - 1, date("Y"))) . ' depuis getStatistique : ' . $statYesterday);
                    }
                }
                
                //log::add('teleinfo', 'info', 'Stats : Tendance - Conso Hier: ' . $statYesterday . ' Wh, type: ' . ($typeTendance == 1 ? 'journée complète' : 'à heure identique'));
            }
            
            // === MISE À JOUR DES COMMANDES STATISTIQUES ===

            // Initialiser la valeur pour la tendance
            $todayValueForTendance = 0;
            if ($newIndex == 1) {
                if ($hasIndex00direct) {
                    $todayValueForTendance = intval($configuredIndexes[0]['conso']);
                } else {
                    $todayValueForTendance = intval($statTodayIndex00);
                }
            } else {
                $todayValueForTendance = intval($statTodayTotal);
            }

            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getConfiguration('type') != "stat") {
                    continue;
                }
                
                $infoConso = $cmd->getConfiguration('info_conso');
                
                switch ($infoConso) {
                    case "STAT_TODAY":
                        // Utilise le total si disponible, sinon index00
                        if (intval($statTodayTotal) != 0) {
                            log::add('teleinfo', 'info', 'Stats : Mise à jour STAT_TODAY (total) ==> ' . intval($statTodayTotal) . ' Wh');
                            $cmd->event(intval($statTodayTotal));
                        } elseif ($hasIndex00direct) {
                            if (intval($configuredIndexes[0]['conso']) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour STAT_TODAY (index00 direct) ==> ' . intval($configuredIndexes[0]['conso']) . ' Wh');
                            $cmd->event(intval($configuredIndexes[0]['conso']));
                        } else {
                            if (intval($statTodayIndex00) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour STAT_TODAY (somme index) ==> ' . intval($statTodayIndex00) . ' Wh');
                            $cmd->event(intval($statTodayIndex00));
                        }
                        break;
                        
                    case "STAT_TODAY_HP":
                        if (intval($statTodayHp) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour STAT_TODAY_HP ==> ' . intval($statTodayHp) . ' Wh');
                        $cmd->event(intval($statTodayHp));
                        break;
                        
                    case "STAT_TODAY_HC":
                        if (intval($statTodayHc) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour STAT_TODAY_HC ==> ' . intval($statTodayHc) . ' Wh');
                        $cmd->event(intval($statTodayHc));
                        break;
                        
                    case "STAT_TODAY_PROD":
                        if (intval($statTodayProd) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour STAT_TODAY_PROD ==> ' . intval($statTodayProd) . ' Wh');
                        $cmd->event(intval($statTodayProd));
                        break;
                        
                    case "STAT_TODAY_INDEX00":
                        if ($newIndex == 1) {
                            // priorité à BASE/EAST, sinon somme des index
                            if ($hasIndex00direct && $configuredIndexes[0]['conso'] > 0) {
                                if (intval($configuredIndexes[0]['conso']) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour STAT_TODAY_INDEX00 (direct) ==> ' . intval($configuredIndexes[0]['conso']) . ' Wh');
                                $cmd->event(intval($configuredIndexes[0]['conso']));
                            } else {
                                if (intval($statTodayIndex00) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour STAT_TODAY_INDEX00 (cumul) ==> ' . intval($statTodayIndex00) . ' Wh');
                                $cmd->event(intval($statTodayIndex00));
                            }
                        }
                        break;
                        
                    case "STAT_TODAY_INDEX00_COUT":
                        if ($newIndex == 1) {
                            if ($hasIndex00direct && $configuredIndexes[0]['cout'] > 0) {
                                if (round($configuredIndexes[0]['cout'],2) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour STAT_TODAY_INDEX00_COUT (direct) ==> ' . round($configuredIndexes[0]['cout'], 2) . ' €');
                                $cmd->event(round($configuredIndexes[0]['cout'], 2));
                            } else {
                                if (intval($Coutindex00) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour STAT_TODAY_INDEX00_COUT (cumul) ==> ' . round($Coutindex00, 2) . ' €');
                                $cmd->event(round($Coutindex00, 2));
                            }
                        }
                        break;
                        
                    // Index 01-10
                    case "STAT_TODAY_INDEX01":
                    case "STAT_TODAY_INDEX02":
                    case "STAT_TODAY_INDEX03":
                    case "STAT_TODAY_INDEX04":
                    case "STAT_TODAY_INDEX05":
                    case "STAT_TODAY_INDEX06":
                    case "STAT_TODAY_INDEX07":
                    case "STAT_TODAY_INDEX08":
                    case "STAT_TODAY_INDEX09":
                    case "STAT_TODAY_INDEX10":
                        if ($newIndex == 1) {
                            $indexNum = intval(substr($infoConso, -2));
                            if (isset($configuredIndexes[$indexNum])) {
                                if (intval($configuredIndexes[$indexNum]['conso']) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour ' . $infoConso . ' ==> ' . intval($configuredIndexes[$indexNum]['conso']) . ' Wh');
                                $cmd->event(intval($configuredIndexes[$indexNum]['conso']));
                            }
                        }
                        break;
                        
                    // Coûts index 01-10
                    case "STAT_TODAY_INDEX01_COUT":
                    case "STAT_TODAY_INDEX02_COUT":
                    case "STAT_TODAY_INDEX03_COUT":
                    case "STAT_TODAY_INDEX04_COUT":
                    case "STAT_TODAY_INDEX05_COUT":
                    case "STAT_TODAY_INDEX06_COUT":
                    case "STAT_TODAY_INDEX07_COUT":
                    case "STAT_TODAY_INDEX08_COUT":
                    case "STAT_TODAY_INDEX09_COUT":
                    case "STAT_TODAY_INDEX10_COUT":
                        if ($newIndex == 1) {
                            $indexNum = intval(substr($infoConso, -7, 2));
                            if (isset($configuredIndexes[$indexNum])) {
                                if (round($configuredIndexes[$indexNum]['cout'], 2) != 0) log::add('teleinfo', 'info', 'Stats : Mise à jour ' . $infoConso . ' ==> ' . round($configuredIndexes[$indexNum]['cout'], 2) . ' €');
                                $cmd->event(round($configuredIndexes[$indexNum]['cout'], 2));
                            }
                        }
                        break;
                        
                    case "TENDANCE_DAY":
                        $tendance = intval($todayValueForTendance) - intval($statYesterday);
                        log::add('teleinfo', 'info', 'Stats : Mise à jour TENDANCE_DAY ==> ' . $tendance . ' Wh (Hier: ' . intval($statYesterday) . ', Aujourd\'hui: ' . intval($todayValueForTendance) . ')');
                        $cmd->event($tendance);
                        break;
                }
            }
        }
        
        log::add('teleinfo', 'info', 'Stats : ---------------------------------------------------------------');
    }

    public static function calculateOtherStats()
    {
        $indexConsoHP = config::byKey('indexConsoHP', 'teleinfo', 'EASF02,EASF04,EASF06,HCHP,BBRHPJB,BBRHPJW,BBRHPJR,EJPHPM');
        $indexConsoHC = config::byKey('indexConsoHC', 'teleinfo', 'EASF01,EASF03,EASF05,HCHC,BBRHCJB,BBRHCJW,BBRHCJR,EJPHN');
        $indexProduction = config::byKey('indexProduction', 'teleinfo', 'EAIT');
        $indexConsoTotales = config::byKey('indexConsoTotales', 'teleinfo', 'BASE,EAST,HCHP,HCHC,BBRHPJB,BBRHPJW,BBRHPJR,BBRHCJB,BBRHCJW,BBRHCJR,EJPHPM,EJPHN');
        
        log::add('teleinfo', 'info', __('------------- Calcul des statistiques de la journée ------------', __FILE__));
        
        foreach (eqLogic::byType('teleinfo') as $eqLogic) {
            $startDay = (new DateTime())->setTimestamp(mktime(0, 0, 0, date("m"), date("d"), date("Y")));
            $endDay = (new DateTime())->setTimestamp(mktime(23, 59, 59, date("m"), date("d"), date("Y")));
            $startDay->sub(new DateInterval('P1D'));
            $endDay->sub(new DateInterval('P1D'));
            
            // Initialisation des variables
            $statYesterdayHc = 0;
            $statYesterdayHp = 0;
            $statYesterdayProd = 0;
            $statYesterdayCoutProd = 0;
            $statYesterdayTotal = 0;
            
            $statHpToCumul = array();
            $statHcToCumul = array();
            $statProdToCumul = array();
            $statTotalToCumul = array();
            
            $prod = intval($eqLogic->getConfiguration('ActivationProduction'));
            
            // Structure pour les index 00-10
            $configuredIndexes = array();
            for ($i = 0; $i <= 10; $i++) {
                $indexNum = str_pad($i, 2, '0', STR_PAD_LEFT);
                $configuredIndexes[$i] = array(
                    'name' => ($i == 0) ? '' : $eqLogic->getConfiguration('index' . $indexNum, ''),
                    'cout_kwh' => floatval($eqLogic->getConfiguration('Coutindex' . $indexNum, 0)),
                    'cmd_id' => null,
                    'cout_cmd_id' => null,
                    'conso' => 0,
                    'cout' => 0
                );
            }
            
            log::add('teleinfo', 'info', 'Stats Journée: --------------------------------------------------');
            log::add('teleinfo', 'info', 'Stats Journée: ----- Compteur : ' . $eqLogic->getName() . ' -----');
            log::add('teleinfo', 'info', 'Stats Journée: --------------------------------------------------');
            
            // Prix de vente production
            $coutIndexProd = 0;
            if ($prod == 1 && floatval($eqLogic->getConfiguration('CoutindexProd')) != 0) {
                $coutIndexProd = floatval($eqLogic->getConfiguration('CoutindexProd'));
                log::add('teleinfo', 'info', 'Stats Journée: EAIT revenus au kWh = ' . $coutIndexProd);
            }
            
            $indexProdCmdId = null;
            
            // Parcourir les commandes pour identification
            foreach ($eqLogic->getCmd('info') as $cmd) {
                $infoConso = $cmd->getConfiguration('info_conso');
                $cmdType = $cmd->getConfiguration('type');
                $logicalId = $cmd->getLogicalId();
                
                // Commandes de données
                if ($cmdType == "data" || $cmdType == "") {
                    if (!empty($infoConso)) {
                        // Catégorisation ancienne méthode
                        if (strpos($indexConsoHP, $infoConso) !== false) {
                            array_push($statHpToCumul, $cmd->getId());
                        }
                        if (strpos($indexConsoHC, $infoConso) !== false) {
                            array_push($statHcToCumul, $cmd->getId());
                        }
                        if (strpos($indexProduction, $infoConso) !== false && $prod != 1) {
                            array_push($statProdToCumul, $cmd->getId());
                        }
                        if (strpos($indexConsoTotales, $infoConso) !== false) {
                            array_push($statTotalToCumul, $cmd->getId());
                        }
                        
                        // Index 00 : BASE ou EAST
                        if ($infoConso == 'BASE' || $infoConso == 'EAST') {
                            $configuredIndexes[0]['name'] = $infoConso;
                            $configuredIndexes[0]['cmd_id'] = $cmd->getId();
                            log::add('teleinfo', 'info', 'Stats Journée: Index 00 --> ' . $infoConso . ' (ID: ' . $cmd->getId() . ')');
                        }
                        
                        // Index 01-10
                        for ($i = 1; $i <= 10; $i++) {
                            if ($infoConso == $configuredIndexes[$i]['name']) {
                                $configuredIndexes[$i]['cmd_id'] = $cmd->getId();
                                log::add('teleinfo', 'debug', 'Stats Journée: Index ' . str_pad($i, 2, '0', STR_PAD_LEFT) . ' détecté (ID: ' . $cmd->getId() . ')');
                            }
                        }
                        
                        // Production EAIT
                        if ($infoConso == 'EAIT' && $prod == 1) {
                            $indexProdCmdId = $cmd->getId();
                            log::add('teleinfo', 'info', 'Stats Journée: EAIT = ' . $indexProdCmdId);
                        }
                    }
                }
                
                // Commandes statistiques (coûts)
                if (preg_match('/^STAT_TODAY_INDEX(\d{2})_COUT$/i', $logicalId, $matches)) {
                    $indexNum = intval($matches[1]);
                    if ($indexNum >= 0 && $indexNum <= 10) {
                        $configuredIndexes[$indexNum]['cout_cmd_id'] = $cmd->getId();
                        log::add('teleinfo', 'debug', 'Stats Journée: Id Cout Index' . str_pad($indexNum, 2, '0', STR_PAD_LEFT) . ' = ' . $cmd->getId());
                    }
                }
            }
            
            // === CALCUL DES CONSOMMATIONS D'HIER ===
            $dateFormatStart = $startDay->format('Y-m-d 00:00:00');
            $dateFormatEnd = $endDay->format('Y-m-d 23:59:59');
            
            // Production
            if ($prod == 1 && $indexProdCmdId !== null) {
                $cmd = cmd::byId($indexProdCmdId);
                if (is_object($cmd)) {
                    $stat = $cmd->getStatistique($dateFormatStart, $dateFormatEnd);
                    $maxStat = isset($stat['max']) ? intval($stat['max']) : 0;
                    $minStat = isset($stat['min']) ? intval($stat['min']) : 0;
                    $statYesterdayProd = max(0, $maxStat - $minStat);
                    $statYesterdayCoutProd = $statYesterdayProd * $coutIndexProd / 1000;
                    log::add('teleinfo', 'info', 'Stats Journée: Total PROD hier --> ' . $statYesterdayProd . ' revenus générés : ' . $statYesterdayCoutProd);
                }
            }
            
            // Index 00-10
            $statYesterdayTotalIndex00 = 0;
            $statYesterdayCoutTotalIndex00 = 0;
            
            for ($i = 0; $i <= 10; $i++) {
                $cmdId = $configuredIndexes[$i]['cmd_id'];
                $coutCmdId = $configuredIndexes[$i]['cout_cmd_id'];
                
                // Calcul consommation
                if ($cmdId !== null) {
                    $cmd = cmd::byId($cmdId);
                    if (is_object($cmd)) {
                        $stat = $cmd->getStatistique($dateFormatStart, $dateFormatEnd);
                        $max = isset($stat['max']) ? intval($stat['max']) : 0;
                        $min = isset($stat['min']) ? intval($stat['min']) : 0;
                        $conso = max(0, $max - $min);
                        $configuredIndexes[$i]['conso'] = $conso;
                    }
                }
                
                // Calcul coût : priorité à l'historique, sinon calcul direct
                if ($coutCmdId !== null) {
                    $cmd = cmd::byId($coutCmdId);
                    if (is_object($cmd)) {
                        $stat = $cmd->getStatistique($dateFormatStart, $dateFormatEnd);
                        $cout = isset($stat['max']) ? floatval($stat['max']) : 0;
                        $configuredIndexes[$i]['cout'] = $cout;
                    }
                } elseif ($configuredIndexes[$i]['cout_kwh'] > 0 && $configuredIndexes[$i]['conso'] > 0) {
                    $configuredIndexes[$i]['cout'] = $configuredIndexes[$i]['conso'] * $configuredIndexes[$i]['cout_kwh'] / 1000;
                }
                
                // Cumul pour index00 (somme des index 01-10)
                if ($i > 0) {
                    $statYesterdayTotalIndex00 += $configuredIndexes[$i]['conso'];
                    $statYesterdayCoutTotalIndex00 += $configuredIndexes[$i]['cout'];
                }
            }
            
            // Si index00 direct (BASE/EAST) existe, l'utiliser
            if ($configuredIndexes[0]['cmd_id'] !== null && $configuredIndexes[0]['conso'] > 0) {
                $statYesterdayTotalIndex00 = $configuredIndexes[0]['conso'];
            }
            if ($configuredIndexes[0]['cout'] > 0) {
                $statYesterdayCoutTotalIndex00 = $configuredIndexes[0]['cout'];
            }
            
            // Fonction utilitaire
            $calculateConsoFromList = function($cmdIds) use ($dateFormatStart, $dateFormatEnd) {
                $total = 0;
                foreach ($cmdIds as $cmdId) {
                    $cmd = cmd::byId($cmdId);
                    if (is_object($cmd)) {
                        $stat = $cmd->getStatistique($dateFormatStart, $dateFormatEnd);
                        $max = isset($stat['max']) ? intval($stat['max']) : 0;
                        $min = isset($stat['min']) ? intval($stat['min']) : 0;
                        $total += max(0, $max - $min);
                    }
                }
                return $total;
            };
            
            // Calcul HP/HC/Total/Prod (ancienne méthode)
            $statYesterdayHp = $calculateConsoFromList($statHpToCumul);
            $statYesterdayHc = $calculateConsoFromList($statHcToCumul);
            $statYesterdayTotal = $calculateConsoFromList($statTotalToCumul);
            
            log::add('teleinfo', 'debug', 'Stats Journée: Total HP hier: ' . $statYesterdayHp . ' Wh');
            log::add('teleinfo', 'debug', 'Stats Journée: Total HC hier: ' . $statYesterdayHc . ' Wh');
            log::add('teleinfo', 'debug', 'Stats Journée: Total Conso hier: ' . $statYesterdayTotal . ' Wh');
            
            if ($prod != 1) {
                $statYesterdayProd = $calculateConsoFromList($statProdToCumul);
                log::add('teleinfo', 'info', 'Stats Journée: Total Prod hier: ' . $statYesterdayProd . ' Wh');
            }
            
            // === MISE À JOUR DES COMMANDES STATISTIQUES ===
            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getConfiguration('type') != "stat" && $cmd->getConfiguration('type') != "panel") {
                    continue;
                }
                
                $infoConso = $cmd->getConfiguration('info_conso');
                $eventDate = $startDay->format('Y-m-d 00:00:00');
                
                switch ($infoConso) {
                    case "STAT_YESTERDAY":
                        if (intval($statYesterdayTotal) != 0) log::add('teleinfo', 'info', 'Stats Journée: Mise à jour STAT_YESTERDAY ==> ' . intval($statYesterdayTotal));
                        $cmd->event(intval($statYesterdayTotal), $eventDate);
                        break;
                        
                    case "STAT_YESTERDAY_HP":
                        if (intval($statYesterdayHp) != 0) log::add('teleinfo', 'info', 'Stats Journée: Mise à jour STAT_YESTERDAY_HP ==> ' . intval($statYesterdayHp));
                        $cmd->event(intval($statYesterdayHp), $eventDate);
                        break;
                        
                    case "STAT_YESTERDAY_HC":
                        if (intval($statYesterdayHc) != 0) log::add('teleinfo', 'info', 'Stats Journée: Mise à jour STAT_YESTERDAY_HC ==> ' . intval($statYesterdayHc));
                        $cmd->event(intval($statYesterdayHc), $eventDate);
                        break;
                        
                    case "STAT_YESTERDAY_PROD":
                        if (intval($statYesterdayProd) != 0) log::add('teleinfo', 'info', 'Stats Journée: Mise à jour STAT_YESTERDAY_PROD ==> ' . intval($statYesterdayProd));
                        $cmd->event(intval($statYesterdayProd), $eventDate);
                        break;
                        
                    case "STAT_YESTERDAY_PROD_COUT":
                        if (intval($statYesterdayCoutProd) != 0) log::add('teleinfo', 'info', 'Stats Journée: Mise à jour STAT_YESTERDAY_PROD_COUT ==> ' . floatval($statYesterdayCoutProd));
                        $cmd->event(floatval($statYesterdayCoutProd), $eventDate);
                        break;
                        
                    case "STAT_YESTERDAY_INDEX00":
                        if (intval($statYesterdayTotalIndex00) != 0) log::add('teleinfo', 'info', 'Stats Journée: Mise à jour STAT_YESTERDAY_INDEX00 ==> ' . intval($statYesterdayTotalIndex00));
                        $cmd->event(intval($statYesterdayTotalIndex00), $eventDate);
                        break;
                        
                    case "STAT_YESTERDAY_INDEX00_COUT":
                        if (intval($statYesterdayCoutTotalIndex00) != 0) log::add('teleinfo', 'info', 'Stats Journée: Mise à jour STAT_YESTERDAY_INDEX00_COUT ==> ' . floatval($statYesterdayCoutTotalIndex00));
                        $cmd->event(floatval($statYesterdayCoutTotalIndex00), $eventDate);
                        break;
                }
                
                // Index 01-10 (conso)
                if (preg_match('/^STAT_YESTERDAY_INDEX(\d{2})$/i', $infoConso, $matches)) {
                    $indexNum = intval($matches[1]);
                    if ($indexNum >= 1 && $indexNum <= 10) {
                        $value = intval($configuredIndexes[$indexNum]['conso']);
                        if (intval($value) != 0) log::add('teleinfo', 'info', 'Stats Journée: Mise à jour STAT_YESTERDAY_INDEX' . str_pad($indexNum, 2, '0', STR_PAD_LEFT) . ' ==> ' . $value);
                        $cmd->event($value, $eventDate);
                    }
                }
                
                // Index 01-10 (coût)
                if (preg_match('/^STAT_YESTERDAY_INDEX(\d{2})_COUT$/i', $infoConso, $matches)) {
                    $indexNum = intval($matches[1]);
                    if ($indexNum >= 1 && $indexNum <= 10) {
                        $value = floatval($configuredIndexes[$indexNum]['cout']);
                        if (intval($value) != 0) log::add('teleinfo', 'info', 'Stats Journée: Mise à jour STAT_YESTERDAY_INDEX' . str_pad($indexNum, 2, '0', STR_PAD_LEFT) . '_COUT ==> ' . $value);
                        $cmd->event($value, $eventDate);
                    }
                }
            }
        }
        
        log::add('teleinfo', 'info', '----------------------------------------------------');
    }

    public static function cleanDBTeleinfo()
    {
        log::add('teleinfo_clean', 'info', "Début de l'opération de nettoyage de la base de données.");

        foreach (eqLogic::byType('teleinfo') as $eqLogic) {
            if ($eqLogic->getConfiguration('cleanDBTeleinfo') != 1) {
                log::add('teleinfo_clean', 'info', 'Compteur ' . $eqLogic->getName() . ' nettoyage auto non programmé');
                continue;
            }

            log::add('teleinfo_clean', 'info', 'Nettoyage compteur ' . $eqLogic->getName());

            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getIsHistorized() != 1) {
                    continue;
                }

                $logicalId  = $cmd->getLogicalId();
                $donneeType = $cmd->getConfiguration('type');
                $cmdId      = $cmd->getId();

                // Comptages
                $countSql = "SELECT COUNT(*) as total FROM historyArch WHERE cmd_id = :cmdId";
                $countResult = DB::Prepare($countSql, ['cmdId' => $cmdId], DB::FETCH_TYPE_ROW);
                $valeursDepart = intval($countResult['total'] ?? 0);

                $countDeleteSql = "SELECT COUNT(*) as total FROM historyArch
                                WHERE cmd_id = :cmdId
                                AND MINUTE(datetime) <> 0
                                AND NOT (HOUR(datetime) = 23 AND MINUTE(datetime) = 59)";
                $countDeleteResult = DB::Prepare($countDeleteSql, ['cmdId' => $cmdId], DB::FETCH_TYPE_ROW);
                $valeursEffacer = intval($countDeleteResult['total'] ?? 0);

                log::add('teleinfo_clean', 'info', sprintf(
                    __("Commande %s : %d enregistrements, %d à nettoyer", __FILE__),
                    $logicalId, $valeursDepart, $valeursEffacer
                ));

                if ($valeursEffacer <= 1000) {
                    log::add('teleinfo_clean', 'info', __('Pas assez de données à supprimer => au suivant...', __FILE__));
                    continue;
                }

                // Ignorer les autres types STAT_
                $isStatYesterday = (strpos($logicalId, 'STAT_YESTERDAY') === 0);
                $isStatToday     = (strpos($logicalId, 'STAT_TODAY') === 0);
                if (strpos($logicalId, 'STAT_') === 0 && !$isStatYesterday && !$isStatToday) {
                    log::add('teleinfo_clean', 'info', __('Commande STAT_ non gérée, ignorée : ', __FILE__) . $logicalId);
                    continue;
                }

                log::add('teleinfo_clean', 'info', sprintf(
                    __("Optimisation de l'historique de %s, cela peut prendre du temps.", __FILE__),
                    $logicalId
                ));

                $isAvg = ($donneeType === 'AVG');

                // Sélection des données à conserver
                if ($isStatYesterday) {
                    $selectSql = "SELECT cmd_id,
                                        DATE_FORMAT(datetime, '%Y-%m-%d 00:00:00') as datetime,
                                        MAX(value) as value
                                FROM historyArch
                                WHERE cmd_id = :cmdId
                                GROUP BY YEAR(datetime), MONTH(datetime), DAY(datetime)";
                } elseif ($isStatToday) {
                    $selectSql = "SELECT cmd_id,
                                        DATE_FORMAT(datetime, '%Y-%m-%d %H:00:00') as datetime,
                                        MAX(value) as value
                                FROM historyArch
                                WHERE cmd_id = :cmdId
                                GROUP BY YEAR(datetime), MONTH(datetime), DAY(datetime), HOUR(datetime)";
                } else {
                    if ($isAvg) {
                        $selectSql = "SELECT cmd_id,
                                            DATE_FORMAT(FROM_UNIXTIME(AVG(UNIX_TIMESTAMP(datetime))), '%Y-%m-%d %H:00:00') as datetime,
                                            CAST(AVG(value) AS DECIMAL(12,2)) as value
                                    FROM historyArch
                                    WHERE cmd_id = :cmdId
                                    GROUP BY YEAR(datetime), MONTH(datetime), DAY(datetime), HOUR(datetime)";
                    } else {
                        // première valeur de chaque heure
                        $selectSql = "SELECT h.cmd_id,
                                            DATE_FORMAT(h.datetime, '%Y-%m-%d %H:00:00') as datetime,
                                            h.value
                                    FROM historyArch h
                                    INNER JOIN (
                                        SELECT MIN(datetime) as first_dt
                                        FROM historyArch
                                        WHERE cmd_id = :cmdId
                                        GROUP BY YEAR(datetime), MONTH(datetime), DAY(datetime), HOUR(datetime)
                                    ) first ON h.datetime = first.first_dt
                                    WHERE h.cmd_id = :cmdId";
                    }
                }

                $dataToKeep = DB::Prepare($selectSql, ['cmdId' => $cmdId], DB::FETCH_TYPE_ALL);

                // Max quotidiens à 23:59:59 (uniquement pour les index normaux)
                $maxJournee = [];
                if (!$isStatYesterday && !$isStatToday && !$isAvg) {
                    $maxSql = "SELECT cmd_id,
                                    DATE_FORMAT(datetime, '%Y-%m-%d 23:59:59') as datetime,
                                    MAX(value) as value
                            FROM historyArch
                            WHERE cmd_id = :cmdId 
                                AND datetime < DATE(NOW())
                            GROUP BY YEAR(datetime), MONTH(datetime), DAY(datetime)";
                    $maxJournee = DB::Prepare($maxSql, ['cmdId' => $cmdId], DB::FETCH_TYPE_ALL);
                }

                log::add('teleinfo_clean', 'info', __('Données récupérées, suppression / réinsertion en cours...', __FILE__));

                $pdo = DB::getConnection();
                $pdo->beginTransaction();

                try {
                    $deleteSql = "DELETE FROM historyArch WHERE cmd_id = :cmdId";
                    DB::Prepare($deleteSql, ['cmdId' => $cmdId], DB::FETCH_TYPE_ROW);

                    $totalInserted = self::bulkInsertHistory($dataToKeep);

                    if (!empty($maxJournee)) {
                        $totalInserted += self::bulkInsertHistory($maxJournee);
                    }

                    $pdo->commit();

                    log::add('teleinfo_clean', 'info', sprintf(
                        __('Optimisation terminée pour %s → %d lignes avant, %d lignes après.', __FILE__),
                        $logicalId, $valeursDepart, $totalInserted
                    ));

                } catch (Exception $e) {
                    $pdo->rollBack();
                    log::add('teleinfo_clean', 'error', sprintf(
                        __('Erreur pendant le nettoyage de %s : %s', __FILE__), $logicalId, $e->getMessage()
                    ));
                }
            }


        }

        log::add('teleinfo_clean', 'info', __("Fin de l'opération de nettoyage de la base de données.", __FILE__));
    }

    /**
     * Fonction pour insertion en masse
     */
    public static function bulkInsertHistory($data)
    {
        if (empty($data)) {
            return 0;
        }

        $totalInserted = 0;
        $chunks = array_chunk($data, 500);

        foreach ($chunks as $chunk) {
            $values = [];
            $params = [];
            $i = 0;

            foreach ($chunk as $row) {
                $cmdParam = ":cmd_{$i}";
                $dtParam  = ":dt_{$i}";
                $valParam = ":val_{$i}";

                $values[] = "($cmdParam, $dtParam, $valParam)";

                $params[$cmdParam] = $row['cmd_id'];
                $params[$dtParam]  = $row['datetime'];
                $params[$valParam] = $row['value'];
                $i++;
            }

            $insertSql = "REPLACE INTO historyArch (cmd_id, datetime, value) VALUES " . implode(', ', $values);
            DB::Prepare($insertSql, $params, DB::FETCH_TYPE_ROW);

            $totalInserted += count($chunk);
        }

        return $totalInserted;
    }

    public static function copyVersIndex($compteur, $startDate, $endDate,
        $indexcopy01, $indexcopy02, $indexcopy03, $indexcopy04, $indexcopy05, $indexcopy06, $indexcopy07, $indexcopy08, $indexcopy09, $indexcopy10,
        $coutcopy00, $coutcopy01, $coutcopy02, $coutcopy03, $coutcopy04, $coutcopy05, $coutcopy06, $coutcopy07, $coutcopy08, $coutcopy09, $coutcopy10, $coutcopyprod)
    {

        $dateStart = new DateTime($startDate);
        $dateEnd   = new DateTime($endDate);
        $dateEnd->setTime(23, 59, 59);

        $nbJours = (int)$dateStart->diff($dateEnd)->days + 1;

        event::add('jeedom::alert', [
            'level' => 'warning',
            'page'  => 'teleinfo',
            'message' => sprintf(__('Copie des données vers les nouveaux index (%s au %s) — %d jours à traiter...', __FILE__), $startDate, $endDate, $nbJours)
        ]);

        log::add('teleinfo', 'info', sprintf(__("Démarrage copyVersIndex pour le compteur {$compteur} du {$startDate} au {$endDate}", __FILE__)));        

        $indexCopyNames = ['', $indexcopy01, $indexcopy02, $indexcopy03, $indexcopy04, $indexcopy05,
                        $indexcopy06, $indexcopy07, $indexcopy08, $indexcopy09, $indexcopy10, 'EAIT'];

        $coutValues = [$coutcopy00, $coutcopy01, $coutcopy02, $coutcopy03, $coutcopy04, $coutcopy05,
                    $coutcopy06, $coutcopy07, $coutcopy08, $coutcopy09, $coutcopy10, $coutcopyprod];

        $hasCout00 = (!empty($coutValues[0]) && $coutValues[0] != 0);
        $progressStep = max(2, (int)($nbJours / 10));  // ✅ Min 2 pour éviter spam

        foreach (eqLogic::byType('teleinfo') as $eqLogic) {
            if ($compteur !== $eqLogic->getLogicalId()) continue;

            $newIndex = intval($eqLogic->getConfiguration('newIndex',0));
            if ($newIndex == 0) {
                event::add('jeedom::alert', [
                    'level' => 'warning',
                    'page'  => 'teleinfo',
                    'message' => sprintf(__("Nouveaux index non utilisés pour le compteur {$compteur} => traitement arrété", __FILE__))
                ]);
                log::add('teleinfo', 'info', "Nouveaux index non utilisés pour le compteur {$compteur} => copyVersIndex stoppé");
                return;
            }

            // Préparation des destinations
            $destIndexIds = [];
            $destCoutIds  = [];
            for ($i = 0; $i <= 11; $i++) {
                $suffix = ($i === 11) ? 'PROD' : sprintf('INDEX%02d', $i);
                $destCmd = $eqLogic->getCmd('info', 'STAT_YESTERDAY_' . $suffix);
                $destCoutCmd = $eqLogic->getCmd('info', 'STAT_YESTERDAY_' . $suffix . '_COUT');

                $destIndexIds[$i] = $destCmd ? $destCmd->getId() : null;
                $destCoutIds[$i]  = $destCoutCmd ? $destCoutCmd->getId() : null;
            }

            // Cache des commandes sources
            $sourceCmds = [];
            for ($j = 1; $j <= 11; $j++) {
                $name = ($j === 11) ? 'EAIT' : $indexCopyNames[$j];
                if (!empty($name)) {
                    $cmd = $eqLogic->getCmd('info', $name);
                    if ($cmd) {
                        $sourceCmds[$j] = $cmd;
                    }
                }
            }

            $cmdBase = $eqLogic->getCmd('info', 'BASE');
            $cmdEast = $eqLogic->getCmd('info', 'EAST');

            $dataToSave = [];
            $currentDate = clone $dateEnd;

            for ($i = 0; $i < $nbJours; $i++) {
                // Progression (~10%)
                if ($nbJours > 10 && $i > 0 && $i % $progressStep === 0) {
                    $percent = (int)(($i + 1) * 100 / $nbJours);
                    event::add('jeedom::alert', [
                        'level' => 'warning',
                        'page'  => 'teleinfo',
                        'message' => sprintf(__('Traitement en cours... %d%%', __FILE__), $percent)
                    ]);
                }

                $dayStart = $currentDate->format('Y-m-d 00:00:00');
                $dayEnd   = $currentDate->format('Y-m-d 23:59:59');

                // BASE + EAST → INDEX00
                $valBase = self::getDayDiff($cmdBase, $dayStart, $dayEnd);
                $valEast = self::getDayDiff($cmdEast, $dayStart, $dayEnd);
                $total00 = $valBase + $valEast;

                if ($total00 > 0 && $destIndexIds[0]) {
                    $dataToSave[] = ['cmd_id' => $destIndexIds[0], 'datetime' => $dayStart, 'value' => $total00];
                    if ($hasCout00 && $destCoutIds[0]) {
                        $dataToSave[] = ['cmd_id' => $destCoutIds[0], 'datetime' => $dayStart, 'value' => round($total00 * $coutValues[0] / 1000, 4)];
                    }
                }

                // INDEX 01-10 + PROD
                $sumFallback = 0;
                $sumCoutFallback = 0.0;

                for ($j = 1; $j <= 11; $j++) {
                    if (!isset($sourceCmds[$j])) continue;

                    $val = self::getDayDiff($sourceCmds[$j], $dayStart, $dayEnd);

                    if ($val > 0 && $destIndexIds[$j]) {
                        $dataToSave[] = ['cmd_id' => $destIndexIds[$j], 'datetime' => $dayStart, 'value' => $val];
                        $sumFallback += $val;

                        if (!empty($coutValues[$j]) && $coutValues[$j] != 0 && $destCoutIds[$j]) {
                            $cout = round($val * $coutValues[$j] / 1000, 4);
                            $dataToSave[] = ['cmd_id' => $destCoutIds[$j], 'datetime' => $dayStart, 'value' => $cout];
                            $sumCoutFallback += $cout;
                        }
                    }
                }

                // Fallback INDEX00
                if ($total00 <= 0 && $sumFallback > 0 && $destIndexIds[0]) {
                    $dataToSave[] = ['cmd_id' => $destIndexIds[0], 'datetime' => $dayStart, 'value' => $sumFallback];
                    if ($sumCoutFallback > 0 && $destCoutIds[0]) {
                        $dataToSave[] = ['cmd_id' => $destCoutIds[0], 'datetime' => $dayStart, 'value' => $sumCoutFallback];
                    }
                }

                $currentDate->sub(new DateInterval('P1D'));
            }

            // Insertion en masse
            if (!empty($dataToSave)) {
                self::bulkInsertHistory($dataToSave);
            }

            // Nettoyage final
            $allIds = array_filter(array_merge($destIndexIds, $destCoutIds));
            if (!empty($allIds)) {
                $placeholders = implode(',', array_fill(0, count($allIds), '?'));
                $sql = "DELETE FROM historyArch WHERE cmd_id IN ($placeholders) AND (value IS NULL OR value = 0 OR value = ' ')";
                DB::Prepare($sql, array_values($allIds), DB::FETCH_TYPE_ROW);
            }
        }

        event::add('jeedom::alert', [
            'level' => 'success',
            'page'  => 'teleinfo',
            'message' => __('Les index ont bien été constitués.', __FILE__)
        ]);
    }

    /**
     * Calcule la différence Max - Min pour une commande sur une période via SQL direct
     */
    private static function getDayDiff($cmd, $start, $end) {
        if (!is_object($cmd)) return 0;
        $sql = "SELECT MAX(value) - MIN(value) as diff FROM historyArch WHERE cmd_id = :id AND datetime >= :start AND datetime <= :end";
        $res = DB::Prepare($sql, ['id' => $cmd->getId(), 'start' => $start, 'end' => $end], DB::FETCH_TYPE_ROW);
        return floatval($res['diff'] ?? 0);
    }

    public static function sauveCmd($id) {
        $return = array('erreur' => 'nOk');
        $eqLogic = eqLogic::byId($id);
        
        if (!is_object($eqLogic)) {
            return $return;
        }

        log::add('teleinfo', 'info', sprintf(__("[TELEINFO] Début sauvegarde de l'équipement : %s", __FILE__), $eqLogic->getName()));

        $indexSauve = array('BASE','EAST','EASF01','EASF03','EASF05','HCHC','BBRHCJB','BBRHCJW','BBRHCJR','EJPHN','EASF02','EASF04','EASF06','HCHP','BBRHPJB','BBRHPJW','BBRHPJR','EJPHPM','EAIT');
        
        $dir = __DIR__ . '/../../sauvegarde/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // On nettoie le nom de l'équipement pour le système de fichiers
        $cleanEqName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $eqLogic->getName());
        $dateSuffix = date('Y-m-d');

        foreach ($indexSauve as $sauve) {
            try {
                $cmd = $eqLogic->getCmd('info', $sauve);
                if (is_object($cmd)) {
                    $cmdId = $cmd->getId();
                    
                    // On récupère tout de historyArch ET tout de history pour cet ID
                    $sql = "SELECT cmd_id, datetime, value FROM historyArch WHERE cmd_id=:cmdId 
                            UNION 
                            SELECT cmd_id, datetime, value FROM history WHERE cmd_id=:cmdId 
                            ORDER BY datetime ASC";
                    
                    $querys = DB::Prepare($sql, array('cmdId' => $cmdId), DB::FETCH_TYPE_ALL);

                    if (count($querys) == 0) continue; 

                    $fileName = 'sauvegarde_equipement-' . $cleanEqName . '_index-' . $sauve . '_le-' . $dateSuffix . '.csv';
                    $fullPath = $dir . $fileName;

                    $f = fopen($fullPath, 'w');
                    if ($f) {
                        fputcsv($f, array('cmd_id', 'datetime', 'value'), ',');
                        foreach ($querys as $query) {
                            fputcsv($f, array($query['cmd_id'], $query['datetime'], $query['value']), ',');
                        }
                        fclose($f);
                        log::add('teleinfo', 'info', "[TELEINFO] Fichier complet créé (History + Arch) : " . $fileName);
                    }
                }
            } catch (\Exception $e) {
                log::add('teleinfo', 'error', "[TELEINFO] Erreur index $sauve : " . $e->getMessage());
            }
        }

        $return['erreur'] = 'ok';
        return $return;
    }

    public static function regenerateMonthlyStat(){
        cache::set('teleinfo::regenerateMonthlyStat', '1', 86400);
        $indexConsoHP      = config::byKey('indexConsoHP', 'teleinfo', 'EASF02,EASF04,EASF06,HCHP,BBRHPJB,BBRHPJW,BBRHPJR,EJPHPM');
        $indexConsoHC      = config::byKey('indexConsoHC', 'teleinfo', 'EASF01,EASF03,EASF05,HCHC,BBRHCJB,BBRHCJW,BBRHCJR,EJPHN');
        $indexProduction   = config::byKey('indexProduction', 'teleinfo', 'EAIT');
        $indexConsoTotales   = config::byKey('indexConsoTotales', 'teleinfo', 'BASE,EAST,HCHP,HCHC,BBRHPJB,BBRHPJW,BBRHPJR,BBRHCJB,BBRHCJW,BBRHCJR,EJPHPM,EJPHN');
        event::add('jeedom::alert', array(
				'level' => 'warning',
				'page' => 'teleinfo',
				'message' => __('Les statistiques sont en cours de regénérations, cela peut prendre un peu de temps veuillez patienter ...', __FILE__),
		));
        foreach (eqLogic::byType('teleinfo') as $eqLogic) {
            $startDay = (new DateTime())->setTimestamp(mktime(0, 0, 0, date("m"), date("d"), date("Y")));
            $endDay   = (new DateTime())->setTimestamp(mktime(23, 59, 59, date("m"), date("d"), date("Y")));
            $statHpToCumul       = array();
            $statHcToCumul       = array();
            $statProdToCumul     = array();
            $statTotalToCumul    = array();
            $statTotal           = 0;

            try{
                $cmdYesterdayHP     = $eqLogic->getCmd('info', 'STAT_YESTERDAY_HP');
                $cmdYesterdayHC     = $eqLogic->getCmd('info', 'STAT_YESTERDAY_HC');
                $cmdYesterdayProd   = $eqLogic->getCmd('info', 'STAT_YESTERDAY_PROD');
                $cmdYesterdayTotal     = $eqLogic->getCmd('info', 'STAT_YESTERDAY');
                $sql = "DELETE FROM historyArch WHERE (cmd_id=:cmdIdHP OR cmd_id=:cmdIdHC OR cmd_id=:cmdIdPROD OR cmd_id=:cmdIdTotal) AND MINUTE(datetime) <> '0'";
                $values = array(
                    'cmdIdHP' => $cmdYesterdayHP->getId(),
                    'cmdIdHC' => $cmdYesterdayHC->getId(),
                    'cmdIdPROD' => $cmdYesterdayProd->getId(),
					'cmdIdTotal' => $cmdYesterdayTotal->getId(),
                );
				$sql = "DELETE FROM historyArch WHERE (cmd_id=:cmdIdHP OR cmd_id=:cmdIdHC OR cmd_id=:cmdIdPROD OR cmd_id=:cmdIdTotal) AND SECOND(datetime) <> '0'";
				DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
            } catch (\Exception $e) {
                log::add('teleinfo', 'error', '[TELEINFO]-----' . $e) ;
            }

            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getConfiguration('type') == "data" || $cmd->getConfiguration('type') == "") {
                    if (strpos($indexConsoHP, $cmd->getConfiguration('info_conso')) !== false) {
                        array_push($statHpToCumul, $cmd->getId());
                    }
                    if (strpos($indexConsoHC, $cmd->getConfiguration('info_conso')) !== false) {
                        array_push($statHcToCumul, $cmd->getId());
                    }
                    if (strpos($indexConsoTotales, $cmd->getConfiguration('info_conso')) !== false) {
                        array_push($statTotalToCumul, $cmd->getId());
                    }
                    if (strpos($indexProduction, $cmd->getConfiguration('info_conso')) !== false) {
                        array_push($statProdToCumul, $cmd->getId());
                    }
                }
            }

            for($i=1; $i < 730; $i++){
                $statHc     = 0;
                $statHp     = 0;
                $statProd   = 0;
                $startDay->sub(new DateInterval('P1D'));
                $endDay->sub(new DateInterval('P1D'));

                if (($i % 40) == 0){
                    event::add('jeedom::alert', array(
                            'level' => 'warning',
                            'page' => 'teleinfo',
                            'message' => sprintf(__('Les statistiques sont en cours de regénérations, cela peut prendre un peu de temps veuillez patienter ... (%s %)', __FILE__), intval($i/7.3)),
                    ));
                }


                foreach ($statTotalToCumul as $key => $value) {
                    $cmd    = cmd::byId($value);
                    $statTotal += intval($cmd->getStatistique($startDay->format('Y-m-d H:i:s'), $endDay->format('Y-m-d H:i:s'))['max']) - intval($cmd->getStatistique($startDay->format('Y-m-d H:i:s'), $endDay->format('Y-m-d H:i:s'))['min']);
                }
                foreach ($statHcToCumul as $key => $value) {
                    $cmd    = cmd::byId($value);
                    $statHc += intval($cmd->getStatistique($startDay->format('Y-m-d H:i:s'), $endDay->format('Y-m-d H:i:s'))['max']) - intval($cmd->getStatistique($startDay->format('Y-m-d H:i:s'), $endDay->format('Y-m-d H:i:s'))['min']);
                }
                foreach ($statHpToCumul as $key => $value) {
                    $cmd    = cmd::byId($value);
                    $statHp += intval($cmd->getStatistique($startDay->format('Y-m-d H:i:s'), $endDay->format('Y-m-d H:i:s'))['max']) - intval($cmd->getStatistique($startDay->format('Y-m-d H:i:s'), $endDay->format('Y-m-d H:i:s'))['min']);
                }

                foreach ($statProdToCumul as $key => $value) {
                    $cmd        = cmd::byId($value);
                    $statProd 	+= intval($cmd->getStatistique($startDay->format('Y-m-d H:i:s'), $endDay->format('Y-m-d H:i:s'))['max']) - intval($cmd->getStatistique($startDay->format('Y-m-d H:i:s'), $endDay->format('Y-m-d H:i:s'))['min']);
                }

                foreach ($eqLogic->getCmd('info') as $cmd) {
                    if ($cmd->getConfiguration('type') == "stat" || $cmd->getConfiguration('type') == "panel") {
                        $history = new history();
                        $history->setCmd_id($cmd->getId());
                        $history->setDatetime($startDay->format('Y-m-d 00:00:00'));
                        $history->setTableName('historyArch');
                        switch ($cmd->getConfiguration('info_conso')) {
                            case "STAT_YESTERDAY_HP":
                                log::add('teleinfo', 'debug', __('Mise à jour de la statistique HP   ==>', __FILE__) . ' ' . $startDay->format('Y-m-d') . " / Valeur : " . intval($statHp)) ;
                                $history->setValue(intval($statHp));
                                $history->save();
                                break;
                            case "STAT_YESTERDAY_HC":
                                log::add('teleinfo', 'debug', __('Mise à jour de la statistique HC   ==>', __FILE__) . ' ' . $startDay->format('Y-m-d') . " / Valeur : " . intval($statHc)) ;
                                $history->setValue(intval($statHc));
                                $history->save();
                                break;
                            case "STAT_YESTERDAY_PROD":
                                log::add('teleinfo', 'debug', __('Mise à jour de la statistique PROD ==>', __FILE__) . ' ' . $startDay->format('Y-m-d') . " / Valeur : " . intval($statProd)) ;
                                $history->setValue(intval($statProd));
                                $history->save();
                                break;
                            case "STAT_YESTERDAY":
                                log::add('teleinfo', 'debug', __('Mise à jour de la statistique HIER ==>', __FILE__) . ' ' . $startDay->format('Y-m-d') . " / Valeur : " . intval($statTotal)) ;
                                $history->setValue(intval($statTotal));
                                $history->save();
                                break;
                        }
                    }
                }

            }
        }
        event::add('jeedom::alert', array(
				'level' => 'success',
				'page' => 'teleinfo',
				'message' => __('Les statistiques ont étés regénérés.', __FILE__),
		));
    }

    public static function moyLastHour()
    {
        $indexConsoHP = config::byKey('indexConsoHP', 'teleinfo', 'BASE,HCHP,EASF02,EASF04,EASF06,BBRHPJB,BBRHPJW,BBRHPJR,EJPHPM');
        $indexConsoHC = config::byKey('indexConsoHC', 'teleinfo', 'HCHC,EASF01,EASF03,EASF05,BBRHCJB,BBRHCJW,BBRHCJR,EJPHN');
        log::add('teleinfo', 'debug', 'moylasthour ');

        foreach (eqLogic::byType('teleinfo') as $eqLogic) {
            $cptId = strval($eqLogic->getId());
            $ppapHp  = 0;
            $ppapHc  = 0;
            $cmdPpap = null;
            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getConfiguration('type') == 'stat') {
                    if ($cmd->getConfiguration('info_conso') == 'STAT_MOY_LAST_HOUR') {
                        log::add('teleinfo', 'debug', __('----- Calcul de la consommation moyenne sur la dernière heure -----', __FILE__));
                        $cmdPpap = $cmd;
                    }
                }
            }
            if ($cmdPpap !== null) {
                foreach ($eqLogic->getCmd('info') as $cmd) {
                    if ($cmd->getConfiguration('type') == "data" || $cmd->getConfiguration('type') == "") {
                        if (strpos($indexConsoHP, $cmd->getConfiguration('info_conso')) !== false) {
                            $ppapHp += $cmd->execCmd();
                            log::add('teleinfo', 'debug', 'Compteur ID ' . $cptId . ' Cmd HP : ' . $cmd->getId() . ' ( ' . $cmd->getName() . ' ) / Value : ' . $cmd->execCmd());
                        }
                        if (strpos($indexConsoHC, $cmd->getConfiguration('info_conso')) !== false) {
                            $ppapHc += $cmd->execCmd();
                            log::add('teleinfo', 'debug', 'Compteur ID ' . $cptId . ' Cmd HC : ' . $cmd->getId() . ' ( ' . $cmd->getName() . ' ) / Value : ' . $cmd->execCmd());
                        }
                    }
                }

                $cacheHcLast = cache::byKey('teleinfo::stat_moy_last_hour::hc' . $cptId, false);
                $cacheHpLast = cache::byKey('teleinfo::stat_moy_last_hour::hp' . $cptId, false);

                // Si le cache n'existe pas ou est vide, on initialise à 0
                $cacheHc = is_object($cacheHcLast) ? $cacheHcLast->getValue() : 0;
                $cacheHp = is_object($cacheHpLast) ? $cacheHpLast->getValue() : 0;

                // Forcer la conversion en integer au cas où le cache contiendrait une chaîne vide
                $cacheHc = intval($cacheHc);
                $cacheHp = intval($cacheHp);
              
                log::add('teleinfo', 'debug', 'Compteur ID ' . $cptId . ' Cache HP : ' . strval($cacheHp)  . ' / Valeur PPAP HP : ' . strval($ppapHp));
                log::add('teleinfo', 'debug', 'Compteur ID ' . $cptId . ' Cache HC : ' . strval($cacheHc) . ' / Valeur PPAP HC : ' . strval($ppapHc));

                $consoWh = intval(($ppapHp - $cacheHp) + ($ppapHc - $cacheHc));
                log::add('teleinfo', 'debug', 'Compteur ID ' . $cptId . ' Conso Wh : ' . strval($consoWh));

                $cmdPpap->event($consoWh);

                cache::set('teleinfo::stat_moy_last_hour::hc' . $cptId, $ppapHc, 7200);
                cache::set('teleinfo::stat_moy_last_hour::hp' . $cptId, $ppapHp, 7200);
            }
			else {
                log::add('teleinfo', 'debug', sprintf(__('Compteur ID %s => Pas de calcul', __FILE__) , $cptId));
            }
        }
    }

    public static function calculatePAPP()
    {
        log::add('teleinfo', 'debug', 'calculatepapp ');
        $indexConsoHP = config::byKey('indexConsoHP', 'teleinfo', 'BASE,HCHP,EASF02,EASF04,EASF06,BBRHPJB,BBRHPJW,BBRHPJR,EJPHPM');
        $indexConsoHC = config::byKey('indexConsoHC', 'teleinfo', 'HCHC,EASF01,EASF03,EASF05,BBRHCJB,BBRHCJW,BBRHCJR,EJPHN');
        foreach (eqLogic::byType('teleinfo') as $eqLogic) {
            $cptId = strval($eqLogic->getId());
			$ppapHp  = 0;
			$ppapHc  = 0;
			$cmdPpap = null;
            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getConfiguration('type') == 'stat') {
                    if ($cmd->getConfiguration('info_conso') == 'PPAP_MANUELLE') {
                        log::add('teleinfo', 'debug', __('----- Calcul de la puissance apparente moyenne -----', __FILE__));
                        $cmdPpap = $cmd;
                    }
                }
            }
            if ($cmdPpap !== null) {
                log::add('teleinfo', 'debug', 'Compteur ' . $cptId . ' Cmd trouvée');
                foreach ($eqLogic->getCmd('info') as $cmd) {
                    if ($cmd->getConfiguration('type') == "data" || $cmd->getConfiguration('type') == "") {
                        if (strpos($indexConsoHP, $cmd->getConfiguration('info_conso')) !== false) {
                            $ppapHp += (int)$cmd->execCmd();
							log::add('teleinfo', 'debug', 'Compteur ' . $cptId . ' HP : ' . $cmd->getId() . ' Valeur: ' . $ppapHp);
                        }
                        if (strpos($indexConsoHC, $cmd->getConfiguration('info_conso')) !== false) {
                            $ppapHc += (int)$cmd->execCmd();
							log::add('teleinfo', 'debug', 'Compteur ' . $cptId . ' HC : ' . $cmd->getId() . ' Valeur: ' . $ppapHc);
                        }
                    }
                }
                $cacheHc        = cache::byKey('teleinfo::ppap_manuelle::' . $cptId . '::hc', false);
                $datetimeMesure = date_create($cacheHc->getDatetime());
                $cacheHp        = cache::byKey('teleinfo::ppap_manuelle::' . $cptId . '::hp', false);
                $cacheHc = is_object($cacheHc) ? $cacheHc->getValue() : 0;
                $cacheHp = is_object($cacheHp) ? $cacheHp->getValue() : 0;
                $datetimeMesure = $datetimeMesure->getTimestamp();
                $datetime2      = time();
                $interval       = (float)$datetime2 - (float)$datetimeMesure;
                if ($interval!=0){
                    $consoResultat = ((((float)$ppapHp - (float)$cacheHp) + ((float)$ppapHc - (float)$cacheHc)) / $interval) * 3600;
                } else {
                    $consoResultat = 0;
                }
                log::add('teleinfo', 'debug', 'Compteur ' . $cptId . __(' Intervale depuis la dernière valeur :', __FILE__) . ' ' . $interval);
                log::add('teleinfo', 'debug', 'Compteur ' . $cptId . __(' Conso calculée :', __FILE__) . ' ' . intval($consoResultat) . ' Wh');
                $cmdPpap->event(intval($consoResultat));
                cache::set('teleinfo::ppap_manuelle::' . $cptId . '::hc', $ppapHc, 150);
                cache::set('teleinfo::ppap_manuelle::' . $cptId . '::hp', $ppapHp, 150);
            } else {
                log::add('teleinfo', 'debug', 'Compteur ' . $cptId . __(' Pas de calcul', __FILE__));
            }
        }
    }

    public function preSave()
    {
        log::add('teleinfo', 'debug', '-------- PRESAVE --------');
        $this->setCategory('energy', 1);
        $cmd = $this->getCmd('info', 'HEALTH');
        if (is_object($cmd)) {
            $cmd->remove();
        }

        $array = array("STAT_JAN_HP", "STAT_JAN_HC", "STAT_FEV_HP", "STAT_FEV_HC", "STAT_MAR_HP", "STAT_MAR_HC", "STAT_AVR_HP", "STAT_AVR_HC", "STAT_MAI_HP", "STAT_MAI_HC", "STAT_JUIN_HP", "STAT_JUIN_HC", "STAT_JUI_HP", "STAT_JUI_HC", "STAT_AOU_HP", "STAT_AOU_HC", "STAT_SEP_HP", "STAT_SEP_HC");
        foreach ($array as $value){
            log::add('teleinfo', 'debug', __('Recherche de =>', __FILE__) . ' ' . $value);
            $cmd = $this->getCmd('info', $value);
            if (is_object($cmd)) {
                log::add('teleinfo', 'debug', __('Suppression de =>', __FILE__) . ' ' . $value);
                cache::set('teleinfo::needRegenerateMonthlyStat', '1');
                $cmd->remove();
                //$cmd->save();
            }
        }

        $array = array("STAT_OCT_HP", "STAT_OCT_HC", "STAT_NOV_HP", "STAT_NOV_HC", "STAT_DEC_HP", "STAT_DEC_HC", "STAT_MONTH_LAST_YEAR", "STAT_YEAR_LAST_YEAR","STAT_MONTH","STAT_MONTH_PROD", "STAT_YEAR", "STAT_YEAR_PROD", "STAT_LASTMONTH");
        foreach ($array as $value){
            log::add('teleinfo', 'debug', __('Recherche de =>', __FILE__) . ' ' . $value);
            $cmd = $this->getCmd('info', $value);
            if (is_object($cmd)) {
                log::add('teleinfo', 'debug', __('Suppression de =>', __FILE__) . ' ' . $value);
                cache::set('teleinfo::needRegenerateMonthlyStat', '1');
                $cmd->remove();
                //$cmd->save();
            }
        }
    }

    public function setChanged($_changed)
            
    {
        log::add('teleinfo', 'info', '-------- isEnable --------' . $_changed);
    }

    public function postSave()
    {
        log::add('teleinfo', 'info', __('-------- Sauvegarde de l\'objet --------', __FILE__));
        foreach ($this->getCmd(null, null, true) as $cmd) {
            switch ($cmd->getConfiguration('info_conso')) {
                case "BASE":
                case "HCHP":
                case "HCHC":
                case "EJPHN":
                case "BBRHPJB":
                case "BBRHPJW":
                case "BBRHPJR":
                case "BBRHCJB":
                case "BBRHCJW":
                case "BBRHCJR":
                case "EJPHPM":
                case "EAIT":
                case "EAST":
                case "EASF01":
                case "EASF02":
                case "EASF03":
                case "EASF04":
                case "EASF05":
                case "EASF06":
                case "EASF07":
                case "EASF08":
                case "EASF09":
                case "EASF10":
                case "EASD01":
                case "EASD02":
                    log::add('teleinfo', 'debug', $cmd->getConfiguration('info_conso') . '=> index');
                    if ($cmd->getDisplay('generic_type') == '') {
                        $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    }
					$cmd->setConfiguration('historizeMode', 'none');
                    $cmd->save();
                    $cmd->refresh();
                    break;
                case "PAPP":
                case "SINSTS":
                    log::add('teleinfo', 'debug', $cmd->getConfiguration('info_conso') . '=> papp ou sinsts');
                    if ($cmd->getDisplay('generic_type') == '') {
                        $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                        //$cmd->setDisplay('icon', '<i class=\"fa fa-tachometer\"><\/i>');
                    }
					$cmd->setConfiguration('historizeMode', 'avg');
                    $cmd->save();
                    $cmd->refresh();
                    break;
                case "PTEC":
                    log::add('teleinfo', 'debug', $cmd->getConfiguration('info_conso') . '=> ptec');
                    if ($cmd->getDisplay('generic_type') == '') {
                        $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    }
                    $cmd->save();
                    $cmd->refresh();
                    break;
                default :
                    log::add('teleinfo', 'debug', $cmd->getConfiguration('info_conso') . '=> default');
                    if ($cmd->getDisplay('generic_type') == '') {
                        $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    }
                    break;
            }
        }
        log::add('teleinfo', 'info', __('==> Gestion des id des commandes', __FILE__));
        foreach ($this->getCmd('info') as $cmd) {
            log::add('teleinfo', 'debug', 'Commande : ' . $cmd->getConfiguration('info_conso'));
            $cmd->setLogicalId($cmd->getConfiguration('info_conso'));
            $cmd->save();
        }
        log::add('teleinfo', 'debug', __('-------- Fin de la sauvegarde --------', __FILE__));

        if ($this->getConfiguration('AutoGenerateFields') == '1') {
            $this->CreateFromAbo($this->getConfiguration('abonnement'));
        }

        $this->createOtherCmd();

        $this->createPanelStats();

        if (cache::byKey('teleinfo::needRegenerateMonthlyStat', '0')->getValue() == '1'){
            cache::set('teleinfo::needRegenerateMonthlyStat', '0');
            $this->regenerateMonthlyStat();
        }

        

    }

    public function preRemove()
    {
        log::add('teleinfo', 'debug', __('Suppression d\'un objet', __FILE__));
    }

    public function createOtherCmd()
    {
        log::add('teleinfo', 'debug', __('-------- Santé --------', __FILE__));
        $array = array("HEALTH");
        foreach ($array as $value){
            $cmd = $this->getCmd('info', $value);
            if (!is_object($cmd)) {
                log::add('teleinfo', 'debug', 'Santé => ' . $value);
                $cmd = new teleinfoCmd();
                $cmd->setName($value);
                //$cmd->setEqLogic_id($value);
                $cmd->setEqLogic_id($this->id);
                $cmd->setLogicalId($value);
                $cmd->setType('info');
                $cmd->setConfiguration('info_conso', $value);
                $cmd->setConfiguration('type', 'health');
                $cmd->setSubType('string');
                $cmd->setIsHistorized(0);
                //$cmd->setEventOnly(1);
                $cmd->setIsVisible(0);
                $cmd->save();
            }
        }
    }

    public static function getConfigForCommunity() {
        if (!file_exists('/var/www/html/plugins/teleinfo/plugin_info/info.json')) {
          log::add('Teleinfo','warning', __('Pas de fichier info.json', __FILE__));
        }
        $data = json_decode(file_get_contents('/var/www/html/plugins/teleinfo/plugin_info/info.json'), true);
        if (!is_array($data)) {
            log::add('Teleinfo','warning', __('Impossible de décoder le fichier info.json', __FILE__));
        }
        try {
            $core_version = $data['pluginVersion'];
        } catch (\Exception $e) {
            log::add('Teleinfo','warning', __('Impossible de récupérer la version.', __FILE__));
        }
    
    
        $index = 1;
        $CommunityInfo = "";
        foreach (eqLogic::byType('teleinfo', true) as $teleinfo)  {
          if ($teleinfo->getConfiguration('ActivationProduction') == 1) { $prod = 'Producteur et consommateur'; }
          else if ($teleinfo->getConfiguration('ActivationProduction') == 0) { $prod = 'Consommateur'; }
          if ($teleinfo->getConfiguration('HCHP') == 1) { $style = 'HPHC ancienne formule'; }
          else if ($teleinfo->getConfiguration('HCHP') == 0) { $style = 'pas de HPHC ancienne formule'; }
          if ($teleinfo->getConfiguration('newIndex') == 1) { $newIndex = 'Utilisation new index'; }
          else if ($teleinfo->getConfiguration('newIndex') == 0) { $newIndex = 'pas de new index'; }
          $CommunityInfo = $CommunityInfo . "Compteur #" . $index . " - Mode : " . $prod . " - HPHC? : ". $style . " - Nouveaux index? : ". $newIndex . "\n";
          $index++;
        }
        
        $CommunityInfo .= '<br/>';
    
        
        $hw = jeedom::getHardwareName();
        if ($hw == 'diy')
            $hw = trim(shell_exec('systemd-detect-virt'));
        if ($hw == 'none')
            $hw = 'diy';
        $distrib = trim(shell_exec('. /etc/*-release && echo $ID $VERSION_ID'));
        $CommunityInfo .= 'OS: ' . $distrib . ' on ' . $hw;
        $CommunityInfo .= ' ; PHP: ' . phpversion();
        $CommunityInfo .= ' ; Python: ' . trim(shell_exec("python3 -V | cut -d ' ' -f 2"));
        $CommunityInfo .= '<br/>teleinfo: version ' . $core_version;
        $CommunityInfo .= ' ; cmds: ' . count(cmd::searchConfiguration('', teleinfo::class));
        return $CommunityInfo;
      }
       
    
    public function createPanelStats()
    {
        log::add('teleinfo', 'debug', '-------- Commandes des stats ---------');
        $array = array("STAT_TODAY","STAT_TODAY_HC", "STAT_TODAY_HP", "STAT_TODAY_PROD",
                        "STAT_YESTERDAY","STAT_YESTERDAY_HC","STAT_YESTERDAY_HP","STAT_YESTERDAY_PROD","STAT_YESTERDAY_PROD_COUT",
                        "STAT_TODAY_INDEX00","STAT_TODAY_INDEX00_COUT","STAT_YESTERDAY_INDEX00","STAT_YESTERDAY_INDEX00_COUT",
                        "STAT_TODAY_INDEX01","STAT_TODAY_INDEX01_COUT","STAT_YESTERDAY_INDEX01","STAT_YESTERDAY_INDEX01_COUT",
                        "STAT_TODAY_INDEX02","STAT_TODAY_INDEX02_COUT","STAT_YESTERDAY_INDEX02","STAT_YESTERDAY_INDEX02_COUT",
                        "STAT_TODAY_INDEX03","STAT_TODAY_INDEX03_COUT","STAT_YESTERDAY_INDEX03","STAT_YESTERDAY_INDEX03_COUT",
                        "STAT_TODAY_INDEX04","STAT_TODAY_INDEX04_COUT","STAT_YESTERDAY_INDEX04","STAT_YESTERDAY_INDEX04_COUT",
                        "STAT_TODAY_INDEX05","STAT_TODAY_INDEX05_COUT","STAT_YESTERDAY_INDEX05","STAT_YESTERDAY_INDEX05_COUT",
                        "STAT_TODAY_INDEX06","STAT_TODAY_INDEX06_COUT","STAT_YESTERDAY_INDEX06","STAT_YESTERDAY_INDEX06_COUT",
                        "STAT_TODAY_INDEX07","STAT_TODAY_INDEX07_COUT","STAT_YESTERDAY_INDEX07","STAT_YESTERDAY_INDEX07_COUT",
                        "STAT_TODAY_INDEX08","STAT_TODAY_INDEX08_COUT","STAT_YESTERDAY_INDEX08","STAT_YESTERDAY_INDEX08_COUT",
                        "STAT_TODAY_INDEX09","STAT_TODAY_INDEX09_COUT","STAT_YESTERDAY_INDEX09","STAT_YESTERDAY_INDEX09_COUT",
                        "STAT_TODAY_INDEX10","STAT_TODAY_INDEX10_COUT","STAT_YESTERDAY_INDEX10","STAT_YESTERDAY_INDEX10_COUT");
        foreach ($array as $value){
            $cmd = $this->getCmd('info', $value);
            if (!is_object($cmd)) {
                log::add('teleinfo', 'debug', 'Nouvelle => ' . $value);
                if (strpos($value,'COUT')<>0) {
                    $unite = ('€');
                }else{
                    $unite = ('Wh');
                }
                $cmd = new teleinfoCmd();
                $cmd->setName($value);
                $cmd->setEqLogic_id($this->id);
                $cmd->setLogicalId($value);
                $cmd->setType('info');
                $cmd->setUnite($unite);
                $cmd->setConfiguration('info_conso', $value);
                $cmd->setConfiguration('type', 'stat');
				$cmd->setConfiguration('historizeMode', 'none');
                $cmd->setDisplay('generic_type', 'DONT');
                $cmd->setSubType('numeric');
                $cmd->setIsHistorized(1);
                //$cmd->setEventOnly(1);
                $cmd->setIsVisible(0);
                $cmd->save();
                $cmd->refresh();
            } else {
                log::add('teleinfo', 'debug', 'Ancienne => ' . $value);
                //suppression du forçage des histo sur les commandes existantes, cela permet à l'utilisateur d'avoir le choix
                //$cmd->setIsHistorized(1);
                //$cmd->setConfiguration('type', 'stat');
                //$cmd->setConfiguration('historizeMode', 'none');
                //$cmd->setDisplay('generic_type', 'DONT');
                //$cmd->save();
                //$cmd->refresh();
            }

        }
    }

    public function CreateFromAbo($_abo)
    {
        $this->setConfiguration('AutoGenerateFields', '0');
        $this->save();
    }

    /*     * ******** MANAGEMENT ZONE ******* */

    private static function pythonRequirementsInstalled(string $pythonPath, string $requirementsPath) {
        if (!file_exists($pythonPath) || !file_exists($requirementsPath)) {
          return false;
        }
        exec("{$pythonPath} -m pip freeze", $packages_installed);
        $packages = join("||", $packages_installed);
        exec("cat {$requirementsPath}", $packages_needed);
        foreach ($packages_needed as $line) {
          if (preg_match('/([^\s]+)[\s]*([>=~]=)[\s]*([\d+\.?]+)$/', $line, $need) === 1) {
            if (preg_match('/' . $need[1] . '==([\d+\.?]+)/', $packages, $install) === 1) {
              if ($need[2] == '==' && $need[3] != $install[1]) {
                return false;
              } elseif (version_compare($need[3], $install[1], '>')) {
                return false;
              }
            } else {
              return false;
            }
          }
        }
        return true;
      }
    
      public static function dependancy_info()
      {
        $pythonBin = __DIR__ . '/../../resources/venv/bin/python3';
        $pythonReq = __DIR__ . '/../../resources/requirements.txt';
        $return = array();
        $return['log'] = log::getPathToLog(__CLASS__ . '_packages');
        $return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependance';
        $return['state'] = 'ok';
        if (file_exists($return['progress_file'])) {
          $return['state'] = 'in_progress';
          log::add(__CLASS__, 'debug', sprintf(
            __("Dépendances en cours d'installation... (%s%%)", __FILE__),
            trim(file_get_contents($return['progress_file']))
          ));
        } elseif (!file_exists($pythonBin)) {
          $return['state'] = 'nok';
        } elseif (!self::pythonRequirementsInstalled($pythonBin, $pythonReq)) {
          $return['state'] = 'nok';
        } else {
          log::add(__CLASS__, 'debug', sprintf(__('Dépendances installées.', __FILE__)));
        }
        return $return;
      }
    
      public static function dependancy_install()
      {
        $depLogFile = __CLASS__ . '_packages';
        log::remove($depLogFile);
        log::add(__CLASS__, 'info', sprintf(
            __('Installation des dépendances, voir log dédié (%s)', __FILE__),
            $depLogFile
          ));
        return array('script' => __DIR__ . '/../../resources/install_apt.sh ' . jeedom::getTmpFolder(__CLASS__) . '/dependance', 'log' => log::getPathToLog($depLogFile));
      }


    // Permet de modifier l'affichage du widget (également utilisable par les commandes)
    public function toHtml($_version = 'dashboard', $eqLogic = null) {
        // Récupérer l'équipement si non fourni
        if ($eqLogic === null) {
            $eqLogic = $this;
        }
        
        // Vérifier les conditions d'affichage du widget personnalisé
        $newIndex = $eqLogic->getConfiguration('newIndex', 0);
        $usePluginTemplate = $eqLogic->getConfiguration('usePluginTemplate', 0);
        
        // Si les conditions ne sont pas remplies, utiliser le template par défaut
        if ($usePluginTemplate != 1) {
            return parent::toHtml($_version);
        }
        
        // Déterminer le mode (standard ou historique)
        $linky = $eqLogic->getConfiguration('linky', 0);
        $isStandardMode = ($linky == 1);
        
        // Vérifier si le mode production est activé
        $activationProduction = intval($eqLogic->getConfiguration('ActivationProduction', 0));
        $hasProduction = $isStandardMode && ($activationProduction == 1);
        
        // Vérifier si l'affichage de toutes les commandes est activé
        $displayAll = intval($eqLogic->getConfiguration('display_all', 0));
        $hasAllCmds = ($displayAll == 1);
        
        // === VÉRIFIER SI LE PANEL EST ACTIVÉ ===
        $displayDesktopPanel = (config::byKey('displayDesktopPanel', 'teleinfo') == 1);
        
        // === RÉCUPÉRER LES INFORMATIONS D'ABONNEMENT ET TARIF ===
        $subscriptionName = '--';
        $subscriptionCmdId = '';
        $subscriptionCollectDate = '';
        $subscriptionValueDate = '';
        $currentTarif = '--';
        $tarifCmdId = '';
        $tarifCollectDate = '';
        $tarifValueDate = '';
        $tarifClass = 'tarif-standard';
        
        if ($isStandardMode) {
            // Mode Standard: NGTF (nom abonnement) et LTARF (tarif en cours)
            $cmdNGTF = $eqLogic->getCmd('info', 'NGTF');
            $cmdLTARF = $eqLogic->getCmd('info', 'LTARF');
            
            if (is_object($cmdNGTF)) {
                $subscriptionName = $cmdNGTF->execCmd();
                $subscriptionCmdId = $cmdNGTF->getId();
                $subscriptionCollectDate = $cmdNGTF->getCollectDate();
                $subscriptionValueDate = $cmdNGTF->getValueDate();
            }
            if (is_object($cmdLTARF)) {
                $currentTarif = $cmdLTARF->execCmd();
                $tarifCmdId = $cmdLTARF->getId();
                $tarifCollectDate = $cmdLTARF->getCollectDate();
                $tarifValueDate = $cmdLTARF->getValueDate();
                $tarifClass = $this->getTarifClass($currentTarif);
            }
        } else {
            // Mode Historique: OPTARIF (abonnement) et PTEC (tarif en cours)
            $cmdOPTARIF = $eqLogic->getCmd('info', 'OPTARIF');
            $cmdPTEC = $eqLogic->getCmd('info', 'PTEC');
            
            if (is_object($cmdOPTARIF)) {
                $subscriptionName = $cmdOPTARIF->execCmd();
                $subscriptionCmdId = $cmdOPTARIF->getId();
                $subscriptionCollectDate = $cmdOPTARIF->getCollectDate();
                $subscriptionValueDate = $cmdOPTARIF->getValueDate();
            }
            if (is_object($cmdPTEC)) {
                $currentTarif = $cmdPTEC->execCmd();
                $tarifCmdId = $cmdPTEC->getId();
                $tarifCollectDate = $cmdPTEC->getCollectDate();
                $tarifValueDate = $cmdPTEC->getValueDate();
                $tarifClass = $this->getTarifClass($currentTarif);
            }
        }

        // === GÉNÉRER LE HTML DU BOUTON PANEL ===
        $panelButtonHtml = '';
        if ($displayDesktopPanel) {
            $panelUrl = 'index.php?m=teleinfo&p=panel';
            $panelButtonHtml = '<a href="' . $panelUrl . '" class="teleinfo-panel-button" target="_blank">
                <i class="fa fa-chart-bar"></i> Panel Téléinfo
            </a>';
        }

        // Récupérer les données des index
        $indexData = $this->getIndexDataForWidget($eqLogic, $isStandardMode);
        
        // Générer les lignes du tableau
        $indexTableRows = $this->generateIndexTableRows($indexData);
        
        // Calculer les totaux consommation
        $totalConsoToday = 0;
        $totalCoutToday = 0;
        
        if ($isStandardMode) {
            // En mode standard, EAST contient déjà le total
            if (isset($indexData[0])) {
                $totalConsoToday = floatval($indexData[0]['conso_today']) / 1000;
                $totalCoutToday = floatval($indexData[0]['cout_today']);
            }
        } else {
            // En mode historique, sommer tous les index affichés
            foreach ($indexData as $index) {
                if ($index['display']) {
                    $totalConsoToday += floatval($index['conso_today']) / 1000;
                    $totalCoutToday += floatval($index['cout_today']);
                }
            }
        }
        
        // === VUMÈTRE CONSOMMATION ===
        $pappCmdId = '';
        $pappValue = 0;
        $pappLabel = 'Puissance';
        $pappCollectDate = '';
        $pappValueDate = '';
        $pappMax = intval($eqLogic->getConfiguration('pmax', 12000));
        if ($pappMax <= 0) {
            $pappMax = 12000;
        }
        
        if ($isStandardMode) {
            $pappCmd = $eqLogic->getCmd('info', 'SINSTS');
            $pappLabel = 'SINSTS';
        } else {
            $pappCmd = $eqLogic->getCmd('info', 'PAPP');
            $pappLabel = 'PAPP';
        }
        
        if (is_object($pappCmd)) {
            $pappCmdId = $pappCmd->getId();
            $pappValue = floatval($pappCmd->execCmd());
            $pappCollectDate = $pappCmd->getCollectDate();
            $pappValueDate = $pappCmd->getValueDate();
        }
        
        $pappPercent = min(($pappValue / $pappMax) * 100, 100);
        $pappBarClass = 'low';
        if ($pappPercent >= 85) {
            $pappBarClass = 'critical';
        } elseif ($pappPercent >= 60) {
            $pappBarClass = 'high';
        } elseif ($pappPercent >= 30) {
            $pappBarClass = 'medium';
        }

        // === INTENSITÉS TRIPHASÉ ===
        $displayTriphase = intval($eqLogic->getConfiguration('display_triphase', 0));
        $hasTriphase = ($displayTriphase == 1);
        
        // Données des intensités
        $triphaseData = array();
        $triphaseSectionHtml = '';
        $imaxParPhase  = 0;
        
        if ($hasTriphase) {
            // Calcul de l'intensité max par phase
            if ($isStandardMode) {
                // Mode Standard: IMAX = pmax / 3 / 200 (mode de calcul enedis)
                $imaxParPhase = round($pappMax / 3 / 200, 1);
                if ($imaxParPhase <= 0) $imaxParPhase = 30; // Valeur par défaut
                
                // Commandes IRMS1, IRMS2, IRMS3
                $phaseCommands = array(
                    1 => array('cmd' => 'IRMS1', 'label' => 'Phase 1'),
                    2 => array('cmd' => 'IRMS2', 'label' => 'Phase 2'),
                    3 => array('cmd' => 'IRMS3', 'label' => 'Phase 3')
                );
            } else {
                // Mode Historique: ISOUSC = intensité souscrite (max)
                $cmdISOUSC = $eqLogic->getCmd('info', 'ISOUSC');
                $imaxParPhase = 0;
                if (is_object($cmdISOUSC)) {
                    $imaxParPhase = floatval($cmdISOUSC->execCmd());
                }
                if ($imaxParPhase <= 0) $imaxParPhase = round($pappMax / 3 / 200, 1); //calcul identique à celui du mode standard
                
                // Commandes IINST1, IINST2, IINST3
                $phaseCommands = array(
                    1 => array('cmd' => 'IINST1', 'label' => 'Phase 1'),
                    2 => array('cmd' => 'IINST2', 'label' => 'Phase 2'),
                    3 => array('cmd' => 'IINST3', 'label' => 'Phase 3')
                );
            }
            
            // Récupérer les valeurs pour chaque phase
            foreach ($phaseCommands as $phase => $phaseInfo) {
                $cmdName = $phaseInfo['cmd'];
                $cmdPhase = $eqLogic->getCmd('info', $cmdName);
                
                $phaseValue = 0;
                $phaseCmdId = '';
                $phaseCollectDate = '';
                $phaseValueDate = '';
                
                if (is_object($cmdPhase)) {
                    $phaseValue = floatval($cmdPhase->execCmd());
                    $phaseCmdId = $cmdPhase->getId();
                    $phaseCollectDate = $cmdPhase->getCollectDate();
                    $phaseValueDate = $cmdPhase->getValueDate();
                }
                
                $phasePercent = ($imaxParPhase > 0) ? min(($phaseValue / $imaxParPhase) * 100, 100) : 0;
                $phaseBarClass = 'low';
                if ($phasePercent >= 85) {
                    $phaseBarClass = 'critical';
                } elseif ($phasePercent >= 60) {
                    $phaseBarClass = 'high';
                } elseif ($phasePercent >= 30) {
                    $phaseBarClass = 'medium';
                }
                
                $triphaseData[$phase] = array(
                    'cmd_id' => $phaseCmdId,
                    'value' => $phaseValue,
                    'max' => $imaxParPhase,
                    'percent' => $phasePercent,
                    'bar_class' => $phaseBarClass,
                    'collect_date' => $phaseCollectDate,
                    'value_date' => $phaseValueDate,
                    'label' => $phaseInfo['label']
                );
            }
            
            // Générer le HTML des vumètres triphasé
            $triphaseVumetersHtml = '';
            foreach ($triphaseData as $phase => $data) {
                $triphaseVumetersHtml .= '
                <div class="teleinfo-vumeter teleinfo-vumeter-phase" id="vumeter-phase' . $phase . '-' . $eqLogic->getId() . '">
                    <div class="vumeter-header">
                        <span class="vumeter-label">' . $data['label'] . '</span>
                        <span class="vumeter-value tooltip-trigger" 
                            id="phase' . $phase . '-value-' . $eqLogic->getId() . '"
                            data-collect-date="' . htmlspecialchars($data['collect_date']) . '"
                            data-value-date="' . htmlspecialchars($data['value_date']) . '">' . $this->formatNumber($data['value'], 0) . ' A</span>
                    </div>
                    <div class="vumeter-bar-container">
                        <div class="vumeter-bar ' . $data['bar_class'] . '" id="phase' . $phase . '-bar-' . $eqLogic->getId() . '" style="width: ' . number_format($data['percent'], 1, '.', '') . '%;"></div>
                    </div>
                    <div class="vumeter-scale">
                        <span>0</span>
                        <span>' . $this->formatNumber($data['max'], 0) . ' A</span>
                    </div>
                </div>';
            }
            
            // Générer le HTML de la section triphasé
            $triphaseSectionHtml = '<div class="teleinfo-section triphase" id="section-triphase-' . $eqLogic->getId() . '">
                <div class="section-header">
                    <span class="section-title">Intensités par Phase</span>
                    <span class="section-badge badge-triphase">Triphasé</span>
                </div>
                <div class="triphase-vumeters-container">
                    ' . $triphaseVumetersHtml . '
                </div>
            </div>';
        }


        // === SECTION PRODUCTION ===
        $productionSectionHtml = '';
        $sinstiCmdId = '';
        $sinstiValue = 0;
        $sinstiCollectDate = '';
        $sinstiValueDate = '';
        $sinstiMax = 0;
        $sinstiPercent = 0;
        $sinstiBarClass = 'injection-low';
        $eaitCmdId = '';
        $eaitValue = 0;
        $eaitCollectDate = '';
        $eaitValueDate = '';
        $statProdCmdId = '';
        $statProdValue = 0;
        $statProdCollectDate = '';
        $statProdValueDate = '';
        $coutProdKwh = 0;
        
        if ($hasProduction) {
            // --- Vumètre SINSTI ---
            $sinstiMax = intval($eqLogic->getConfiguration('sinsti_max', 12000));
            if ($sinstiMax <= 0) {
                $sinstiMax = 12000;
            }
            
            $sinstiCmd = $eqLogic->getCmd('info', 'SINSTI');
            if (is_object($sinstiCmd)) {
                $sinstiCmdId = $sinstiCmd->getId();
                $sinstiValue = floatval($sinstiCmd->execCmd());
                $sinstiCollectDate = $sinstiCmd->getCollectDate();
                $sinstiValueDate = $sinstiCmd->getValueDate();
            }
            
            $sinstiPercent = min(($sinstiValue / $sinstiMax) * 100, 100);
            $sinstiBarClass = 'injection-low';
            if ($sinstiPercent >= 85) {
                $sinstiBarClass = 'injection-critical';
            } elseif ($sinstiPercent >= 60) {
                $sinstiBarClass = 'injection-high';
            } elseif ($sinstiPercent >= 30) {
                $sinstiBarClass = 'injection-medium';
            }
            
            // --- Index EAIT ---
            $eaitCmd = $eqLogic->getCmd('info', 'EAIT');
            if (is_object($eaitCmd)) {
                $eaitCmdId = $eaitCmd->getId();
                $eaitValue = floatval($eaitCmd->execCmd());
                $eaitCollectDate = $eaitCmd->getCollectDate();
                $eaitValueDate = $eaitCmd->getValueDate();
            }
            
            // --- Stat production du jour ---
            $statProdCmd = $eqLogic->getCmd('info', 'STAT_TODAY_PROD');
            if (is_object($statProdCmd)) {
                $statProdCmdId = $statProdCmd->getId();
                $statProdValue = floatval($statProdCmd->execCmd());
                $statProdCollectDate = $statProdCmd->getCollectDate();
                $statProdValueDate = $statProdCmd->getValueDate();
            }
            
            // --- Coût production au kWh ---
            $coutProdKwh = floatval($eqLogic->getConfiguration('CoutindexProd', 0));
            $statProdCoutValue = ($statProdValue / 1000) * $coutProdKwh;
            
            // Générer le HTML de la section production
            $productionSectionHtml = '<div class="teleinfo-section prod" id="section-prod-' . $eqLogic->getId() . '">
                <div class="section-header">
                    <span class="section-title">Production (Injection)</span>
                    <span class="section-badge badge-prod">Prod</span>
                </div>
                
                <!-- Vumètre Puissance Injection -->
                <div class="teleinfo-vumeter" id="vumeter-prod-' . $eqLogic->getId() . '">
                    <div class="vumeter-header">
                        <span class="vumeter-label">SINSTI</span>
                        <span class="vumeter-value tooltip-trigger" 
                            id="sinsti-value-' . $eqLogic->getId() . '" 
                            data-collect-date="' . htmlspecialchars($sinstiCollectDate) . '"
                            data-value-date="' . htmlspecialchars($sinstiValueDate) . '">' . $this->formatNumber($sinstiValue, 0) . ' VA</span>
                    </div>
                    <div class="vumeter-bar-container">
                        <div class="vumeter-bar ' . $sinstiBarClass . '" id="sinsti-bar-' . $eqLogic->getId() . '" style="width: ' . number_format($sinstiPercent, 1, '.', '') . '%;"></div>
                    </div>
                    <div class="vumeter-scale">
                        <span>0</span>
                        <span>' . $sinstiMax . ' VA</span>
                    </div>
                </div>
                
                <!-- Tableau Production -->
                <table class="teleinfo-table">
                    <thead>
                        <tr>
                            <th class="index-name">Index</th>
                            <th class="index-value">Valeur</th>
                            <th class="index-conso">Inj. Jour</th>
                            <th class="index-cout">Revenu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="index-row">
                            <td class="index-name">EAIT</td>
                            <td class="index-value"><span class="value-number tooltip-trigger" 
                                                        id="eait-value-' . $eqLogic->getId() . '"
                                                        data-collect-date="' . htmlspecialchars($eaitCollectDate) . '"
                                                        data-value-date="' . htmlspecialchars($eaitValueDate) . '">' . $this->formatNumber($eaitValue, 0) . '</span></td>
                            <td class="index-conso"><span class="conso-number tooltip-trigger" 
                                                        id="prod-conso-' . $eqLogic->getId() . '"
                                                        data-collect-date="' . htmlspecialchars($statProdCollectDate) . '"
                                                        data-value-date="' . htmlspecialchars($statProdValueDate) . '">' . $this->formatNumber($statProdValue / 1000, 2) . ' kWh</span></td>
                            <td class="index-cout"><span class="cout-number" id="prod-cout-' . $eqLogic->getId() . '">' . $this->formatNumber($statProdCoutValue, 2) . ' €</span></td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Total Production -->
                <div class="teleinfo-total-row">
                    <div class="total-item total-conso">
                        <span class="total-label">Injection Jour</span>
                        <span class="total-value" id="total-prod-' . $eqLogic->getId() . '">' . $this->formatNumber($statProdValue / 1000, 2) . ' kWh</span>
                    </div>
                    <div class="total-item total-cout">
                        <span class="total-label">Revenu</span>
                        <span class="total-value" id="total-prod-cout-' . $eqLogic->getId() . '">' . $this->formatNumber($statProdCoutValue, 2) . ' €</span>
                    </div>
                </div>
            </div>';
        }
        
        // === SECTION TOUTES LES COMMANDES ===
        $allCmdsSectionHtml = '';
        $allCmdsData = array();
        
        if ($hasAllCmds) {
            // Récupérer toutes les commandes visibles de type info
            $allCmdsTableRows = '';
            $cmdIndex = 0;
            
            foreach ($eqLogic->getCmd('info') as $cmd) {
                // Vérifier si la commande est visible
                if ($cmd->getIsVisible() != 1) {
                    continue;
                }
                
                $cmdId = $cmd->getId();
                $cmdName = $cmd->getName();
                $cmdValue = $cmd->execCmd();
                $cmdUnit = $cmd->getUnite();
                $cmdSubType = $cmd->getSubType();
                $cmdCollectDate = $cmd->getCollectDate();
                $cmdValueDate = $cmd->getValueDate();
                
                // Formater la valeur selon le sous-type
                $displayValue = $cmdValue;
                $decimals = 0;
                
                if ($cmdSubType === 'numeric') {
                    $decimals = (strpos(strval($cmdValue), '.') !== false) ? 2 : 0;
                    $displayValue = $this->formatNumber(floatval($cmdValue), $decimals);
                }
                
                // Stocker les données pour le JavaScript
                $allCmdsData[$cmdIndex] = array(
                    'id' => $cmdId,
                    'name' => $cmdName,
                    'value' => $cmdValue,
                    'unit' => $cmdUnit,
                    'subtype' => $cmdSubType,
                    'decimals' => $decimals,
                    'collectDate' => $cmdCollectDate,
                    'valueDate' => $cmdValueDate
                );
                
                // Générer la ligne du tableau
                $allCmdsTableRows .= '<tr class="all-cmds-row" data-cmd-id="' . $cmdId . '">
                    <td class="cmd-name">' . $cmdName . '</td>
                    <td class="cmd-value"><span class="tooltip-trigger" 
                                            id="all-cmd-value-' . $cmdId . '"
                                            data-collect-date="' . htmlspecialchars($cmdCollectDate) . '"
                                            data-value-date="' . htmlspecialchars($cmdValueDate) . '">' . $displayValue . '</span></td>
                    <td class="cmd-unit">' . $cmdUnit . '</td>
                </tr>';
                
                $cmdIndex++;
            }
            
            // Générer le HTML de la section si des commandes ont été trouvées
            if (!empty($allCmdsTableRows)) {
                $allCmdsSectionHtml = '<div class="teleinfo-section all-cmds" id="section-all-cmds-' . $eqLogic->getId() . '">
                    <div class="section-header">
                        <span class="section-title">Toutes les commandes</span>
                        <span class="section-badge badge-all">' . $cmdIndex . ' cmd</span>
                    </div>
                    
                    <table class="all-cmds-table">
                        <thead>
                            <tr>
                                <th class="cmd-name">Nom</th>
                                <th class="cmd-value">Valeur</th>
                                <th class="cmd-unit">Unité</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $allCmdsTableRows . '
                        </tbody>
                    </table>
                </div>';
            }
        }

        //personnalisation de quelques éléments du template
        $vumeterFontSize = $eqLogic->getConfiguration('vumeter_font_size', '0.95em');
        if(empty($vumeterFontSize)){
            $vumeterFontSize = '0.95em';
        }

        $valueColor = $eqLogic->getConfiguration('value_color', '#2c3e50');
        if (empty($valueColor)) {
            $valueColor = '#2c3e50'; // Couleur par défaut
        }

        // Préparer les replacements pour le template
        $replace = array(
            '#id#' => $eqLogic->getId(),
            '#name#' => $eqLogic->getName(),
            '#name_display#' => $eqLogic->getName(),
            '#eqLink#' => $eqLogic->getLinkToConfiguration(),
            '#eqType#' => 'teleinfo',
            '#uid#' => 'teleinfo_' . $eqLogic->getId() . '_' . mt_rand(),
            '#version#' => $_version,
            '#width#' => $eqLogic->getDisplay('width', '250px'),
            '#height#' => $eqLogic->getDisplay('height', 'auto'),
            '#style#' => $eqLogic->getDisplay('style', ''),
            '#class#' => $eqLogic->getDisplay('widget_class', ''),
            '#eqLogic_class#' => $eqLogic->getConfiguration('class', ''),
            '#object_name#' => is_object($eqLogic->getObject()) ? $eqLogic->getObject()->getName() : '',
            
            // Données du tableau consommation
            '#index_table_rows#' => $indexTableRows,
            '#index_data#' => json_encode($indexData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            
            // Totaux consommation
            '#total_conso_today#' => $this->formatNumber($totalConsoToday, 2),
            '#total_cout_today#' => $this->formatNumber($totalCoutToday, 2) . ' €',
            
            // Mode
            '#is_standard_mode#' => $isStandardMode ? 'badge-standard' : 'badge-historique',
            '#is_standard_mode_label#' => $isStandardMode ? 'Standard' : 'Historique',
            '#is_standard_mode_js#' => $isStandardMode ? 'true' : 'false',
            
            // Abonnement et tarif
            '#subscription_name#' => $subscriptionName,
            '#subscription_cmd_id#' => strval($subscriptionCmdId),
            '#subscription_collect_date#' => htmlspecialchars($subscriptionCollectDate),
            '#subscription_value_date#' => htmlspecialchars($subscriptionValueDate),
            '#current_tarif#' => $currentTarif,
            '#tarif_cmd_id#' => strval($tarifCmdId),
            '#tarif_collect_date#' => htmlspecialchars($tarifCollectDate),
            '#tarif_value_date#' => htmlspecialchars($tarifValueDate),
            '#tarif_class#' => $tarifClass,
            
            // Vumètre consommation
            '#papp_cmd_id#' => strval($pappCmdId),
            '#papp_value#' => $this->formatNumber($pappValue, 0),
            '#papp_percent#' => number_format($pappPercent, 1, '.', ''),
            '#papp_max#' => strval($pappMax),
            '#papp_label#' => $pappLabel,
            '#papp_bar_class#' => $pappBarClass,
            '#papp_collect_date#' => htmlspecialchars($pappCollectDate),
            '#papp_value_date#' => htmlspecialchars($pappValueDate),
            
            // Triphasé
            '#has_triphase#' => $hasTriphase ? 'true' : 'false',
            '#triphase_section_html#' => $triphaseSectionHtml,
            '#triphase_data#' => json_encode($triphaseData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            '#triphase_imax#' => strval($imaxParPhase),

            // Production
            '#has_production#' => $hasProduction ? 'true' : 'false',
            '#production_section_html#' => $productionSectionHtml,
            '#sinsti_cmd_id#' => strval($sinstiCmdId),
            '#sinsti_max#' => strval($sinstiMax),
            '#eait_cmd_id#' => strval($eaitCmdId),
            '#stat_prod_cmd_id#' => strval($statProdCmdId),
            '#cout_prod_kwh#' => strval($coutProdKwh),
            
            // Toutes les commandes
            '#has_all_cmds#' => $hasAllCmds ? 'true' : 'false',
            '#all_cmds_section_html#' => $allCmdsSectionHtml,
            '#all_cmds_data#' => json_encode($allCmdsData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            
            // Bouton Panel
            '#panel_button_html#' => $panelButtonHtml,

            // Personnalisation du template
            '#vumeter_font_size#' => $vumeterFontSize,
            '#value_color#' => $valueColor,
        );
        
        $html = template_replace($replace, getTemplate('core', $_version, 'teleinfo.template', __CLASS__));
        return $eqLogic->postToHtml($_version, $html);
    }

    /**
     * Détermine la classe CSS pour le badge tarif
     * 
     * @param string $tarif Le tarif en cours
     * @return string La classe CSS
     */
    private function getTarifClass($tarif) {
        if (empty($tarif)) {
            return 'tarif-standard';
        }

        $tarif = strtoupper($tarif);
        
        // Tempo - Bleu
        if (strpos($tarif, 'BLEU') !== false || strpos($tarif, 'JB') !== false) {
            return 'tarif-bleu';
        }
        // Tempo - Blanc
        if (strpos($tarif, 'BLANC') !== false || strpos($tarif, 'JW') !== false) {
            return 'tarif-blanc';
        }
        // Tempo - Rouge
        if (strpos($tarif, 'ROUGE') !== false || strpos($tarif, 'JR') !== false) {
            return 'tarif-rouge';
        }
        
        // Heures Pleines
        if (strpos($tarif, 'HP') !== false) {
            return 'tarif-hp';
        }
        // Heures Creuses
        if (strpos($tarif, 'HC') !== false) {
            return 'tarif-hc';
        }

        // Heures de pointe
        if (strpos($tarif, 'PM') !== false) {
            return 'tarif-pointe';
        }
        
        // Standard (BASE, etc.) ou inconnu
        return 'tarif-standard';
    }

    /**
     * Récupère les données des index pour le widget
     * 
     * @param eqLogic $eqLogic L'équipement
     * @param bool $isStandardMode Mode standard (true) ou historique (false)
     * @return array Tableau des données d'index
     */
    private function getIndexDataForWidget($eqLogic, $isStandardMode) {
        $indexData = array();
        $hasConfiguredIndexes = false;
        
        // === D'ABORD: Vérifier si des index sont configurés (01 à 10) ===
        for ($i = 1; $i <= 10; $i++) {
            $indexNum = str_pad($i, 2, '0', STR_PAD_LEFT);
            $indexName = $eqLogic->getConfiguration('index' . $indexNum, '');
            
            if (!empty($indexName)) {
                $hasConfiguredIndexes = true;
                break;
            }
        }
        
        // === INDEX 00: BASE ou EAST ===
        if ($isStandardMode) {
            // Mode Standard: EAST toujours présent
            $index00Name = 'EAST';
            $cmdIndex00 = $eqLogic->getCmd('info', $index00Name);
            $cmdStatToday00 = $eqLogic->getCmd('info', 'STAT_TODAY_INDEX00');
            $cmdCoutToday00 = $eqLogic->getCmd('info', 'STAT_TODAY_INDEX00_COUT');
            
            $indexData[0] = array(
                'name' => $index00Name,
                'label' => 'Total (EAST)',
                'cmd_id' => is_object($cmdIndex00) ? $cmdIndex00->getId() : '',
                'stat_conso_id' => is_object($cmdStatToday00) ? $cmdStatToday00->getId() : '',
                'stat_cout_id' => is_object($cmdCoutToday00) ? $cmdCoutToday00->getId() : '',
                'value' => is_object($cmdIndex00) ? $cmdIndex00->execCmd() : 0,
                'unit' => is_object($cmdIndex00) ? $cmdIndex00->getUnite() : 'Wh',
                'conso_today' => is_object($cmdStatToday00) ? $cmdStatToday00->execCmd() : 0,
                'cout_today' => is_object($cmdCoutToday00) ? $cmdCoutToday00->execCmd() : 0,
                'collect_date' => is_object($cmdIndex00) ? $cmdIndex00->getCollectDate() : '',
                'value_date' => is_object($cmdIndex00) ? $cmdIndex00->getValueDate() : '',
                'stat_conso_collect_date' => is_object($cmdStatToday00) ? $cmdStatToday00->getCollectDate() : '',
                'stat_conso_value_date' => is_object($cmdStatToday00) ? $cmdStatToday00->getValueDate() : '',
                'stat_cout_collect_date' => is_object($cmdCoutToday00) ? $cmdCoutToday00->getCollectDate() : '',
                'stat_cout_value_date' => is_object($cmdCoutToday00) ? $cmdCoutToday00->getValueDate() : '',
                'display' => true,
                'is_total' => true
            );
        } else {
            // Mode Historique: BASE affiché SEULEMENT si aucun index configuré
            if (!$hasConfiguredIndexes) {
                $index00Name = 'BASE';
                $cmdIndex00 = $eqLogic->getCmd('info', $index00Name);
                $cmdStatToday00 = $eqLogic->getCmd('info', 'STAT_TODAY_INDEX00');
                $cmdCoutToday00 = $eqLogic->getCmd('info', 'STAT_TODAY_INDEX00_COUT');
                
                $indexData[0] = array(
                    'name' => $index00Name,
                    'label' => 'Total (BASE)',
                    'cmd_id' => is_object($cmdIndex00) ? $cmdIndex00->getId() : '',
                    'stat_conso_id' => is_object($cmdStatToday00) ? $cmdStatToday00->getId() : '',
                    'stat_cout_id' => is_object($cmdCoutToday00) ? $cmdCoutToday00->getId() : '',
                    'value' => is_object($cmdIndex00) ? $cmdIndex00->execCmd() : 0,
                    'unit' => is_object($cmdIndex00) ? $cmdIndex00->getUnite() : 'Wh',
                    'conso_today' => is_object($cmdStatToday00) ? $cmdStatToday00->execCmd() : 0,
                    'cout_today' => is_object($cmdCoutToday00) ? $cmdCoutToday00->execCmd() : 0,
                    'collect_date' => is_object($cmdIndex00) ? $cmdIndex00->getCollectDate() : '',
                    'value_date' => is_object($cmdIndex00) ? $cmdIndex00->getValueDate() : '',
                    'stat_conso_collect_date' => is_object($cmdStatToday00) ? $cmdStatToday00->getCollectDate() : '',
                    'stat_conso_value_date' => is_object($cmdStatToday00) ? $cmdStatToday00->getValueDate() : '',
                    'stat_cout_collect_date' => is_object($cmdCoutToday00) ? $cmdCoutToday00->getCollectDate() : '',
                    'stat_cout_value_date' => is_object($cmdCoutToday00) ? $cmdCoutToday00->getValueDate() : '',
                    'display' => true,
                    'is_total' => true
                );
            }
        }
        
        // === INDEX 01 à 10 - Uniquement si configurés ===
        for ($i = 1; $i <= 10; $i++) {
            $indexNum = str_pad($i, 2, '0', STR_PAD_LEFT);
            $indexName = $eqLogic->getConfiguration('index' . $indexNum, '');
            
            // Ne pas afficher si le nom de l'index est vide
            if (empty($indexName)) {
                continue;
            }
            
            // Récupérer la commande de l'index
            $cmdIndex = null;
            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getConfiguration('info_conso') == $indexName) {
                    $cmdIndex = $cmd;
                    break;
                }
            }
            
            // Récupérer les statistiques du jour
            $cmdStatToday = $eqLogic->getCmd('info', 'STAT_TODAY_INDEX' . $indexNum);
            $cmdCoutToday = $eqLogic->getCmd('info', 'STAT_TODAY_INDEX' . $indexNum . '_COUT');
            
            // Récupérer le coût au kWh configuré
            $coutKwh = $eqLogic->getConfiguration('Coutindex' . $indexNum, 0);

            // Récupérer le nom des index
            $labelName = $eqLogic->getConfiguration('index' . $indexNum . '_nom', 'pas de nom');
            
            $indexData[$i] = array(
                'name' => $indexName,
                'label' => $labelName,
                'cmd_id' => is_object($cmdIndex) ? $cmdIndex->getId() : '',
                'stat_conso_id' => is_object($cmdStatToday) ? $cmdStatToday->getId() : '',
                'stat_cout_id' => is_object($cmdCoutToday) ? $cmdCoutToday->getId() : '',
                'value' => is_object($cmdIndex) ? $cmdIndex->execCmd() : 0,
                'unit' => is_object($cmdIndex) ? $cmdIndex->getUnite() : 'Wh',
                'conso_today' => is_object($cmdStatToday) ? $cmdStatToday->execCmd() : 0,
                'cout_today' => is_object($cmdCoutToday) ? $cmdCoutToday->execCmd() : 0,
                'cout_kwh' => $coutKwh,
                'collect_date' => is_object($cmdIndex) ? $cmdIndex->getCollectDate() : '',
                'value_date' => is_object($cmdIndex) ? $cmdIndex->getValueDate() : '',
                'stat_conso_collect_date' => is_object($cmdStatToday) ? $cmdStatToday->getCollectDate() : '',
                'stat_conso_value_date' => is_object($cmdStatToday) ? $cmdStatToday->getValueDate() : '',
                'stat_cout_collect_date' => is_object($cmdCoutToday) ? $cmdCoutToday->getCollectDate() : '',
                'stat_cout_value_date' => is_object($cmdCoutToday) ? $cmdCoutToday->getValueDate() : '',
                'display' => true,
                'is_total' => false
            );
        }
        
        return $indexData;
    }

    /**
     * Formate un nombre pour l'affichage
     * 
     * @param float $value La valeur à formater
     * @param int $decimals Nombre de décimales
     * @return string La valeur formatée
     */
    private function formatNumber($value, $decimals = 0) {
        return number_format($value, $decimals, ',', ' ');
    }

    /**
     * Génère les lignes HTML du tableau des index
     * 
     * @param array $indexData Les données des index
     * @return string Le HTML des lignes du tableau
     */
    private function generateIndexTableRows($indexData) {
        $html = '';
        
        foreach ($indexData as $key => $index) {
            if (!$index['display']) {
                continue;
            }
            
            $rowClass = isset($index['is_total']) && $index['is_total'] ? 'index-total-row' : 'index-row';
            
            // Convertir la valeur en kWh si elle est en Wh
            $valueWh = floatval($index['value']);
            $valueKwh = $valueWh;
            $valueDisplay = $this->formatNumber($valueKwh, 0);
            
            // Formater la consommation du jour en kWh
            $consoToday = floatval($index['conso_today']);
            $consoTodayKwh = $consoToday / 1000;
            $consoDisplay = $this->formatNumber($consoTodayKwh, 2) . ' kWh';
            
            // Formater le coût
            $coutDisplay = $this->formatNumber(floatval($index['cout_today']), 2) . ' €';
            
            // Générer le lien vers la commande si disponible
            $nameDisplay = $index['label'];
            if (!empty($index['cmd_id'])) {
                $nameDisplay = '<span class="cmd-widget" data-cmd_id="' . $index['cmd_id'] . '">' . $index['label'] . '</span>';
            }
            
            $html .= '<tr class="' . $rowClass . '" data-row-index="' . $key . '">';
            $html .= '<td class="index-name">' . $nameDisplay . '</td>';
            $html .= '<td class="index-value"><span class="value-number tooltip-trigger" 
                                                        data-collect-date="' . htmlspecialchars($index['collect_date']) . '"
                                                        data-value-date="' . htmlspecialchars($index['value_date']) . '">' . $valueDisplay . '</span></td>';
            $html .= '<td class="index-conso"><span class="conso-number tooltip-trigger" 
                                                        data-collect-date="' . htmlspecialchars($index['stat_conso_collect_date']) . '"
                                                        data-value-date="' . htmlspecialchars($index['stat_conso_value_date']) . '">' . $consoDisplay . '</span></td>';
            $html .= '<td class="index-cout"><span class="cout-number tooltip-trigger" 
                                                        data-collect-date="' . htmlspecialchars($index['stat_cout_collect_date']) . '"
                                                        data-value-date="' . htmlspecialchars($index['stat_cout_value_date']) . '">' . $coutDisplay . '</span></td>';
            $html .= '</tr>';
        }
        
        return $html;
    }



    
}

class teleinfoCmd extends cmd
{

    public function execute($_options = null)
    {

    }

}
