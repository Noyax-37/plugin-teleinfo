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
		$twoCptCartelectronic = config::byKey('2cpt_cartelectronic', 'teleinfo');
		if ($twoCptCartelectronic == 1) {
			$return['state'] = 'nok';
            $return['message'] = __('Non disponible pour le modem 2 compteurs. Veuillez regarder la zone Configuration avancée afin de configurer le modem.', __FILE__);
			return $return;
		}
        if ($type == "usb") {
            $port = jeedom::getUsbMapping($port);
        }

/*		exec('stty -F ' . $port . ' 1200 sane evenp parenb cs7 -crtscts');
        passthru('timeout 5 sed -n 5,8p ' . $port, $return['data']);
        log::add('teleinfo', 'debug', "retour : " . $return['data']);
		if ($return['data'] > 5){
            $return['state'] = 'ok';
            $return['type'] = 'historique';
            $return['linky'] = false;
            $return['vitesse'] = '1200';
            $return['message'] = 'Il s\'agit d\'un compteur en mode historique.';
        }
        else {
            exec('stty -F ' . $port . ' 9600 sane evenp parenb cs7 -crtscts');
			passthru('timeout 5 sed -n 5,8p ' . $port, $return['data']);
			if ($return['data'] > 5){
				$return['state'] = 'ok';
				$return['type'] = 'standard';
                $return['linky'] = true;
				$return['vitesse'] = '9600';
				$return['message'] = 'Il s\'agit d\'un compteur en mode standard.';
			}
			else {
				$return['state'] = 'nok';
				$return['type'] = '';
				$return['vitesse'] = '';
				$return['message'] = 'Impossible de détecter le type de compteur.';
			}
        }
*/
        // en attendant de faire fonctionner le test de vitesse du modem
        $return['state'] = 'nok';
        $return['type'] = '';
        $return['vitesse'] = '';
        $return['message'] = __('Cette fonction n est pas opérationnelle, configurez la vitesse du port manuellement.', __FILE__);
        // --------------------------------------------------------------
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
        $indexConsoHP      = config::byKey('indexConsoHP', 'teleinfo', 'EASF02,EASF04,EASF06,HCHP,BBRHPJB,BBRHPJW,BBRHPJR,EJPHPM');
        $indexConsoHC      = config::byKey('indexConsoHC', 'teleinfo', 'EASF01,EASF03,EASF05,HCHC,BBRHCJB,BBRHCJW,BBRHCJR,EJPHN');
        $indexProduction   = config::byKey('indexProduction', 'teleinfo', 'EAIT');
        $indexConsoTotales   = config::byKey('indexConsoTotales', 'teleinfo', 'BASE,EAST,HCHP,HCHC,BBRHPJB,BBRHPJW,BBRHPJR,BBRHCJB,BBRHCJW,BBRHCJR,EJPHPM,EJPHN');


        log::add('teleinfo', 'info', '----- Calcul des statistiques temps réel -----');
        $startDateToday            = (new DateTime())->setTimestamp(mktime(0, 0, 0, date("m"), date("d"), date("Y")));
        $endDateToday              = (new DateTime())->setTimestamp(mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
        log::add('teleinfo', 'info', 'Date de début : ' . $startDateToday->format('Y-m-d 00:00:00'));
        log::add('teleinfo', 'info', 'Date de fin   : ' . $endDateToday->format('Y-m-d H:i:s'));
        log::add('teleinfo', 'info', 'Liste index HP            : ' . $indexConsoHP);
        log::add('teleinfo', 'info', 'Liste index HC            : ' . $indexConsoHC);
        log::add('teleinfo', 'info', 'Liste index Production    : ' . $indexProduction);
        log::add('teleinfo', 'info', 'Liste index Conso Totale  : ' . $indexConsoTotales);


        foreach (eqLogic::byType('teleinfo') as $eqLogic) {

            log::add('teleinfo', 'info', '----------------------------------------------');
            log::add('teleinfo', 'info', 'Objet : ' . $eqLogic->getName());

            $statTodayHp       = 0;
            $statTodayHc       = 0;
            $statTodayProd     = 0;
            $statYesterdayHp   = 0;
            $statYesterdayHc   = 0;
            $typeTendance      = 0;
            $statToday         = 0;
			$index             = '';
            $statHpToCumul     = array();
            $statHcToCumul     = array();
            $statProdToCumul   = array();
			$statTotalToCumul  = array();
            $statTotalMaxToday = 0;
            $statTotalMinToday = 0;
            $statTodayTotal = 0;
            $statYesterdayTotal = 0;
            $statHcMaxToday = 0;
            $statHcMinToday = 0;
            $statHcTotal = 0;
            $statYesterdayHc = 0;
            $statHpMaxToday = 0;
            $statHpMinToday = 0;
            $statHpTotal = 0;
            $statYesterdayHp = 0;
            $statProdMaxToday = 0;
            $statProdMinToday = 0;
            $statProdTotal = 0;
            $statYesterdayProd = 0;



			// raz des variables
            for ($i=0; $i <= 10; $i++){
				if ($i == 10) {   //affectation des variables index en dynamique
					$a = 'idIndex' . $i;
					$b = 'statTodayIndex' . $i;
					$c = 'statYesterdayIndex' . $i;
                    $d = 'Coutindex' . $i;
                    $e = 'Coutkwhindex' . $i;
                    $f = 'index' . $i;
				} 
				else {
					$a = 'idIndex0' . $i;
					$b = 'statTodayIndex0' . $i;
					$c = 'statYesterdayIndex0' . $i;
                    $d = 'Coutindex0' . $i;
                    $e = 'Coutkwhindex0' . $i;
                    $f = 'index0' . $i;
				}
                $$a = 0;
                $$b = 0;
                $$c = 0;
                $$d = 0;
                $$e = '';
                $$f = '';
            }


            $index01 = $eqLogic->getConfiguration('index01');
			$index02 = $eqLogic->getConfiguration('index02');
			$index03 = $eqLogic->getConfiguration('index03');
			$index04 = $eqLogic->getConfiguration('index04');
			$index05 = $eqLogic->getConfiguration('index05');
			$index06 = $eqLogic->getConfiguration('index06');
			$index07 = $eqLogic->getConfiguration('index07');
			$index08 = $eqLogic->getConfiguration('index08');
			$index09 = $eqLogic->getConfiguration('index09');
			$index10 = $eqLogic->getConfiguration('index10');

            $Coutkwhindex00 = $eqLogic->getConfiguration('Coutindex00');
            $Coutkwhindex01 = $eqLogic->getConfiguration('Coutindex01');
            $Coutkwhindex02 = $eqLogic->getConfiguration('Coutindex02');
            $Coutkwhindex03 = $eqLogic->getConfiguration('Coutindex03');
            $Coutkwhindex04 = $eqLogic->getConfiguration('Coutindex04');
            $Coutkwhindex05 = $eqLogic->getConfiguration('Coutindex05');
            $Coutkwhindex06 = $eqLogic->getConfiguration('Coutindex06');
            $Coutkwhindex07 = $eqLogic->getConfiguration('Coutindex07');
            $Coutkwhindex08 = $eqLogic->getConfiguration('Coutindex08');
            $Coutkwhindex09 = $eqLogic->getConfiguration('Coutindex09');
            $Coutkwhindex10 = $eqLogic->getConfiguration('Coutindex10');

            $linky = config::byKey('linky', 'teleinfo');

			if ($index01 != '') {
				log::add('teleinfo', 'info', 'Index 01     --> ' . $index01);
			}
			if ($index02 != '') {
				log::add('teleinfo', 'info', 'Index 02     --> ' . $index02);
			}
			if ($index03 != '') {
				log::add('teleinfo', 'info', 'Index 03     --> ' . $index03);
			}
			if ($index04 != '') {
				log::add('teleinfo', 'info', 'Index 04     --> ' . $index04);
			}
			if ($index05 != '') {
				log::add('teleinfo', 'info', 'Index 05     --> ' . $index05);
			}
			if ($index06 != '') {
				log::add('teleinfo', 'info', 'Index 06     --> ' . $index06);
			}
			if ($index07 != '') {
				log::add('teleinfo', 'info', 'Index 07     --> ' . $index07);
			}
			if ($index08 != '') {
				log::add('teleinfo', 'info', 'Index 08     --> ' . $index08);
			}
			if ($index09 != '') {
				log::add('teleinfo', 'info', 'Index 09     --> ' . $index09);
			}
			if ($index10 != '') {
				log::add('teleinfo', 'info', 'Index 10     --> ' . $index10);
			}

            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getConfiguration('type') == "data" || $cmd->getConfiguration('type') == "") {
                    if (!empty($cmd->getConfiguration('info_conso'))) {
                        if (strpos($indexConsoHP, $cmd->getConfiguration('info_conso')) !== false) {
                            array_push($statHpToCumul, $cmd->getId());
                        }
                        if (strpos($indexConsoHC, $cmd->getConfiguration('info_conso')) !== false) {
                            array_push($statHcToCumul, $cmd->getId());
                        }
                        if (strpos($indexProduction, $cmd->getConfiguration('info_conso')) !== false) {
                            array_push($statProdToCumul, $cmd->getId());
                        }
						if (strpos($indexConsoTotales, $cmd->getConfiguration('info_conso')) !== false) {
							log::add('teleinfo', 'debug', 'Id Index Global --> ' . $cmd->getId());
							array_push($statTotalToCumul, $cmd->getId());
						}
                    }
                }
                if ($cmd->getConfiguration('info_conso') == "TENDANCE_DAY") {
                    $typeTendance = $cmd->getConfiguration('type_calcul_tendance');
                }
				if (($cmd->getConfiguration('info_conso') == 'BASE') || ($cmd->getConfiguration('info_conso') == 'EAST')) {
					$index00 = $cmd->getConfiguration('info_conso');
					$idIndex00 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index 00     --> ' . $index00);
					log::add('teleinfo', 'debug', 'Id Index00 ' . $idIndex00);
				}
				if ($cmd->getConfiguration('info_conso') == $index01) {
					$idIndex01 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Index01 ' . $idIndex01);
				}
				if ($cmd->getConfiguration('info_conso') == $index02) {
					$idIndex02 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Index02 ' . $idIndex02);
				}
				if ($cmd->getConfiguration('info_conso') == $index03) {
					$idIndex03 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Index03 ' . $idIndex03);
				}
				if ($cmd->getConfiguration('info_conso') == $index04) {
					$idIndex04 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Index04 ' . $idIndex04);
				}
				if ($cmd->getConfiguration('info_conso') == $index05) {
					$idIndex05 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Index05 ' . $idIndex05);
				}
				if ($cmd->getConfiguration('info_conso') == $index06) {
					$idIndex06 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Index06 ' . $idIndex06);
				}
				if ($cmd->getConfiguration('info_conso') == $index07) {
					$idIndex07 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Index07 ' . $idIndex07);
				}
				if ($cmd->getConfiguration('info_conso') == $index08) {
					$idIndex08 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Index08 ' . $idIndex08);
				}
				if ($cmd->getConfiguration('info_conso') == $index09) {
					$idIndex09 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Index09 ' . $idIndex09);
				}
				if ($cmd->getConfiguration('info_conso') == $index10) {
					$idIndex10 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Index10 ' . $idIndex10);
				}
				log::add('teleinfo', 'debug', __('liste des donnees :', __FILE__) . ' ' . $cmd->getConfiguration('info_conso'));
            }

            $startdateyesterday = (new DateTime())->setTimestamp(mktime(0, 0, 0, date("m"), date("d") - 1, date("Y")));
            if ($typeTendance === 1) {
                $enddateyesterday = date("Y-m-d H:i:s", mktime(23, 59, 59, date("m"), date("d") - 1, date("Y")));
            } else {
                $enddateyesterday = date("Y-m-d H:i:s", mktime(date("H"), date("i"), date("s"), date("m"), date("d") - 1, date("Y")));
            }

            $Coutindex00 = 0;
			for ($i=0; $i <= 10; $i++){
				if ($i == 10) {   //affectation des variables index en dynamique
					$a = 'idIndex' . $i;
					$b = 'statTodayIndex' . $i;
					$c = 'statYesterdayIndex' . $i;
                    $d = 'Coutindex' . $i;
                    $e = 'Coutkwhindex' . $i;
				} 
				else {
					$a = 'idIndex0' . $i;
					$b = 'statTodayIndex0' . $i;
					$c = 'statYesterdayIndex0' . $i;
                    $d = 'Coutindex0' . $i;
                    $e = 'Coutkwhindex0' . $i;
				}
				if (${$a} >= 1) {
                    log::add('teleinfo', 'debug', __('Index à trouver', __FILE__) . ' ' . $i . ' = ' . $a);
					log::add('teleinfo', 'debug', 'Id Index ' . $i . ' = ' . ${$a});
					$cmd = cmd::byId(${$a});
					$statMaxToday = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'))['max'];
					$statMinToday = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'))['min'];
					log::add('teleinfo', 'debug', ' ==> Valeur Index ' . $i . ' MAX : ' . intval($statMaxToday));
					log::add('teleinfo', 'debug', ' ==> Valeur Index ' . $i . ' MIN : ' . intval($statMinToday));
					$$b = intval($statMaxToday) - intval($statMinToday);
					log::add('teleinfo', 'debug', 'Total Index ' . $i . ' --> ' . ${$b});
                    $$d = $$b * floatval($$e) / 1000;
                    if ($i == 0){
                        $Coutindex00Init = $Coutindex00;
                        $statTodayIndex00init = $statTodayIndex00;
                        $Coutindex00 = 0;
                        $statTodayIndex00 = 0;
                    }else{
                        $statTodayIndex00 += ${$b};
                        $Coutindex00 += ${$d};
                        $statTodayIndex00init = 0;
                        $Coutindex00Init = 0;
                    }
                    log::add('teleinfo', 'info', __('Coût Index00', __FILE__) . ' ' . $Coutindex00); 
					log::add('teleinfo', 'info', __('Coût au kWh Index', __FILE__) . ' ' . $i . ' --> ' .${$e}. __(' coût pour cet index aujourd hui -->', __FILE__) . ' ' .${$d});
                }
			}
            $statTodayIndex00 += $statTodayIndex00init;
            $Coutindex00 += $Coutindex00Init;

            
            foreach ($statTotalToCumul as $key => $value) {
                log::add('teleinfo', 'debug', 'Commande Conso totale N° ' . $value);
                $cmd            = cmd::byId($value);
                $statTotalMaxToday = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'))['max'];
                $statTotalMinToday = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'))['min'];
                log::add('teleinfo', 'debug', ' ==> Valeur conso totale MAX : ' . $statTotalMaxToday);
                log::add('teleinfo', 'debug', ' ==> Valeur consototale MIN : ' . $statTotalMinToday);

                $statTodayTotal     += intval($statTotalMaxToday) - intval($statTotalMinToday);
                $statYesterdayTotal += intval($cmd->getStatistique($startdateyesterday->format('Y-m-d 00:00:00'), $enddateyesterday)['max']) - intval($cmd->getStatistique($startdateyesterday->format('Y-m-d 00:00:00'), $enddateyesterday)['min']);
                log::add('teleinfo', 'debug', 'Total conso --> ' . $statTodayTotal);
            }
            foreach ($statHcToCumul as $key => $value) {
                $cmd            = cmd::byId($value);
                $statHcMaxToday = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'))['max'];
                $statHcMinToday = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'))['min'];
                log::add('teleinfo', 'debug', 'Commande HC N°' . $value);
                log::add('teleinfo', 'debug', ' ==> Valeur HC MAX : ' . $statHcMaxToday);
                log::add('teleinfo', 'debug', ' ==> Valeur HC MIN : ' . $statHcMinToday);

                $statTodayHc     += intval($statHcMaxToday) - intval($statHcMinToday);
                $statYesterdayHc += intval($cmd->getStatistique($startdateyesterday->format('Y-m-d 00:00:00'), $enddateyesterday)['max']) - intval($cmd->getStatistique($startdateyesterday->format('Y-m-d 00:00:00'), $enddateyesterday)['min']);
                log::add('teleinfo', 'debug', 'Total HC --> ' . $statTodayHc);
            }
            foreach ($statHpToCumul as $key => $value) {
                $cmd            = cmd::byId($value);
                $statHpMaxToday = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'))['max'];
                $statHpMinToday = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'))['min'];
                log::add('teleinfo', 'debug', 'Commande HP N°' . $value);
                log::add('teleinfo', 'debug', ' ==> Valeur HP MAX : ' . $statHpMaxToday);
                log::add('teleinfo', 'debug', ' ==> Valeur HP MIN : ' . $statHpMinToday);

                $statTodayHp     += intval($statHpMaxToday) - intval($statHpMinToday);
                $statYesterdayHp += intval($cmd->getStatistique($startdateyesterday->format('Y-m-d 00:00:00'), $enddateyesterday)['max']) - intval($cmd->getStatistique($startdateyesterday->format('Y-m-d 00:00:00'), $enddateyesterday)['min']);
                log::add('teleinfo', 'debug', 'Total HP --> ' . $statTodayHp);
            }

            foreach ($statProdToCumul as $key => $value) {
                $cmd              = cmd::byId($value);
                $statProdMaxToday = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'))['max'];
                $statProdMinToday = $cmd->getStatistique($startDateToday->format('Y-m-d 00:00:00'), $endDateToday->format('Y-m-d H:i:s'))['min'];
                log::add('teleinfo', 'debug', 'Commande Production N°' . $value);
                log::add('teleinfo', 'debug', ' ==> Valeur MAX : ' . $statProdMaxToday);
                log::add('teleinfo', 'debug', ' ==> Valeur MIN : ' . $statProdMinToday);

                $statTodayProd     += intval($statProdMaxToday) - intval($statProdMinToday);
                log::add('teleinfo', 'debug', 'Total Production --> ' . $statTodayProd);
            }

            
            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getConfiguration('type') == "stat") {
                    switch ($cmd->getConfiguration('info_conso')) {
                        case "STAT_TODAY":
                            if (intval($statTodayTotal)!=0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière STAT_TODAY 1 ==>', __FILE__) . ' ' . intval($statTodayTotal));
								$cmd->event(intval($statTodayTotal));
							}
							else {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière STAT_TODAY 2 ==>', __FILE__) . ' ' . intval($statTodayIndex00));
								$cmd->event(intval($statTodayIndex00));
							}								
                            break;
                        case "STAT_TODAY_HP":
                            log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' (HP) ==> ' . intval($statTodayHp));
                            $cmd->event(intval($statTodayHp));
                            break;
                        case "STAT_TODAY_HC":
                            log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' (HC) ==> ' . intval($statTodayHc));
                            $cmd->event(intval($statTodayHc));
                            break;
                        case "STAT_TODAY_PROD":
                            log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' (PROD) ==> ' . intval($statTodayProd));
                            $cmd->event(intval($statTodayProd));
                            break;
                        case "STAT_TODAY_INDEX00":
							//if ($statTodayIndex00 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 00 ==> ' . intval($statTodayIndex00));
								$cmd->event(intval($statTodayIndex00));
							//}
							break;
                        case "STAT_TODAY_INDEX01":
							//if ($statTodayIndex01 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 01 ==> ' . intval($statTodayIndex01));
								$cmd->event(intval($statTodayIndex01));
							//}
							break;
                        case "STAT_TODAY_INDEX02":
							//if ($statTodayIndex02 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 02 ==> ' . intval($statTodayIndex02));
								$cmd->event(intval($statTodayIndex02));
							//}
							break;
                        case "STAT_TODAY_INDEX03":
							//if ($statTodayIndex03 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 03 ==> ' . intval($statTodayIndex03));
								$cmd->event(intval($statTodayIndex03));
							//}
							break;
                        case "STAT_TODAY_INDEX04":
							//if ($statTodayIndex04 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 04 ==> ' . intval($statTodayIndex04));
								$cmd->event(intval($statTodayIndex04));
							//}
							break;
                        case "STAT_TODAY_INDEX05":
							//if ($statTodayIndex05 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 05 ==> ' . intval($statTodayIndex05));
								$cmd->event(intval($statTodayIndex05));
							//}
							break;
                        case "STAT_TODAY_INDEX06":
							//if ($statTodayIndex06 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 06 ==> ' . intval($statTodayIndex06));
								$cmd->event(intval($statTodayIndex06));
							//}
							break;
                        case "STAT_TODAY_INDEX07":
							//if ($statTodayIndex07 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 07 ==> ' . intval($statTodayIndex07));
								$cmd->event(intval($statTodayIndex07));
							//}
							break;
                        case "STAT_TODAY_INDEX08":
							//if ($statTodayIndex08 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 08 ==> ' . intval($statTodayIndex08));
								$cmd->event(intval($statTodayIndex08));
							//}
							break;
                        case "STAT_TODAY_INDEX09":
							//if ($statTodayIndex09 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 09 ==> ' . intval($statTodayIndex09));
								$cmd->event(intval($statTodayIndex09));
							//}
							break;
                        case "STAT_TODAY_INDEX10":
							//if ($statTodayIndex10 > 0) {
								log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' Index 10 ==> ' . intval($statTodayIndex10));
								$cmd->event(intval($statTodayIndex10));
							//}
							break;
                            case "STAT_TODAY_INDEX00_COUT":
                                //if ($Coutindex00 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 00 ==> ' . round($Coutindex00,2));
                                    $cmd->event(round($Coutindex00,2));
                                //}
                                break;
                            case "STAT_TODAY_INDEX01_COUT":
                                //if ($Coutindex01 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 01 ==> ' . round($Coutindex01,2));
                                    $cmd->event(round($Coutindex01,2));
                                //}
                                break;
                            case "STAT_TODAY_INDEX02_COUT":
                                //if ($Coutindex02 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 02 ==> ' . round($Coutindex02,2));
                                    $cmd->event(round($Coutindex02,2));
                                //}
                                break;
                            case "STAT_TODAY_INDEX03_COUT":
                                //if ($Coutindex03 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 03 ==> ' . round($Coutindex03,2));
                                    $cmd->event(round($Coutindex03,2));
                                //}
                                break;
                            case "STAT_TODAY_INDEX04_COUT":
                                //if ($Coutindex04 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 04 ==> ' . round($Coutindex04,2));
                                    $cmd->event(round($Coutindex04,2));
                                //}
                                break;
                            case "STAT_TODAY_INDEX05_COUT":
                                //if ($Coutindex05 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 05 ==> ' . round($Coutindex05,2));
                                    $cmd->event(round($Coutindex05,2));
                                //}
                                break;
                            case "STAT_TODAY_INDEX06_COUT":
                                //if ($Coutindex06 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 06 ==> ' . round($Coutindex06,2));
                                    $cmd->event(round($Coutindex06,2,2));
                                //}
                                break;
                            case "STAT_TODAY_INDEX07_COUT":
                                //if ($Coutindex07 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 07 ==> ' . round($Coutindex07,2));
                                    $cmd->event(round($Coutindex07,2));
                                //}
                                break;
                            case "STAT_TODAY_INDEX08_COUT":
                                //if ($Coutindex08 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 08 ==> ' . round($Coutindex08,2));
                                    $cmd->event(round($Coutindex08,2));
                                //}
                                break;
                            case "STAT_TODAY_INDEX09_COUT":
                                //if ($Coutindex09 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 09 ==> ' . round($Coutindex09,2));
                                    $cmd->event(round($Coutindex09,2));
                                //}
                                break;
                            case "STAT_TODAY_INDEX10_COUT":
                                //if ($Coutindex10 > 0) {
                                    log::add('teleinfo', 'info', __('Mise à jour de la statistique journalière', __FILE__) . ' coût Index 10 ==> ' . round($Coutindex10,2));
                                    $cmd->event(round($Coutindex10,2));
                                //}
                                break;
                            case "TENDANCE_DAY":
                            log::add('teleinfo', 'debug', 'Mise à jour de la tendance journalière ==> ' . '(Hier : ' . intval($statYesterdayHc + $statYesterdayHp) . ' Aujourd\'hui : ' . intval($statTodayHc + $statTodayHp) . ' Différence : ' . (intval($statYesterdayHc + $statYesterdayHp) - intval($statTodayHc + $statTodayHp)) . ')');
                            $cmd->event(intval($statYesterdayHc + $statYesterdayHp) - intval($statTodayHc + $statTodayHp));
                            break;
                    }
                }
            }
        }
        log::add('teleinfo', 'info', '----------------------------------------------');
    }


    public static function calculateOtherStats()
    {
        $indexConsoHP      = config::byKey('indexConsoHP', 'teleinfo', 'EASF02,EASF04,EASF06,HCHP,BBRHPJB,BBRHPJW,BBRHPJR,EJPHPM');
        $indexConsoHC      = config::byKey('indexConsoHC', 'teleinfo', 'EASF01,EASF03,EASF05,HCHC,BBRHCJB,BBRHCJW,BBRHCJR,EJPHN');
        $indexProduction   = config::byKey('indexProduction', 'teleinfo', 'EAIT');
        $indexConsoTotales   = config::byKey('indexConsoTotales', 'teleinfo', 'BASE,EAST,HCHP,HCHC,BBRHPJB,BBRHPJW,BBRHPJR,BBRHCJB,BBRHCJW,BBRHCJR,EJPHPM,EJPHN');
        log::add('teleinfo', 'info', __('----- Calcul des statistiques de la journée -----', __FILE__));
        foreach (eqLogic::byType('teleinfo') as $eqLogic) {
            $startDay            = (new DateTime())->setTimestamp(mktime(0, 0, 0, date("m"), date("d"), date("Y")));
            $endDay              = (new DateTime())->setTimestamp(mktime(23, 59, 59, date("m"), date("d"), date("Y")));
            $startDay->sub(new DateInterval('P1D'));
            $endDay->sub(new DateInterval('P1D'));
            $statYesterdayHc     = 0;
            $statYesterdayHp     = 0;
            $statYesterdayProd   = 0;
            $statHpToCumul       = 0;
            $statHcToCumul       = 0;
            $statYesterdayCoutProd = 0;
            $statYesterdayTotal  = 0;
            $statYesterdayHp     = 0;
            $statYesterdayHc     = 0;
            $statYesterdayProd  = 0;
            $prod                = 0;
            $statHpToCumul       = array();
            $statHcToCumul       = array();
            $statProdToCumul     = array();
            $statTotalToCumul    = array();
            $idIndexProd         = 0;


            // raz des variables
            for ($i=0; $i <= 10; $i++){
                if ($i == 10) {   //affectation des variables index en dynamique
                    $a = 'idIndex' . $i;
                    $b = 'statYesterdayTotalIndex' . $i;
                    $c = 'statYesterdayCoutTotalIndex' . $i;
                    $d = 'Coutindex' . $i;
                    $e = 'Coutkwhindex' . $i;
                    $f = 'index' . $i;
                    $g = 'idCoutIndex' . $i;
                } 
                else {
                    $a = 'idIndex0' . $i;
                    $b = 'statYesterdayTotalIndex0' . $i;
                    $c = 'statYesterdayCoutTotalIndex0' . $i;
                    $d = 'Coutindex0' . $i;
                    $e = 'Coutkwhindex0' . $i;
                    $f = 'index0' . $i;
                    $g = 'idCoutIndex0' . $i;
                }
                $$a = 0;
                $$b = 0;
                $$c = 0;
                $$d = 0;
                $$e = '';
                $$f = '';
                $$g = 0;
            }
            

            log::add('teleinfo', 'info', '--------------------------------------------------');
            log::add('teleinfo', 'info', '----- Compteur : ' . $eqLogic->getName() . ' -----');
            log::add('teleinfo', 'info', '--------------------------------------------------');
			$index01 = $eqLogic->getConfiguration('index01');
			$index02 = $eqLogic->getConfiguration('index02');
			$index03 = $eqLogic->getConfiguration('index03');
			$index04 = $eqLogic->getConfiguration('index04');
			$index05 = $eqLogic->getConfiguration('index05');
			$index06 = $eqLogic->getConfiguration('index06');
			$index07 = $eqLogic->getConfiguration('index07');
			$index08 = $eqLogic->getConfiguration('index08');
			$index09 = $eqLogic->getConfiguration('index09');
			$index10 = $eqLogic->getConfiguration('index10');
            $prod    = intval($eqLogic->getConfiguration('ActivationProduction'));
            $linky = config::byKey('linky', 'teleinfo');

            if ((floatval($eqLogic->getConfiguration('CoutindexProd')) <> 0) && ($prod == 1)) {
                $CoutIndexProd = floatval($eqLogic->getConfiguration('CoutindexProd'));
                log::add('teleinfo', 'info', 'EAIT revenus au kWh = ' . strval($CoutIndexProd));
            }else{
                $CoutIndexProd = 0;
            }

            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getConfiguration('type') == "data" || $cmd->getConfiguration('type') == "") {
                    if (strpos($indexConsoHP, $cmd->getConfiguration('info_conso')) !== false) {
                        array_push($statHpToCumul, $cmd->getId());
                    }
                    if (strpos($indexConsoHC, $cmd->getConfiguration('info_conso')) !== false) {
                        array_push($statHcToCumul, $cmd->getId());
                    }
                    if ((strpos($indexProduction, $cmd->getConfiguration('info_conso')) !== false) && ($prod <> 1)){
                        array_push($statProdToCumul, $cmd->getId());
                    }
                    if (strpos($indexConsoTotales, $cmd->getConfiguration('info_conso')) !== false) {
                        array_push($statTotalToCumul, $cmd->getId());
                    }
                }
				if (($cmd->getConfiguration('info_conso') == 'EAIT') && ($prod == 1)) {
					$IndexProd = $cmd->getId();
					log::add('teleinfo', 'info', 'EAIT = ' . $IndexProd);
				}
				if (($cmd->getConfiguration('info_conso') == 'BASE') || ($cmd->getConfiguration('info_conso') == 'EAST')) {
					$index00 = $cmd->getConfiguration('info_conso');
					$idIndex00 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index 00 --> ' . $index00);
					log::add('teleinfo', 'debug', 'Id Index00 ' . $idIndex00);
				}
				if ($cmd->getConfiguration('info_conso') == $index01) {
					$idIndex01 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index01 ' . $idIndex01);
				}
				if ($cmd->getConfiguration('info_conso') == $index02) {
					$idIndex02 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index02 ' . $idIndex02);
				}
				if ($cmd->getConfiguration('info_conso') == $index03) {
					$idIndex03 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index03 ' . $idIndex03);
				}
				if ($cmd->getConfiguration('info_conso') == $index04) {
					$idIndex04 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index04 ' . $idIndex04);
				}
				if ($cmd->getConfiguration('info_conso') == $index05) {
					$idIndex05 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index05 ' . $idIndex05);
				}
				if ($cmd->getConfiguration('info_conso') == $index06) {
					$idIndex06 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index06 ' . $idIndex06);
				}
				if ($cmd->getConfiguration('info_conso') == $index07) {
					$idIndex07 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index07 ' . $idIndex07);
				}
				if ($cmd->getConfiguration('info_conso') == $index08) {
					$idIndex08 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index08 ' . $idIndex08);
				}
				if ($cmd->getConfiguration('info_conso') == $index09) {
					$idIndex09 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index09 ' . $idIndex09);
				}
				if ($cmd->getConfiguration('info_conso') == $index10) {
					$idIndex10 = $cmd->getId();
					log::add('teleinfo', 'info', 'Index10 ' . $idIndex10);
				}
				if (($cmd->getLogicalId() == 'STAT_TODAY_PROD')) {
					$idIndexProd = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id STAT_TODAY_PROD ' . $idIndexProd);
				}
				if (($cmd->getLogicalId() == 'STAT_TODAY_INDEX00_COUT')) {
					$idCoutIndex00 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index00 ' . $idCoutIndex00);
				}
				if ($cmd->getLogicalId() == 'STAT_TODAY_INDEX01_COUT') {
					$idCoutIndex01 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index01 ' . $idCoutIndex01);
				}
				if ($cmd->getLogicalId() == 'STAT_TODAY_INDEX02_COUT') {
					$idCoutIndex02 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index02 ' . $idCoutIndex02);
				}
				if ($cmd->getLogicalId() == 'STAT_TODAY_INDEX03_COUT') {
					$idCoutIndex03 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index03 ' . $idCoutIndex03);
				}
				if ($cmd->getLogicalId() == 'STAT_TODAY_INDEX04_COUT') {
					$idCoutIndex04 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index04 ' . $idCoutIndex04);
				}
				if ($cmd->getLogicalId() == 'STAT_TODAY_INDEX05_COUT') {
					$idCoutIndex05 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index05 ' . $idCoutIndex05);
				}
				if ($cmd->getLogicalId() == 'STAT_TODAY_INDEX06_COUT') {
					$idCoutIndex06 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index06 ' . $idCoutIndex06);
				}
				if ($cmd->getLogicalId() == 'STAT_TODAY_INDEX07_COUT') {
					$idCoutIndex07 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index07 ' . $idCoutIndex07);
				}
				if ($cmd->getLogicalId() == 'STAT_TODAY_INDEX08_COUT') {
					$idCoutIndex08 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index08 ' . $idCoutIndex08);
				}
				if ($cmd->getLogicalId() == 'STAT_TODAY_INDEX09_COUT') {
					$idCoutIndex09 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index09 ' . $idCoutIndex09);
				}
				if ($cmd->getLogicalId() == 'STAT_TODAY_INDEX10_COUT') {
					$idCoutIndex10 = $cmd->getId();
					log::add('teleinfo', 'debug', 'Id Cout Index10 ' . $idCoutIndex10);
				}
            }

            if ($prod = 1){
                //log::add('teleinfo', 'debug', 'Index EAIT = ' . $idIndexProd);
				$cmd = cmd::byId($idIndexProd);
                $statYesterdayProd = intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['max']);
                log::add('teleinfo', 'debug', 'Total PROD hier --> ' . $statYesterdayProd);
                $statYesterdayCoutProd = $statYesterdayProd * $CoutIndexProd / 1000;
                log::add('teleinfo', 'debug', 'Total Revenus Prod hier --> ' . $statYesterdayCoutProd);
            }

            

			for ($i=0; $i <= 10; $i++){
				if ($i == 10) {   //affectation des variables index en dynamique
					$a = 'idIndex' . $i;
					$c = 'statYesterdayTotalIndex' . $i;
                    $d = 'idCoutIndex' . $i;
                    $e = 'statYesterdayCoutTotalIndex' . $i;
				}
				else {
					$a = 'idIndex0' . $i;
					$c = 'statYesterdayTotalIndex0' . $i;
                    $d = 'idCoutIndex0' . $i;
                    $e = 'statYesterdayCoutTotalIndex0' . $i;
				}
				if (${$a} >= 1) {
					log::add('teleinfo', 'debug', 'Index à trouver ' . $i . ' = ' . $a);
					log::add('teleinfo', 'debug', 'Id Index ' . $i . ' = ' . ${$a});
					$cmd = cmd::byId(${$a});
					$$c = intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['max']) - intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['min']);
					log::add('teleinfo', 'debug', 'Total Index ' . $i . ' hier --> ' . ${$c});
                    $cmd = cmd::byId(${$d});
                    $$e = floatval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['max']);
					if ($i==0){ 
                        $statYesterdayTotalIndex00Init = $statYesterdayTotalIndex00;
                        $statYesterdayCoutTotalIndex00Init = $statYesterdayCoutTotalIndex00;
                        $statYesterdayTotalIndex00 = 0;
                        $statYesterdayCoutTotalIndex00 = 0;
                    }else{
                        $statYesterdayTotalIndex00 += ${$c};
                        $statYesterdayCoutTotalIndex00 += ${$e};
                        $statYesterdayTotalIndex00Init = 0;
                        $statYesterdayCoutTotalIndex00Init = 0;
                    }

                    log::add('teleinfo', 'debug', 'Total Cout Index ' . $i . ' hier --> ' . ${$e} . ' id coût index : ' . ${$d} . ' Conso index : ' . ${$c} . ' id index : ' . ${$a});
                }
			}
            $statYesterdayTotalIndex00 += $statYesterdayTotalIndex00Init;
            $statYesterdayCoutTotalIndex00 += $statYesterdayCoutTotalIndex00Init;


            foreach ($statTotalToCumul as $key => $value) {
                log::add('teleinfo', 'debug', 'Commande Totale N°' . $value);
                $cmd               = cmd::byId($value);
                $statYesterdayTotal	 += intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['max']) - intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['min']);
            }
            foreach ($statHcToCumul as $key => $value) {
                log::add('teleinfo', 'debug', 'Commande HC N°' . $value);
                $cmd               = cmd::byId($value);
                $statYesterdayHc	 += intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['max']) - intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['min']);
            }
            foreach ($statHpToCumul as $key => $value) {
                log::add('teleinfo', 'debug', 'Commande HP N°' . $value);
                $cmd               = cmd::byId($value);
                $statYesterdayHp 	 += intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['max']) - intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['min']);
            }

            if ($prod <> 1){
                foreach ($statProdToCumul as $key => $value) {
                    log::add('teleinfo', 'debug', 'Commande Prod N°' . $value);
                    $cmd                  = cmd::byId($value);
                    $statYesterdayProd 	 += intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['max']) - intval($cmd->getStatistique($startDay->format('Y-m-d 00:00:00'), $endDay->format('Y-m-d 23:59:59'))['min']);
                }
            }

            foreach ($eqLogic->getCmd('info') as $cmd) {
                if ($cmd->getConfiguration('type') == "stat" || $cmd->getConfiguration('type') == "panel") {
                    //$history = new history();
                    //$history->setCmd_id($cmd->getId());
                    //$history->setDatetime($startDay->format('Y-m-d 00:00:00'));
                    //$history->setTableName('historyArch');
                    $test = $cmd->getConfiguration('info_conso');
                            switch (true) {
                        case ($test==="STAT_YESTERDAY"):
                            log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier ==> ' . intval($statYesterdayTotal));
                            $cmd->event(intval($statYesterdayTotal), $startDay->format('Y-m-d 00:00:00'));
                            //$history->setValue(intval($statYesterdayHc) + intval($statYesterdayHp));
                            //$history->save();
                            break;
                        case ($test==="STAT_YESTERDAY_HP"):
                            log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (HP) ==> ' . intval($statYesterdayHp));
                            $cmd->event((intval($statYesterdayHp)), $startDay->format('Y-m-d 00:00:00'));
                            //$history->setValue(intval($statYesterdayHp));
                            //$history->save();
                            break;
                        case ($test==="STAT_YESTERDAY_HC"):
                            log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (HC) ==> ' . intval($statYesterdayHc));
                            $cmd->event((intval($statYesterdayHc)), $startDay->format('Y-m-d 00:00:00'));
                            //$history->setValue(intval($statYesterdayHc));
                            //$history->save();
                            break;
                        case ($test==="STAT_YESTERDAY_PROD"):
                            log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (PROD) ==> ' . intval($statYesterdayProd));
                            $cmd->event($statYesterdayProd, $startDay->format('Y-m-d 00:00:00'));
                            //$history->setValue(intval($statYesterdayProd));
                            //$history->save();
                            break;
						case ($test==="STAT_YESTERDAY_INDEX00"):
                            //if ($statYesterdayTotalIndex00 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index00) ==> ' . intval($statYesterdayTotalIndex00));
								$cmd->event((intval($statYesterdayTotalIndex00)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
						case ($test==="STAT_YESTERDAY_INDEX01"):
                            //if ($statYesterdayTotalIndex01 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index01) ==> ' . intval($statYesterdayTotalIndex01));
								$cmd->event((intval($statYesterdayTotalIndex01)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
						case ($test==="STAT_YESTERDAY_INDEX02"):
                            //if ($statYesterdayTotalIndex02 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index02) ==> ' . intval($statYesterdayTotalIndex02));
								$cmd->event((intval($statYesterdayTotalIndex02)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
						case ($test==="STAT_YESTERDAY_INDEX03"):
                            //if ($statYesterdayTotalIndex03 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index03) ==> ' . intval($statYesterdayTotalIndex03));
								$cmd->event((intval($statYesterdayTotalIndex03)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
						case ($test==="STAT_YESTERDAY_INDEX04"):
                            //if ($statYesterdayTotalIndex04 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index04) ==> ' . intval($statYesterdayTotalIndex04));
								$cmd->event((intval($statYesterdayTotalIndex04)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
						case ($test==="STAT_YESTERDAY_INDEX05"):
                            //if ($statYesterdayTotalIndex05 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index05) ==> ' . intval($statYesterdayTotalIndex05));
								$cmd->event((intval($statYesterdayTotalIndex05)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
						case ($test==="STAT_YESTERDAY_INDEX06"):
                            //if ($statYesterdayTotalIndex06 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index06) ==> ' . intval($statYesterdayTotalIndex06));
								$cmd->event((intval($statYesterdayTotalIndex06)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
						case ($test==="STAT_YESTERDAY_INDEX07"):
                            //if ($statYesterdayTotalIndex07 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index07) ==> ' . intval($statYesterdayTotalIndex07));
								$cmd->event((intval($statYesterdayTotalIndex07)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
						case ($test==="STAT_YESTERDAY_INDEX08"):
                            //if ($statYesterdayTotalIndex08 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index08) ==> ' . intval($statYesterdayTotalIndex08));
								$cmd->event((intval($statYesterdayTotalIndex08)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
						case ($test==="STAT_YESTERDAY_INDEX09"):
                            //if ($statYesterdayTotalIndex09 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index09) ==> ' . intval($statYesterdayTotalIndex09));
								$cmd->event((intval($statYesterdayTotalIndex09)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
						case ($test==="STAT_YESTERDAY_INDEX10"):
                            //if ($statYesterdayTotalIndex10 != 0) {
								log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (Index10) ==> ' . intval($statYesterdayTotalIndex10));
								$cmd->event((intval($statYesterdayTotalIndex10)), $startDay->format('Y-m-d 00:00:00'));
							//}
							break;
                        
                        case ($test==="STAT_YESTERDAY_PROD_COUT"):
                            //if ($statYesterdayCoutProd != 0) {
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique cout hier (PROD) ==> ' . strval($statYesterdayCoutProd));
                                $cmd->event((floatval($statYesterdayCoutProd)), $startDay->format('Y-m-d 00:00:00'));
                            //}
                            break;
                        
                        case (strpos($test,'YESTERDAY_INDEX')!=0 && strpos($test,'COUT')!=0):
                            $indexyy = (int)(substr($test,20,2));
                            if($indexyy == 0){
                                $cmd->event((floatval($statYesterdayCoutTotalIndex00)), $startDay->format('Y-m-d 00:00:00'));
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index00) ==> ' . floatval($statYesterdayCoutTotalIndex00));
                            }
                            if($indexyy == 1){
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index01) ==> ' . floatval($statYesterdayCoutTotalIndex01));
								$cmd->event((floatval($statYesterdayCoutTotalIndex01)), $startDay->format('Y-m-d 00:00:00'));
                            }
                            if($indexyy == 2){
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index02) ==> ' . floatval($statYesterdayCoutTotalIndex02));
								$cmd->event((floatval($statYesterdayCoutTotalIndex02)), $startDay->format('Y-m-d 00:00:00'));
                            }
                            if($indexyy == 3){
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index03) ==> ' . floatval($statYesterdayCoutTotalIndex03));
								$cmd->event((floatval($statYesterdayCoutTotalIndex03)), $startDay->format('Y-m-d 00:00:00'));
                            }
                            if($indexyy == 4){
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index04) ==> ' . floatval($statYesterdayCoutTotalIndex04));
								$cmd->event((floatval($statYesterdayCoutTotalIndex04)), $startDay->format('Y-m-d 00:00:00'));
                            }
                            if($indexyy == 5){
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index05) ==> ' . floatval($statYesterdayCoutTotalIndex05));
								$cmd->event((floatval($statYesterdayCoutTotalIndex05)), $startDay->format('Y-m-d 00:00:00'));
                            }
                            if($indexyy == 6){
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index06) ==> ' . floatval($statYesterdayCoutTotalIndex06));
								$cmd->event((floatval($statYesterdayCoutTotalIndex06)), $startDay->format('Y-m-d 00:00:00'));
                            }
                            if($indexyy == 7){
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index07) ==> ' . floatval($statYesterdayCoutTotalIndex07));
								$cmd->event((floatval($statYesterdayCoutTotalIndex07)), $startDay->format('Y-m-d 00:00:00'));
                            }
                            if($indexyy == 8){
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index08) ==> ' . floatval($statYesterdayCoutTotalIndex08));
								$cmd->event((floatval($statYesterdayCoutTotalIndex08)), $startDay->format('Y-m-d 00:00:00'));
                            }
                            if($indexyy == 9){
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index09) ==> ' . floatval($statYesterdayCoutTotalIndex09));
								$cmd->event((floatval($statYesterdayCoutTotalIndex09)), $startDay->format('Y-m-d 00:00:00'));
                            }
                            if($indexyy == 10){
                                log::add('teleinfo', 'debug', 'Mise à jour de la statistique coût hier (Index10) ==> ' . floatval($statYesterdayCoutTotalIndex10));
								$cmd->event((floatval($statYesterdayCoutTotalIndex10)), $startDay->format('Y-m-d 00:00:00'));
                            }
                        break;
                    }
                }
            }
        }
    log::add('teleinfo', 'info', 'other stats -------------------------------------');
}

    public static function cleanDBTeleinfo(){
        log::add('teleinfo_clean','info', "Début de l'opération de nettoyage de la base de données.");
        foreach (eqLogic::byType('teleinfo') as $eqLogic) {
            if ($eqLogic->getConfiguration('cleanDBTeleinfo') == 1) {
                log::add('teleinfo_clean', 'info', 'nettoyage compteur '. $eqLogic->getName());
                foreach ($eqLogic->getCmd('info') as $cmd) {
                    if ($cmd->getIsHistorized()==1){
                        $minParHeure = array();
                        $valuesClean = 0;
                        $donneeOptimized = $cmd->getLogicalId(); //init('logicalid')
                        $donneeType = $cmd->getConfiguration('type'); //init('type')
                        $donneeId = $cmd->getId(); //init('id')
                        $replaceValues = '';
                        $deleteValues = '';
                        log::add('teleinfo_clean', 'info', sprintf(__("Optimisation de l'historique de %s, cela peut prendre du temps.", __FILE__), $donneeOptimized));
                        
                        //compter le nb de ligne
                        $sql = "SELECT COUNT(*) FROM historyArch WHERE cmd_id=:cmdId";
                        $values = array(
                            'cmdId' => $cmd->getId(),
                        );
                        $valeursDepartDB = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
                        $valeursDepart = $valeursDepartDB['COUNT(*)'];
                        log::add('teleinfo_clean', 'info', __("Nombre d'enregistrements:", __FILE__) . ' ' . $valeursDepart);

                        $sql = 'SELECT COUNT(*) FROM historyArch WHERE cmd_id=:cmdId AND MINUTE(datetime) <> "0" AND (HOUR(datetime) <> "23" AND MINUTE(datetime) <> "59")';
                        $values = array(
                            'cmdId' => $cmd->getId(),
                        );
                        $valeursEffacerDB = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
                        $valeursEffacer = $valeursEffacerDB['COUNT(*)'];
                        log::add('teleinfo_clean', 'info', __('Enregistrements à effacer:', __FILE__) . ' ' . $valeursEffacer);

                        if ($valeursEffacer > 1000){                      
                            
                            //test si on a affaire à un stat_
                            if (strpos($donneeOptimized, 'STAT_') !== 0){
                                //sélectionne le min par heure
                                if ($donneeType != "AVG"){
                                    $sql = "SELECT cmd_id,datetime,value FROM historyArch WHERE (cmd_id=:cmdId) GROUP BY YEAR(datetime),MONTH(datetime),DAY(datetime),HOUR(datetime)";
                                }else{
                                    $sql = "SELECT cmd_id, FROM_UNIXTIME(AVG(UNIX_TIMESTAMP(datetime))) as datetime, (CAST(value AS DECIMAL(12,2))) as value FROM historyArch WHERE (cmd_id=:cmdId) GROUP BY YEAR(datetime),MONTH(datetime),DAY(datetime),HOUR(datetime)";
                                }
                                $values = array(
                                            'cmdId' => $donneeId,
                                );
                                $minParHeure = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);

                                //sélectionne le max de la journée
                                if ($donneeType != "AVG"){
                                    $sql = "SELECT cmd_id,datetime, max(value) as value 
                                        FROM historyArch 
                                        WHERE (cmd_id=:cmdId) AND `datetime` < date(NOW())
                                        GROUP BY YEAR(datetime),MONTH(datetime),DAY(datetime)";
                                    $maxJournee = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
                                }

                                log::add('teleinfo_clean', 'info', __('Les données sont stockées dans une variable, passons à la suppression du superflu, la phase la plus longue...', __FILE__));

                                // Nettoyage de toutes les valeurs
                                $sql = "DELETE FROM historyArch WHERE cmd_id=:cmdId";
                                $values = array(
                                                'cmdId' => $donneeId,
                                );
                                $deleteValues = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
                                log::add('teleinfo_clean', 'info', __('Les anciennes données sont supprimées, passons à la remise en place des valeurs stockées', __FILE__));

                                //remise des données purgées en place
                                $valuesClean=0;
                                foreach ($minParHeure as $cle => $valeur) {
                                    $sql = "REPLACE INTO historyArch SET cmd_id=:cmdId,datetime=:newDatetime,value=:newValue";
                                    $values = array(
                                        'cmdId' => $donneeId,
                                        'newDatetime' => date('Y-m-d H:00:00', strtotime($valeur['datetime'])),
                                        'newValue' => $valeur['value'],
                                    );
                                    $replaceValues = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
                                }
                                if ($donneeType !== "AVG"){
                                    foreach ($maxJournee as $cle2 => $valeur2){
                                        $sql = "REPLACE INTO historyArch SET cmd_id=:cmdId,datetime=:newDatetime,value=:newValue";
                                        $values = array(
                                            'cmdId' => $donneeId,
                                            'newDatetime' => date('Y-m-d 23:59:59', strtotime($valeur2['datetime'])),
                                            'newValue' => $valeur2['value'],
                                        );
                                        $replaceValues = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
                                    }
                                }
                            }else{
                                if (strpos($donneeOptimized, 'STAT_YESTERDAY') === 0){
                                    //si on a affaire à des 'stat_yesterday' alors il ne faut garder que le max de la journée
                                    $sql = "SELECT cmd_id,datetime,max(value) as value FROM historyArch WHERE (cmd_id=:cmdId) GROUP BY YEAR(datetime),MONTH(datetime),DAY(datetime)";
                                    $values = array(
                                                'cmdId' => $donneeId,
                                    );
                                    $minParJour = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
                                    log::add('teleinfo_clean', 'info', __('Les données sont stockées dans une variable, passons à la suppression du superflu, la phase la plus longue...', __FILE__));
                        
                                    // Nettoyage de toutes les valeurs
                                    $sql = "DELETE FROM historyArch WHERE cmd_id=:cmdId";
                                    $values = array(
                                                    'cmdId' => $donneeId,
                                    );
                                    $deleteValues = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
                                    log::add('teleinfo_clean', 'info', __('Les anciennes données sont supprimées, passons à la remise en place des valeurs stockées', __FILE__));

                                    //remise des données purgées en place
                                    $valuesClean=0;
                                    foreach ($minParJour as $cle => $valeur) {
                                        $sql = "REPLACE INTO historyArch SET cmd_id=:cmdId,datetime=:newDatetime,value=:newValue";
                                        $values = array(
                                            'cmdId' => $donneeId,
                                            'newDatetime' => date('Y-m-d 00:00:00', strtotime($valeur['datetime'])),
                                            'newValue' => $valeur['value'],
                                        );
                                        $replaceValues = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
                                    }
                                }else{
                                    if (strpos($donneeOptimized, 'STAT_TODAY') === 0){
                                        //si on a affaire à des 'stat_today' alors il ne faut garder que le max de chaque heure
                                        $sql = "SELECT cmd_id,datetime,max(value) as value FROM historyArch WHERE (cmd_id=:cmdId) GROUP BY YEAR(datetime),MONTH(datetime),DAY(datetime),HOUR(datetime)";
                                        $values = array(
                                            'cmdId' => $donneeId,
                                        );
                                        $maxParHeure = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
                                        log::add('teleinfo_clean', 'info', __('Les données sont stockées dans une variable, passons à la suppression du superflu, la phase la plus longue...', __FILE__));
                            
                                        // Nettoyage de toutes les valeurs
                                        $sql = "DELETE FROM historyArch WHERE cmd_id=:cmdId";
                                        $values = array(
                                                        'cmdId' => $donneeId,
                                        );
                                        $deleteValues = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
                                        log::add('teleinfo_clean', 'info', __('Les anciennes données sont supprimées, passons à la remise en place des valeurs stockées', __FILE__));

                                        //remise des données purgées en place
                                        $valuesClean=0;
                                        foreach ($maxParHeure as $cle => $valeur) {
                                            $sql = "REPLACE INTO historyArch SET cmd_id=:cmdId,datetime=:newDatetime,value=:newValue";
                                            $values = array(
                                                'cmdId' => $donneeId,
                                                'newDatetime' => date('Y-m-d H:00:00', strtotime($valeur['datetime'])),
                                                'newValue' => $valeur['value'],
                                            );
                                            $replaceValues = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
                                        }
                                    }
                                }

                            }
                            $sql = "SELECT COUNT(*) FROM historyArch WHERE cmd_id=:cmdId";
                            $values = array(
                                'cmdId' => $donneeId,
                            );
                            $valuesCleanDB = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
                            $valuesClean = $valuesCleanDB['COUNT(*)'];

                            log::add('teleinfo_clean','info', sprintf(__('Optimisation de l\'historique terminée. Les données sont remises en place. Il y avait %s lignes de données avant, il en reste', __FILE__), $valeursDepart) . ' ' .$valuesClean);
                        }else{
                            log::add('teleinfo_clean','info', __('Pas assez de données à supprimer => au suivant...', __FILE__));
                        }
                    }
                }
            }
        }
        log::add('teleinfo_clean','info', __("Fin de l'opération de nettoyage de la base de données.", __FILE__));
    }

    public static function copyVersIndex($compteur, $startDate, $endDate,
                                            $indexcopy01,$indexcopy02,$indexcopy03,$indexcopy04,$indexcopy05,$indexcopy06,$indexcopy07,$indexcopy08,$indexcopy09,$indexcopy10,
                                            $coutcopy00,$coutcopy01,$coutcopy02,$coutcopy03,$coutcopy04,$coutcopy05,$coutcopy06,$coutcopy07,$coutcopy08,$coutcopy09,$coutcopy10,$coutcopyprod){
        //fonction pour copier les données issues des anciens index vers les nouveaux
        $date1 = new DateTime($startDate);
        $date2 = new DateTime($endDate);
        $diff = $date2->diff($date1)->format("%a");
        
        $p1j = new DateInterval('P1D');

        event::add('jeedom::alert', array(
                'level' => 'warning',
                'page' => 'teleinfo',
                'message' => sprintf(__(' Copie des anciennes donnéés vers les nouveaux index pour la période du %s au %s soit %s jours à traiter, cela peut prendre un peu de temps veuillez patienter ...', __FILE__), $startDate, $endDate, $diff)));
        $indexcopy = array(' ',$indexcopy01,$indexcopy02,$indexcopy03,$indexcopy04,$indexcopy05,$indexcopy06,$indexcopy07,$indexcopy08,$indexcopy09,$indexcopy10,'EAIT');
        $coutcopy = array($coutcopy00,$coutcopy01,$coutcopy02,$coutcopy03,$coutcopy04,$coutcopy05,$coutcopy06,$coutcopy07,$coutcopy08,$coutcopy09,$coutcopy10,$coutcopyprod);
        if (($coutcopy[0] == 0)||($coutcopy[0] == '')){
            $indexcout00 = false;
        }else{
            $indexcout00 = true;
        }
        
        foreach (eqLogic::byType('teleinfo') as $eqLogic) {
            if ($compteur == $eqLogic->getlogicalId()){
                $indexdestination = array($eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX00'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX01'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX02'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX03'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX04'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX05'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX06'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX07'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX08'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX09'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX10'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_PROD')
                                        );
                $coutdestination = array($eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX00_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX01_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX02_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX03_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX04_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX05_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX06_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX07_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX08_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX09_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_INDEX10_COUT'),
                                            $eqLogic->getCmd('info', 'STAT_YESTERDAY_PROD_COUT')
                                        );
                $iddestination = array();
                $idcoutdest = array();
                for ($i=0;$i<12;$i++){
                    $iddestination[$i] = $indexdestination[$i]->getId();
                    $idcoutdest[$i] = $coutdestination[$i]->getId();
                }                        
                //$linky = config::byKey('linky', 'teleinfo');
                $statIndex00 = 0;

                $indexoriginebase=$eqLogic->getCmd('info', 'BASE');
                $indexorigineeast=$eqLogic->getCmd('info', 'EAST');
                
                // mise dans index origine pour recalcul de tous sauf 00 et prod
                for ($i=1;$i<12;$i++){
                    $indexorigine[$i] = $eqLogic->getCmd('info', $indexcopy[$i]);
                }

                
                for($i=1; $i < ($diff+1); $i++){

                    if ($diff > 9){
                        if (($i % ($diff/10)) == 0){
                                event::add('jeedom::alert', array(
                                        'level' => 'warning',
                                        'page' => 'teleinfo',
                                        'message' => __('Les statistiques sont en cours de création, cela peut prendre un peu de temps veuillez patienter ... (', __FILE__) . intval($i/($diff/100)) . ' %)',
                                ));
                        }
                    }
                    
                    $statTotal1 = 0;
                    $coutotal1 = 0;
                    $statTotal2 = 0;
                    $coutotal2 = 0;
                    $aboBase = false;
                    $aboBaseCouts = false;

                    
                    //recalcul des index + coûts base
                    if ($indexoriginebase<>''){
                        $statTotal1 = intval($indexoriginebase->getStatistique($date2->format('Y-m-d 00:00:00'), $date2->format('Y-m-d 23:59:59'))['max'])
                                            - intval($indexoriginebase->getStatistique($date2->format('Y-m-d 00:00:00'), $date2->format('Y-m-d 23:59:59'))['min']);
                        if ($statTotal1 <> 0 && $indexcout00){ 
                            $coutotal1 = floatval($statTotal1) * floatval($coutcopy[0]) / 1000;
                        }
                    }
                    //recalcul des index + coûts EAST
                    if ($indexorigineeast<>''){
                        $statTotal2 = intval($indexorigineeast->getStatistique($date2->format('Y-m-d 00:00:00'), $date2->format('Y-m-d 23:59:59'))['max'])
                                            - intval($indexorigineeast->getStatistique($date2->format('Y-m-d 00:00:00'), $date2->format('Y-m-d 23:59:59'))['min']);
                        if ($statTotal2 <> 0 && $indexcout00){ 
                            $coutotal2 = floatval($statTotal2) * floatval($coutcopy[0]) / 1000;
                        }
                    }
                    //remise en place des index et coûts base + EAST
                    if (($statTotal1 + $statTotal2) <> 0){
                        $aboBase = true;
                        $history = new history();
                        $history->setCmd_id($iddestination[0]);
                        $history->setDatetime($date2->format('Y-m-d 00:00:00'));
                        $history->setTableName('historyArch');
                        $history->setValue(intval($statTotal1 + $statTotal2));
                        $history->save();
                        if (($coutotal1 + $coutotal2) <> 0){ 
                            //$coutotal2 += $coutotal;
                            $aboBaseCouts = true;
                            $historycout = new history();
                            $historycout->setCmd_id($idcoutdest[0]);
                            $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                            $historycout->setTableName('historyArch');
                            $historycout->setValue(($coutotal1 + $coutotal2));
                            $historycout->save();
                        }else{
                            $historycout = new history();
                            $historycout->setCmd_id($idcoutdest[0]);
                            $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                            $historycout->setTableName('historyArch');
                            $historycout->setValue(' ');
                            $historycout->save();
                        }
                    }else{
                        $history = new history();
                        $history->setCmd_id($iddestination[0]);
                        $history->setDatetime($date2->format('Y-m-d 00:00:00'));
                        $history->setTableName('historyArch');
                        $history->setValue(intval(' '));
                        $history->save();
                        $historycout = new history();
                        $historycout->setCmd_id($idcoutdest[0]);
                        $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                        $historycout->setTableName('historyArch');
                        $historycout->setValue(' ');
                        $historycout->save();
                    }


                    //recalcul des couts prod
                    $statotal2 = 0;
                    $coutotal2 = 0;
                    if ($indexorigine[11]<>''){
                        $statTotal2 = intval($indexorigine[11]->getStatistique($date2->format('Y-m-d 00:00:00'), $date2->format('Y-m-d 23:59:59'))['max'])
                                                - intval($indexorigine[11]->getStatistique($date2->format('Y-m-d 00:00:00'), $date2->format('Y-m-d 23:59:59'))['min']);
                        if ($statTotal2 <> 0){ 
                            $coutotal2 = floatval($statTotal2) * floatval($coutcopy[11]) / 1000;
                            $history = new history();
                            $history->setCmd_id($iddestination[11]);
                            $history->setDatetime($date2->format('Y-m-d 00:00:00'));
                            $history->setTableName('historyArch');
                            $history->setValue(intval($statTotal2));
                            $history->save();
                            if ($coutotal2 <> 0){ 
                                //$coutotal2 += $coutotal;
                                
                                $historycout = new history();
                                $historycout->setCmd_id($idcoutdest[11]);
                                $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                                $historycout->setTableName('historyArch');
                                $historycout->setValue($coutotal2);
                                $historycout->save();
                            }else{
                                $historycout = new history();
                                $historycout->setCmd_id($idcoutdest[11]);
                                $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                                $historycout->setTableName('historyArch');
                                $historycout->setValue(' ');
                                $historycout->save();
                            }
                        }else{
                            $history = new history();
                            $history->setCmd_id($iddestination[11]);
                            $history->setDatetime($date2->format('Y-m-d 00:00:00'));
                            $history->setTableName('historyArch');
                            $history->setValue(' ');
                            $history->save();
                            $historycout = new history();
                            $historycout->setCmd_id($idcoutdest[11]);
                            $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                            $historycout->setTableName('historyArch');
                            $historycout->setValue(' ');
                            $historycout->save();
                        }
                    }



                    
                    $statTotal1 = 0;
                    $coutotal1 = 0;
                    $statTotal2 = 0;
                    $coutotal2 = 0;
                    $coutotal = 0;

                    //recalcul des index de 1 à 10
                    for ($j=1;$j<11;$j++){
                        if ($indexcopy[$j]<>''){
                            if(floatval($coutcopy[$j])<>0){
                                $statTotal1 = intval($indexorigine[$j]->getStatistique($date2->format('Y-m-d 00:00:00'), $date2->format('Y-m-d 23:59:59'))['max'])
                                            - intval($indexorigine[$j]->getStatistique($date2->format('Y-m-d 00:00:00'), $date2->format('Y-m-d 23:59:59'))['min']);
                                

                                if ($statTotal1 <> 0){ 
                                    $statTotal2 += $statTotal1;
                                    $coutotal = floatval($statTotal1) * floatval($coutcopy[$j]) / 1000;
                                    $history = new history();
                                    $history->setCmd_id($iddestination[$j]);
                                    $history->setDatetime($date2->format('Y-m-d 00:00:00'));
                                    $history->setTableName('historyArch');
                                    $history->setValue(intval($statTotal1));
                                    $history->save();
                                    if ($coutotal <> 0){ 
                                        $coutotal2 += $coutotal;
                                        $historycout = new history();
                                        $historycout->setCmd_id($idcoutdest[$j]);
                                        $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                                        $historycout->setTableName('historyArch');
                                        $historycout->setValue(($coutotal));
                                        $historycout->save();
                                    }else{
                                        $historycout = new history();
                                        $historycout->setCmd_id($idcoutdest[$j]);
                                        $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                                        $historycout->setTableName('historyArch');
                                        $historycout->setValue(' ');
                                        $historycout->save();
                                    }
                                }else{
                                    $history = new history();
                                    $history->setCmd_id($iddestination[$j]);
                                    $history->setDatetime($date2->format('Y-m-d 00:00:00'));
                                    $history->setTableName('historyArch');
                                    $history->setValue(' ');
                                    $history->save();
                                    $historycout = new history();
                                    $historycout->setCmd_id($idcoutdest[$j]);
                                    $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                                    $historycout->setTableName('historyArch');
                                    $historycout->setValue(' ');
                                    $historycout->save();   
                                    }
                            }else{
                                $history = new history();
                                $history->setCmd_id($iddestination[$j]);
                                $history->setDatetime($date2->format('Y-m-d 00:00:00'));
                                $history->setTableName('historyArch');
                                $history->setValue(' ');
                                $history->save();
                                $historycout = new history();
                                $historycout->setCmd_id($idcoutdest[$j]);
                                $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                                $historycout->setTableName('historyArch');
                                $historycout->setValue(' ');
                                $historycout->save();   
                            }
                        }
                    }
                    // s'il n'y a pas d'index de base (BASE ou EAST) sur la période on prend la somme des index
                    if (!$aboBase){
                        if ($statTotal2 <> 0){
                            $history = new history();
                            $history->setCmd_id($iddestination[0]);
                            $history->setDatetime($date2->format('Y-m-d 00:00:00'));
                            $history->setTableName('historyArch');
                            $history->setValue($statTotal2);
                            $history->save();
                        }else{
                            $history = new history();
                            $history->setCmd_id($iddestination[0]);
                            $history->setDatetime($date2->format('Y-m-d 00:00:00'));
                            $history->setTableName('historyArch');
                            $history->setValue(' ');
                            $history->save();
                        }
                    }
                
                    // s'il n'y a pas de tarif au kwh alors on prend la somme des index
                    if (!$aboBaseCouts){
                        if ($coutotal2 <> 0){
                            $historycout = new history();
                            $historycout->setCmd_id($idcoutdest[0]);
                            $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                            $historycout->setTableName('historyArch');
                            $historycout->setValue($coutotal2);
                            $historycout->save();
                        }else{
                            $historycout = new history();
                            $historycout->setCmd_id($idcoutdest[0]);
                            $historycout->setDatetime($date2->format('Y-m-d 00:00:00'));
                            $historycout->setTableName('historyArch');
                            $historycout->setValue(' ');
                            $historycout->save();
                        }
                    }
                    $date2->sub($p1j);
                }
                foreach ($iddestination as $key => $destination){
                    try{
                        $sql = "DELETE FROM historyArch WHERE (cmd_id=:cmdId) AND (value=' ' OR value = 0)";
                        $values = array(
                            'cmdId' => $destination,
                        );
                        $sql = "DELETE FROM historyArch WHERE (cmd_id=:cmdId) AND (value=' ' OR value = 0)";
                        DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
                    } catch (\Exception $e) {
                        log::add('teleinfo', 'error', '[TELEINFO]-----' . $e) ;
                    }
                }
                foreach ($idcoutdest as $destination){
                    try{
                        $sql = "DELETE FROM historyArch WHERE (cmd_id=:cmdId) AND (value=' ' OR value = 0)";
                        $values = array(
                            'cmdId' => $destination,
                        );
                        $sql = "DELETE FROM historyArch WHERE (cmd_id=:cmdId) AND (value=' ' OR value = 0)";
                        DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
                    } catch (\Exception $e) {
                        log::add('teleinfo', 'error', '[TELEINFO]-----' . $e) ;
                    }
                }
            }    
        }
    

    
    
        event::add('jeedom::alert', array(
            'level' => 'success',
            'page' => 'teleinfo',
            'message' => __('Les index ont bien été constitués.', __FILE__),
        ));

    } 

    public static function sauveCmd($id){
        $return['erreur'] = 'nOk';
        log::add('teleinfo', 'info', "[TELEINFO]----- début sauvegarde");
        $eqLogic = eqLogic::byId($id);
        log::add('teleinfo', 'info', sprintf(__("[TELEINFO]----- sauvegarde du compteur %s avec l'ID : %s", __FILE__), $eqLogic->getName(), $id)) ;
        $indexSauve = array('BASE','EAST','EASF01','EASF03','EASF05','HCHC','BBRHCJB','BBRHCJW','BBRHCJR','EJPHN','EASF02','EASF04','EASF06','HCHP','BBRHPJB','BBRHPJW','BBRHPJR','EJPHPM','EAIT');
        $dir = __DIR__ . '/../../sauvegarde/';
        if (!is_dir($dir)){
            mkdir($dir);
        }
        foreach ($indexSauve as $sauve){
            try{
                $cmd = $eqLogic->getCmd('info', $sauve);
                if (is_object($cmd)){
                    $cmdId = $cmd->getId();
                    log::add('teleinfo', 'info', sprintf(__("[TELEINFO]----- sauvegarde de %s avec l'ID : %s", __FILE__), $sauve, $cmdId)) ;
                    $sql = "SELECT * FROM historyArch WHERE (cmd_id=:cmdId)";
                    $values = array(
                        'cmdId' => $cmdId,
                    );
                    $sql = "SELECT * FROM historyArch WHERE (cmd_id=:cmdId)";
                    $querys = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
                    $delimiter = ","; 
                    //$filename = __DIR__ . "/../../sauvegarde/id_" . strval($cmdId) . "_data_" . date('Y-m-d') . ".csv"; 
                        
                    // Create a file pointer 
                    $f = fopen($dir . 'sauvegarde_equipement-'. str_replace(" ", "_", strval($eqLogic->getName())) . "_index-" . $sauve . '_le-' . date('Y-m-d') . '.csv', 'w'); 
                        
                    // Set column headers 
                    $fields = array('cmd_id', 'datetime', 'value'); 
                    fputcsv($f, $fields, $delimiter); 
                        
                    // Output each row of the data, format line as csv and write to file pointer 
                    foreach ($querys as $query){
                        $lineData = $query; //array($query[0], $query[1], $query[2]); 
                        fputcsv($f, $lineData, $delimiter); 
                    }
                    // Move back to beginning of file 
                    fseek($f, 0); 
                        
                    // Set headers to download file rather than displayed 
                    header('Content-Type: text/csv'); 
                    header('Content-Disposition: attachment; filename="' . $f . '";'); 
                        
                    //output all remaining data on a file pointer 
                    fpassthru($f); 
                } 
                    
            } catch (\Exception $e) {
                log::add('teleinfo', 'error', __('[TELEINFO]----- problème lors de la sauvegarde', __FILE__) . $e) ;
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
    if ($newIndex != 1 || $usePluginTemplate != 1) {
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
        // Placeholders standards Jeedom
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
    
    // === TEMPO: Vérifier les COULEURS en PREMIER (la couleur prime sur HP/HC) ===
    // Tempo - Bleu (prix bas)
    if (strpos($tarif, 'BLEU') !== false || strpos($tarif, 'JB') !== false) {
        return 'tarif-bleu';
    }
    // Tempo - Blanc (prix moyen)
    if (strpos($tarif, 'BLANC') !== false || strpos($tarif, 'JW') !== false) {
        return 'tarif-blanc';
    }
    // Tempo - Rouge (prix élevé)
    if (strpos($tarif, 'ROUGE') !== false || strpos($tarif, 'JR') !== false) {
        return 'tarif-rouge';
    }
    
    // === TYPES CLASSIQUES HP/HC ===
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