#!/usr/bin/python3
# -*- coding: utf-8 -*-
# vim: tabstop=8 expandtab shiftwidth=4 softtabstop=4

""" Read one teleinfo MQTT frame and output the frame
"""

import _thread
import argparse
import json
import sys
import traceback
import globals
import paho.mqtt.client as mqtt_client
from threading import Thread, Lock
import re

try:
    from jeedom.jeedom import *
except ImportError as ex:
    print("Error: importing module from jeedom folder")
    print(ex)
    #sys.exit(1)
from datetime import datetime

class error(Exception):
    def __init__(self, value):
        self.value = value
    def __str__(self):
        return repr(self.value)


# ----------------------------------------------------------------------------
# Teleinfo core
#
# trame wifiTIC: voir la documentation de wifiTIC
# trame tasmota: {"ADCO":"abcdefgh","OPTARIF":"BASE","ISOUSC":"30","BASE":"040335283","PTEC":"TH..","IINST1":"003","IINST2":"003","IINST3":"009","IMAX1":"032","IMAX2":"025","IMAX3":"021","PMAX":"14550","PAPP":"03551","MOTDETAT":"000000","PPOT":"00"}
# trame teleinfo2mqtt: {"ADCO": {"raw": "12345678901","value": 12345678901},"OPTARIF": {"raw": "HC..","value": "HC"},"ISOUSC": {"raw": "45","value": 45}, ...
# trame de base: {"TIC":{"ADCO":"testMQTT HCHP","HCHC":4711286,"HCHP":3590469}}
# ----------------------------------------------------------------------------

def handler(signum=None, frame=None):
    logging.debug(f"MQTT------Signal {signum} caught, exiting...")
    shutdown()

def mqtt_on_log( client, userdata, level, buf ):
    logging.info( "MQTT------log: " + buf)

def mqtt_on_connect( client, userdata, flags, rc ):
    logging.info(f"MQTT------Connexion: code retour = {rc}")
    logging.info(f"MQTT------Connexion: Statut = {'OK' if rc==0 else 'échec'}")
    client.subscribe(globals.mqtt_topic)


def mqtt_on_disconnect(client, userdata, rc):
    logging.info("MQTT------disconnecting reason  "  +str(rc))
    shutdown()

def find_key_subdicts(json_obj, target_keys, result=None):
    """
    Parcourt récursivement un JSON pour trouver tous les sous-dictionnaires contenant l'une des clés dans target_keys.
    Retourne une liste de ces sous-dictionnaires.
    """
    if result is None:
        result = []

    if isinstance(json_obj, dict):
        if any(key in json_obj for key in target_keys):
            logging.debug(f"Trouvé une clé cible dans : {json_obj}")
            result.append(json_obj)
        for key, value in json_obj.items():
            find_key_subdicts(value, target_keys, result)
    elif isinstance(json_obj, list):
        for item in json_obj:
            find_key_subdicts(item, target_keys, result)
    
    return result

def is_teleinfo2mqtt_format(subdict):
    """
    Vérifie si le sous-dictionnaire suit le format teleinfo2mqtt
    (c'est-à-dire que chaque valeur est un dictionnaire avec une clé 'value').
    Retourne True si au moins une valeur suit ce format, False sinon.
    """
    return any(
        isinstance(value, dict) and 'value' in value
        for value in subdict.values()
    )

def clean_ptec_optarif(value):
    """
    Nettoie une valeur en supprimant les '.' et ')' si elle est une chaîne.
    Retourne la valeur nettoyée ou la valeur originale si ce n'est pas une chaîne.
    """
    if isinstance(value, str):
        cleaned_value = value.replace('.', '').replace(')', '')
        return cleaned_value
    return value

def extract_teleinfo2mqtt_values(subdict):
    """
    Extrait la propriété 'value' pour chaque clé dans le dictionnaire
    et retourne un nouveau dictionnaire avec les clés associées directement à leurs valeurs 'value'.
    Nettoie les valeurs de 'PTEC' et 'OPTARIF' en supprimant '.' et ')'.
    """
    result = {}
    for key, value in subdict.items():
        if isinstance(value, dict) and 'value' in value:
            val = value['raw']
            if key in ['PTEC', 'OPTARIF']:
                result[key] = clean_ptec_optarif(val)
            else:
                result[key] = val
        else:
            if key in ['PTEC', 'OPTARIF']:
                result[key] = clean_ptec_optarif(value)
            else:
                result[key] = value
    return result

def format_subdict(subdict):
    """
    Formate un sous-dictionnaire dans la structure {device_name: {...}}.
    Utilise la valeur de 'ADCO' ou 'ADSC' comme device_name et ajoute une clé 'device' avec cette valeur.
    Nettoie les valeurs de 'PTEC' et 'OPTARIF' si elles n'ont pas été nettoyées avant.
    """
    cleaned_subdict = subdict.copy()
    for key in ['PTEC', 'OPTARIF']:
        if key in cleaned_subdict:
            cleaned_subdict[key] = clean_ptec_optarif(cleaned_subdict[key])
    
    device_name = cleaned_subdict.get('ADCO', cleaned_subdict.get('ADSC', 'unknown_device'))
    if device_name == 'unknown_device':
        logging.warning("Ni 'ADCO' ni 'ADSC' trouvé dans le sous-dictionnaire, utilisation de 'unknown_device' comme device_name")
    
    cleaned_subdict['device'] = device_name
    
    return cleaned_subdict

def mqtt_on_message(client, userdata, message):
    # lecture des trames MQTT
    # logging.info("GLOBAL------Debut reception MQTT...")
    data = {}
    _SendData = {}
    y = str(message.payload.decode("utf-8"))
    logging.debug(f"MQTT------Topic : {message.topic}")
    logging.debug("MQTT------Data  : " + y )
    trouveTIC = False
    if 'wifiTIC' in message.topic:
        try:
            if trouveTIC == False:
                device = 'ADCO'
            trouveTIC = True
            x = json.loads(str(y))
            data['ADCO'] = str(x['counter']['id'])
            if x['counter']['contract']== 1:
                data['NGTF'] = 'BASE'
            elif x['counter']['contract']==2:
                data['NGTF'] = 'HP/HC'
            elif x['counter']['contract']==3:
                data['NGTF'] = 'EJP'
            elif x['counter']['contract']==4:
                data['NGTF'] = 'TEMPO'
            elif x['counter']['contract']==5:
                data['NGTF'] = 'PRODUCTION'
                data['EAIT'] = (x['consumption']['counters'][0]['value'])
            elif x['counter']['contract']==6:
                data['NGTF'] = 'ZEN'
            elif x['counter']['contract']==7:
                data['NGTF'] = 'ZEN+'
            elif x['counter']['contract']==8:
                data['NGTF'] = 'Super Creuses'
            elif x['counter']['contract']==9:
                data['NGTF'] = 'Week-End'
            elif x['counter']['contract']==10:
                data['NGTF'] = 'Beaux Jours'
            else:
                data['NGTF'] = 'Inconnu'
            data['EAST'] = 0
            for i in range(len(x['consumption']['counters'])):
                data['EASF0' + str(i+1)] = x['consumption']['counters'][i]['value']
                data['EAST'] += x['consumption']['counters'][i]['value']
            if len(x['consumption']['phases']) == 1:
                data['SINSTS'] = x['consumption']['phases'][0]['app']
                data['IRMS1'] = x['consumption']['phases'][0]['iinst']
            else:
                data['SINSTS1'] = x['consumption']['phases'][0]['app']
                data['IRMS1'] = x['consumption']['phases'][0]['iinst']
                data['SINSTS2'] = x['consumption']['phases'][1]['app']
                data['IRMS2'] = x['consumption']['phases'][1]['iinst']
                data['SINST3'] = x['consumption']['phases'][2]['app']
                data['IRMS3'] = x['consumption']['phases'][2]['iinst']
            logging.debug("MQTT------message wifiTIC. ADCO: " + data['ADCO'])
        except:
            logging.debug("MQTT------message non wifiTIC")

    try:
        if isinstance(y, str):
            x = json.loads(y)  # Parser la chaîne JSON
        else:
            x = y  # y est déjà un dictionnaire

        # Clés à rechercher
        target_keys = ['ADCO', 'ADSC']
        
        # Récupérer les sous-dictionnaires contenant 'ADCO' ou 'ADSC'
        subdicts = find_key_subdicts(x, target_keys)
        
        if subdicts:
            logging.debug("MQTT------message teleinfo2mqtt ou tasmota")
            for i, subdict in enumerate(subdicts, 1):
                logging.debug(f"Sous-dictionnaire {i}: {subdict}")
                
                if is_teleinfo2mqtt_format(subdict):
                    simplified_dict = extract_teleinfo2mqtt_values(subdict)
                else:
                    simplified_dict = subdict
                
                formatted_dict = format_subdict(simplified_dict)

                device_value = formatted_dict['device']

                try:
                    globals.JEEDOM_COM.add_changes('device::' + device_value, formatted_dict)
                except Exception:
                    error_com = f"Erreur lors de l'envoi à Jeedom : {str(e)}"
                    logging.error(f"MQTT------{error_com}")
        else:
            logging.debug("Aucun sous-dictionnaire avec 'ADCO' ou 'ADSC' trouvé")

    except:
        logging.debug("MQTT------message autre")

def read_socket(cycle):
    while True:
        try:
            global JEEDOM_SOCKET_MESSAGE
            if not JEEDOM_SOCKET_MESSAGE.empty():
                logging.debug("SOCKET-READ------Message received in socket JEEDOM_SOCKET_MESSAGE")
                message = json.loads(JEEDOM_SOCKET_MESSAGE.get())
                logging.debug("SOCKET-READ------Message received in socket JEEDOM_SOCKET_MESSAGE " + message['cmd'])
                if message['apikey'] != globals.apikey:
                    logging.error("SOCKET-READ------Invalid apikey from socket : " + str(message))
                    return
                logging.debug('SOCKET-READ------Received command from jeedom : ' + str(message['cmd']))
                if message['cmd'] == 'action':
                    logging.debug('SOCKET-READ------Attempt an action on a device')
                    _thread.start_new_thread(action_handler, (message,))
                    logging.debug('SOCKET-READ------Action Thread Launched')
                elif message['cmd'] == 'changelog':
                    log = logging.getLogger()
                    for hdlr in log.handlers[:]:
                        log.removeHandler(hdlr)
                    jeedom_utils.set_log_level('info')
                    logging.info('SOCKET-READ------Passage des log du demon en mode ' + message['level'])
                    for hdlr in log.handlers[:]:
                        log.removeHandler(hdlr)
                    jeedom_utils.set_log_level(message['level'])
        except Exception as e:
            logging.error(f"SOCKET-READ------Exception on socket : {e}")
            logging.debug("MQTT------" + traceback.format_exc())
        time.sleep(cycle)

def listen():
    jeedom_socket.open()
    try:
        _thread.start_new_thread(read_socket, (globals.cycle,))
        _thread.start_new_thread(listen_mqtt())
        logging.info('MQTT------Tout roule')
        while 1:
            time.sleep(0.5)
    except KeyboardInterrupt:
        shutdown()


def listen_mqtt():
    logging.info("MQTT------Start listening...")
    logging.info("MQTT------Preparing Teleinfo...")
    logging.info('MQTT------Read Socket Thread Launched')
    logging.info("MQTT------Start listening MQTT...")
    client = mqtt_client.Client( client_id="", clean_session=True)

    # Assignation des fonctions de rappel
    client.on_message = mqtt_on_message
    client.on_connect = mqtt_on_connect
    client.on_disconnect = mqtt_on_disconnect

    # Connexion broker
    if globals.mqtt_username != 'aucun_pour_etre_certain':
        client.username_pw_set( username=globals.mqtt_username , password=globals.mqtt_password )
    client.connect( host=globals.mqtt_broker, port=int(globals.mqtt_port), keepalive=int(globals.mqtt_keepalive))
    #client.subscribe("tasmota/teleinfo_full_linky/SENSOR")
    #client.subscribe("tasmota/teleinfo_full/SENSOR")
    # Envoi des messages
    client.loop_forever()  

def shutdown():
    logging.debug("MQTT------Shutdown")
    logging.debug("MQTT------Removing PID file " + str(globals.pidfile))
    try:
        os.remove(globals.pidfile)
    except:
        pass
    try:
        jeedom_socket.close()
    except:
        pass
    logging.debug("MQTT------Exit 0")
    sys.stdout.flush()
    os._exit(0)


# ------------------------------------------------------------------------------
# MAIN
# ------------------------------------------------------------------------------

parser = argparse.ArgumentParser(description='Teleinfo Daemon MQTT for Jeedom plugin')
parser.add_argument("--apikey", help="Value to write", type=str)
parser.add_argument("--loglevel", help="log level", type=str)
parser.add_argument("--callback", help="Value to write", type=str)
parser.add_argument("--pidfile", help="pidfile", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=str)
parser.add_argument("--socketport", help="Socket Port", type=str)
parser.add_argument("--sockethost", help="Socket Host", type=str)
parser.add_argument("--modem", help="presence ou non d un modem", type=str)
parser.add_argument("--mqtt", help="decodage mqtt demande", type=str)
parser.add_argument("--mqtt_broker", help="nom du broker mqtt", type=str)
parser.add_argument("--mqtt_port", help="port utilise par mqtt", type=str)
parser.add_argument("--mqtt_keepalive", help="keep alive utilise par mqtt", type=str)
parser.add_argument("--mqtt_topic", help="topic mqtt a ecouter", type=str)
parser.add_argument("--mqtt_username", help="utilisateur declare sur le broker mqtt", type=str)
parser.add_argument("--mqtt_password", help="mot de passe pour le broker mqtt", type=str)
args = parser.parse_args()
if args.pidfile:
    globals.pidfile = args.pidfile
if args.socketport:
    globals.socketport = args.socketport
if args.sockethost:
    globals.sockethost = args.sockethost
if args.loglevel:
    globals.log_level = args.loglevel
if args.callback:
    globals.callback = args.callback
if args.cycle:
    globals.cycle = args.cycle
if args.apikey:
    globals.apikey = args.apikey
if args.modem:
    globals.modem = args.modem
if args.mqtt:
    globals.mqtt = args.mqtt
if args.mqtt_broker:
    globals.mqtt_broker = args.mqtt_broker
if args.mqtt_port:
    globals.mqtt_port = int(args.mqtt_port)
if args.mqtt_keepalive:
    globals.mqtt_keepalive = int(args.mqtt_keepalive)
if args.mqtt_topic:
    globals.mqtt_topic = args.mqtt_topic
if args.mqtt_username:
    globals.mqtt_username = args.mqtt_username
if args.mqtt_password:
    globals.mqtt_password = args.mqtt_password


globals.socketport = int(globals.socketport)
globals.cycle = float(globals.cycle)
jeedom_utils.set_log_level(globals.log_level)
globals.pidfile = globals.pidfile + "_Mqtt.pid"
logging.info('MQTT------Start teleinfo')
signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)
logging.info('MQTT------Socket port : ' + str(globals.socketport))
logging.info('MQTT------Broker : ' + str(globals.mqtt_broker))
logging.info('MQTT------Broker port : ' + str(globals.mqtt_port))
logging.info('MQTT------User : ' + str(globals.mqtt_username))
logging.info('MQTT------pass : ' + str(globals.mqtt_password))
logging.info('MQTT------Topic : ' + str(globals.mqtt_topic))
logging.info('MQTT------Log level : ' + str(globals.log_level))

try:
    jeedom_utils.write_pid(str(globals.pidfile))
    globals.JEEDOM_COM = jeedom_com(apikey = globals.apikey,url = globals.callback,cycle=globals.cycle)
    if not globals.JEEDOM_COM.test():
        logging.error('MQTT------Probleme de connexion reseau. Verifier votre configuration.')
        shutdown()
    jeedom_socket = jeedom_socket(port=globals.socketport,address=globals.sockethost)
    listen()
except Exception as e:
    logging.error('MQTT------Erreur fatale : '+str(e))
    shutdown()





logging.info('MQTT------fin')

sys.exit()
