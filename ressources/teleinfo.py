#!/usr/bin/python
# -*- coding: utf-8 -*-
# vim: tabstop=8 expandtab shiftwidth=4 softtabstop=4

""" Read one teleinfo frame and output the frame
"""

import os
import time
import traceback
import logging
import sys
import argparse
import thread
from optparse import OptionParser
import subprocess
import urllib2
import threading
import signal
import globals
import json
try:
	from jeedom.jeedom import *
except ImportError as ex:
	print("Error: importing module from jeedom folder")
	print(ex)
	sys.exit(1)
import serial
from datetime import datetime
# ----------------------------------------------------------------------------
# Teleinfo core
# ----------------------------------------------------------------------------
class Teleinfo:
    import globals
    """ Fetch teleinformation datas and call user callback
    each time all data are collected
    """

    def __init__(self):
        logging.debug("TELEINFO------INIT CONNECTION")

    def open(self):
        """ open teleinfo modem device
        """
        try:
            logging.info("TELEINFO------OPEN CONNECTION")
            globals.TELEINFO_SERIAL = serial.Serial(globals.port, globals.vitesse, bytesize=7, parity='E', stopbits=1)
            logging.info("TELEINFO------CONNECTION OPENED")
        except:
            logging.error("Error opening Teleinfo modem '%s' : %s" % (globals.port, traceback.format_exc()))

    def close(self):
        """ close telinfo modem
        """
        logging.info("TELEINFO------CLOSE CONNECTION")
        if globals.TELEINFO_SERIAL != None  and globals.TELEINFO_SERIAL.isOpen():
            globals.TELEINFO_SERIAL.close()
            logging.info("TELEINFO------CONNECTION CLOSED")

    def terminate(self):
        print "Terminating..."
        self.close()
        os.remove("/tmp/teleinfo_" + options.type + ".pid")
        sys.exit()

    def read(self):
        """ Fetch one full frame for serial port
        If some part of the frame is corrupted,
        it waits until th enext one, so if you have corruption issue,
        this method can take time but it enures that the frame returned is valid
        @return frame : list of dict {name, value, checksum}
        """
        if globals.mode == "standard": # Zone linky standard
            resp = globals.TELEINFO_SERIAL.readline()
            is_ok = False
            premtrame = True
            content = {}
            while not is_ok:
                try:
                    while 'ADSC' not in resp:
                        if premtrame:
                            premtrame = False
                        else:
                            resp = globals.TELEINFO_SERIAL.readline()
                        if len(resp.replace('\r', '').replace('\n', '').split('\x09')) == 4:
                            name, horodate, value, checksum = resp.replace('\r', '').replace('\n', '').split('\x09')
                            #checksum = ' '
                            #content[name] = value;
                            if self._is_valid(resp, checksum):
                                logging.debug('TELEINFO------ ** DECODAGE Checksum de la ligne ci dessous OK')
                                content[name] = value;
                            else:
                                logging.error("TELEINFO------ ** DONNEES HS ! ** sur trame : " + resp + ' checksum : ' + checksum)
                            logging.debug('TELEINFO------name : ' + name + ' value : ' + value + ' Horodate : ' + horodate + ' checksum : ' + checksum)
                        else:
                            name, value, checksum = resp.replace('\r', '').replace('\n', '').split('\x09')
                            #logging.debug('Nombre de champs : ' + champs)
                            #content[name] = value;
                            if self._is_valid(resp, checksum):
                                logging.debug('TELEINFO------ ** DECODAGE Checksum de la ligne ci dessous OK')
                                content[name] = value;
                                if name == 'STGE':
                                    logging.debug('TELEINFO------name : STGE value : ' + value + ' checksum : ' + checksum)
                                    stgebin = bin(int(value, 16))
                                    stgebin = stgebin [2::]
                                    bits = [stgebin]
                                    longueur = len(stgebin)
                                    for i in range(32):
	                                    if i > longueur - 1:
		                                    bits += '0'
	                                    else:
		                                    bits += [stgebin[longueur -1 -i:longueur -i]]
                                    #print ('bits 2 ', bits)
                                    name = 'contact_sec'
                                    message = switch_mot1(int(bits[1]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'organe_de_coupure'
                                    message = switch_mot2(int(bits[4]+bits[3]+bits[2],2))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'etat_du_cache_bornes'
                                    message = switch_mot3(int(bits[5]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'non_utilise_toujours_a_0'
                                    message = switch_mot4(int(bits[6]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'surtension'
                                    message = switch_mot5(int(bits[7]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'depassement_de_P_reference'
                                    message = switch_mot6(int(bits[8]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'producteur_consommateur'
                                    message = switch_mot7(int(bits[9]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'sens_energie_active'
                                    message = switch_mot8(int(bits[10]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'tarif_en_cours_fourniture'
                                    message = switch_mot9(int(bits[14]+bits[13]+bits[12]+bits[11],2))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'tarif_en_cours_distrib'
                                    message = switch_mot10(int(bits[16]+bits[15],2))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'mode_degrade_horloge'
                                    message = switch_mot11(int(bits[17]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'etat_sortie_teleinfo'
                                    message = switch_mot12(int(bits[18]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'non_utilise'
                                    message = switch_mot13(int(bits[19]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'etat_sortie_comm_euridis'
                                    message = switch_mot14(int(bits[21]+bits[20],2))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'statut_CPL'
                                    message = switch_mot15(int(bits[23]+bits[22],2))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'synchro_CPL'
                                    message = switch_mot16(int(bits[24]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'couleur_jour_Tempo'
                                    message = switch_mot17(int(bits[26]+bits[25],2))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'couleur_demain_Tempo'
                                    message = switch_mot17(int(bits[28]+bits[27],2))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'preavis_pointe_mobile'
                                    message = switch_mot19(int(bits[30]+bits[29],2))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                    name = 'pointe_mobile'
                                    message = switch_mot19(int(bits[32]+bits[31],2))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction STGE : ' + name + ' value : ' + message)
                                # traduction RELAIS
                                if name == 'RELAIS':
                                    logging.debug('TELEINFO------name : RELAIS : ' + value)
                                    relais = bin(int(value))
                                    relais = relais [2::]
                                    bitsrelais = [relais]
                                    longueur = len(relais)
                                    #print ('bits 1 ', bits)
                                    for i in range(8):
	                                    if i > longueur - 1:
		                                    bitsrelais += '0'
 	                                    else:
		                                    bitsrelais += [relais[longueur -1 -i:longueur -i]]
                                    name = 'Relais_1'
                                    message = switch_mot20(int(bitsrelais[0]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction RELAIS : ' + name + ' value : ' + message)
                                    name = 'Relais_2'
                                    message = switch_mot20(int(bitsrelais[1]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction RELAIS : ' + name + ' value : ' + message)
                                    name = 'Relais_3'
                                    message = switch_mot20(int(bitsrelais[2]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction RELAIS : ' + name + ' value : ' + message)
                                    name = 'Relais_4'
                                    message = switch_mot20(int(bitsrelais[3]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction RELAIS : ' + name + ' value : ' + message)
                                    name = 'Relais_5'
                                    message = switch_mot20(int(bitsrelais[4]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction RELAIS : ' + name + ' value : ' + message)
                                    name = 'Relais_6'
                                    message = switch_mot20(int(bitsrelais[5]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction RELAIS : ' + name + ' value : ' + message)
                                    name = 'Relais_7'
                                    message = switch_mot20(int(bitsrelais[6]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction RELAIS : ' + name + ' value : ' + message)
                                    name = 'Relais_8'
                                    message = switch_mot20(int(bitsrelais[7]))
                                    content[name] = message;
                                    logging.debug('TELEINFO---traduction RELAIS : ' + name + ' value : ' + message)
                            else:
                                logging.error('TELEINFO------ ** DONNEES HS ! ** sur trame : ' + resp + ' checksum : ' + checksum)
                            logging.debug('TELEINFO------name : ' + name + ' value : ' + value + ' checksum : ' + checksum)
                    is_ok = True
                except ValueError:
                    checksum = ' '
        else: # Zone historique
            #Get the begin of the frame, markde by \x02
            resp = globals.TELEINFO_SERIAL.readline()
            is_ok = False
            content = {}
            while not is_ok:
                try:
                    while '\x02' not in resp:
                        resp = globals.TELEINFO_SERIAL.readline()
                    #\x02 is in the last line of a frame, so go until the next one
                    #print "* Begin frame"
                    resp = globals.TELEINFO_SERIAL.readline()
                    #A new frame starts
                    #\x03 is the end of the frame
                    while '\x03' not in resp:
                        #Don't use strip() here because the checksum can be ' '
                        if len(resp.replace('\r', '').replace('\n', '').split()) == 2:
                            #The checksum char is ' '
                            name, value = resp.replace('\r', '').replace('\n', '').split()
                            checksum = ' '
                            logging.debug('TELEINFO------name : ' + name + ' value : ' + value)
                        else:
                            name, value, checksum = resp.replace('\r', '').replace('\n', '').split()
                            logging.debug('TELEINFO------name : ' + name + ' value : ' + value + ' checksum : ' + checksum)
                        if self._is_valid(resp, checksum):
                            content[name] = value;
                        else:
                            logging.error("TELEINFO------ ** FRAME CORRUPTED ! **")
                            logging.error('** FRAME : ' + resp + '**')
                            #This frame is corrupted, we need to wait until the next one
                            while '\x02' not in resp:
                                resp = globals.TELEINFO_SERIAL.readline()
                            logging.error("* New frame after corrupted")
                        resp = globals.TELEINFO_SERIAL.readline()
                    #\x03 has been detected, that's the last line of the frame
                    if len(resp.replace('\r', '').replace('\n', '').split()) == 2:
                        name, value = resp.replace('\r', '').replace('\n', '').replace('\x02', '').replace('\x03', '').split()
                        checksum = ' '
                        logging.debug('TELEINFO------name : ' + name + ' value : ' + value)
                    else:
                        name, value, checksum = resp.replace('\r', '').replace('\n', '').replace('\x02', '').replace('\x03', '').split()
                        logging.debug('TELEINFO------name : ' + name + ' value : ' + value + ' checksum : ' + checksum)
                    if self._is_valid(resp, checksum):
                        is_ok = True
                    else:
                        logging.error("** Last frame invalid")
                        resp = globals.TELEINFO_SERIAL.readline()
                except ValueError:
                    #Badly formatted frame
                    #This frame is corrupted, we need to wait until the next one
                    while '\x02' not in resp:
                        resp = globals.TELEINFO_SERIAL.readline()
        return content

    def _is_valid(self, frame, checksum):
        """ Check if a frame is valid
        @param frame : the full frame
        @param checksum : the frame checksum
        """
        if globals.mode == "standard":
            #Gestion des champs horodates
            if len(frame.split('\x09')) == 4:
                datas = '\x09'.join(frame.split('\x09')[0:3])
            else:
                datas = '\x09'.join(frame.split('\x09')[0:2])
            my_sum = 0
            for cks in datas:
                my_sum = my_sum + ord(cks)
            computed_checksum = ((my_sum + 0x09) & int("111111", 2)) + 0x20
            if chr(computed_checksum) != checksum[0:1]:
                logging.error('checksum non concordant. Checksum reçu : ' + checksum[0:1] + ' Checksum calcul : ' + chr(computed_checksum))
            else:
                logging.debug('TELEINFO------ ** checksum concordant. Checksum reçu : ' + checksum[0:1] + ' Checksum calcul : ' + chr(computed_checksum))
        else:
            #print "Check checksum : f = %s, chk = %s" % (frame, checksum)
            datas = ' '.join(frame.split()[0:2])
            my_sum = 0
            for cks in datas:
                my_sum = my_sum + ord(cks)
            computed_checksum = (my_sum & int("111111", 2)) + 0x20
            #print "computed_checksum = %s" % chr(computed_checksum)
        return chr(computed_checksum) == checksum[0:1]

    def run(self):
        """ Main function
        """
        data = {}
        data_temp = {}
        raz_calcul = 0
        separateur = " "
        send_data = ""

        # Read a frame
        raz_time = datetime.now()
        while(1):
            raz_calcul = datetime.now() - raz_time
            if raz_calcul.seconds > 60:
                logging.info("TELEINFO------HEARTBEAT")
                raz_time = datetime.now()
                for cle, valeur in data.items():
                    data.pop(cle)
                    data_temp.pop(cle)
            send_data = ""
            frameCsv = self.read()
            for cle, valeur in frameCsv.items():
                if cle == 'PTEC':
                    valeur = valeur.replace(".", "")
                    valeur = valeur.replace(")", "")
                    data[cle] = valeur
                elif cle == 'OPTARIF':
                    valeur = valeur.replace(".", "")
                    valeur = valeur.replace(")", "")
                    data[cle] = valeur
                else:
                    #valeur = valeur.replace(" ", "%20")
                    data[cle] = valeur
            _SendData = {}
            PENDING_CHANGES = False
            for cle, valeur in data.items():
                if cle in data_temp:
                    if data[cle] != data_temp[cle]:
                        _SendData[cle] = valeur
                        data_temp[cle] = valeur
                        PENDING_CHANGES = True
                else:
                    _SendData[cle] = valeur
                    data_temp[cle] = valeur
                    PENDING_CHANGES = True
            try:
                if PENDING_CHANGES :
                    if globals.mode == "standard":
                        _SendData["device"] = data["ADSC"]
                        globals.JEEDOM_COM.add_changes('device::'+data["ADSC"],_SendData)
                    else:
                        _SendData["device"] = data["ADCO"]
                        globals.JEEDOM_COM.add_changes('device::'+data["ADCO"],_SendData)
            except Exception:
                errorCom = "Connection error"
                logging.error(errorCom)
            logging.debug("TELEINFO------START SLEEPING " + str(globals.cycle_sommeil) + " seconds")
            time.sleep(globals.cycle_sommeil)
            logging.debug("TELEINFO------WAITING : " + str(globals.TELEINFO_SERIAL.inWaiting()) + " octets dans la file apres sleep ")
            if globals.TELEINFO_SERIAL.inWaiting() > 1500:
                globals.TELEINFO_SERIAL.flushInput()
                logging.info("TELEINFO------BUFFER OVERFLOW => FLUSH")
                logging.debug(str(globals.TELEINFO_SERIAL.inWaiting()) + "octets dans la file apres flush ")
        self.terminate()
    def exit_handler(self, *args):
        logging.info("[exit_handler]")

def read_socket(cycle):
	while True :
		try:
			global JEEDOM_SOCKET_MESSAGE
			if not JEEDOM_SOCKET_MESSAGE.empty():
				logging.debug("SOCKET-READ------Message received in socket JEEDOM_SOCKET_MESSAGE")
				message = json.loads(JEEDOM_SOCKET_MESSAGE.get())
				if message['apikey'] != globals.apikey:
					logging.error("SOCKET-READ------Invalid apikey from socket : " + str(message))
					return
				logging.debug('SOCKET-READ------Received command from jeedom : '+str(message['cmd']))
				if message['cmd'] == 'action':
					logging.debug('SOCKET-READ------Attempt an action on a device')
					thread.start_new_thread( action_handler, (message,))
					logging.debug('SOCKET-READ------Action Thread Launched')
				elif message['cmd'] == 'logdebug':
					logging.info('SOCKET-READ------Passage du demon en mode debug force')
					log = logging.getLogger()
					for hdlr in log.handlers[:]:
						log.removeHandler(hdlr)
					jeedom_utils.set_log_level('debug')
					logging.debug('SOCKET-READ------<----- La preuve ;)')
				elif message['cmd'] == 'lognormal':
					logging.info('SOCKET-READ------Passage du demon en mode de log normal')
					log = logging.getLogger()
					for hdlr in log.handlers[:]:
						log.removeHandler(hdlr)
					jeedom_utils.set_log_level('error')
		except Exception as e:
			logging.error("SOCKET-READ------Exception on socket : %s" % str(e))
			logging.debug(traceback.format_exc())
		time.sleep(cycle)

def log_starting(cycle):
	time.sleep(90)
	logging.info('GLOBAL------Passage des logs en normal')
	log = logging.getLogger()
	for hdlr in log.handlers[:]:
		log.removeHandler(hdlr)
	jeedom_utils.set_log_level('error')

def listen():
	globals.PENDING_ACTION=False
	jeedom_socket.open()
	logging.info("GLOBAL------Start listening...")
	globals.TELEINFO = Teleinfo()
	logging.info("GLOBAL------Preparing Teleinfo...")
	thread.start_new_thread( read_socket, (globals.cycle,))
	logging.debug('GLOBAL------Read Socket Thread Launched')
	while 1:
		try:
			try:
				logging.info("TELEINFO------RUN")
				globals.TELEINFO.open()
			except error as err:
				logging.error(err.value)
				globals.TELEINFO.terminate()
				return
			thread.start_new_thread( log_starting,(globals.cycle,))
			globals.TELEINFO.run()
		except Exception as e:
			print("Error:")
			print(e)
			shutdown()

def handler(signum=None, frame=None):
	logging.debug("Signal %i caught, exiting..." % int(signum))
	shutdown()

def shutdown():
	log = logging.getLogger()
	for hdlr in log.handlers[:]:
		log.removeHandler(hdlr)
	jeedom_utils.set_log_level('debug')
	logging.info("GLOBAL------Shutdown")
	logging.info("Removing PID file " + str(globals.pidfile))
	try:
		os.remove(globals.pidfile)
	except:
		pass
	try:
		jeedom_socket.close()
	except:
		pass
	logging.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)

#------------------------------------------------------------------------------
# Ajout pour traduction code STGE
#------------------------------------------------------------------------------

#DEFINITION DE LA SIGNIFICATION DES BITS
def switch_mot1(argument):
	switcher = {
		0: "Ferme",
		1: "Ouvert",
	}
	return switcher.get(argument, "Invalide")

def switch_mot2(argument):
	switcher = {
		0: "Ferme",
		1: "Ouvert sur surpuissance",
		2: "ouvert sur surtension",
		3: "Ouvert sur delestage",
		4: "Ouvert sur ordre CPL ou Euridis",
		5: "Ouvert sur surchauffe avec I > Imax",
		6: "Ouvert sur surchauffe avec I < Imax",
	}
	return switcher.get(argument, "Invalide")

def switch_mot3(argument):
	switcher = {
		0: "Ferme",
		1: "Ouvert",
	}
	return switcher.get(argument, "Invalide")

def switch_mot4(argument):
	switcher = {
		0: "Toujours a 0",
		1: "Anormal",
	}
	return switcher.get(argument, "Invalide")

def switch_mot5(argument):
	switcher = {
		0: "Pas de surtension",
		1: "Surtension",
	}
	return switcher.get(argument, "Invalide")

def switch_mot6(argument):
	switcher = {
		0: "Pas de depassement",
		1: "Depassement en cours",
	}
	return switcher.get(argument, "Invalide")

def switch_mot7(argument):
	switcher = {
		0: "Consommateur",
		1: "Producteur",
	}
	return switcher.get(argument, "Invalide")

def switch_mot8(argument):
	switcher = {
		0: "Positive",
		1: "Negative",
	}
	return switcher.get(argument, "Invalide")

def switch_mot9(argument):
	switcher = {
		0: "Ventile sur index 1",
		1: "Ventile sur index 2",
		2: "Ventile sur index 3",
		3: "Ventile sur index 4",
		4: "Ventile sur index 5",
		5: "Ventile sur index 6",
		6: "Ventile sur index 7",
		7: "Ventile sur index 8",
		8: "Ventile sur index 9",
		9: "Ventile sur index 10",
	}
	return switcher.get(argument, "Invalide")

def switch_mot10(argument):
	switcher = {
		0: "Ventile sur index 1",
		1: "Ventile sur index 2",
		2: "Ventile sur index 3",
		3: "Ventile sur index 4",
	}
	return switcher.get(argument, "Invalide")

def switch_mot11(argument):
	switcher = {
		0: "Correct",
		1: "Degrade",
	}
	return switcher.get(argument, "Invalide")

def switch_mot12(argument):
	switcher = {
		0: "Historique",
		1: "Standard",
	}
	return switcher.get(argument, "Invalide")

def switch_mot13(argument):
	switcher = {
		0: "Non utilise",
		1: "Non utilise",
	}
	return switcher.get(argument, "Invalide")

def switch_mot14(argument):
	switcher = {
		0: "Desactivee",
		1: "Activee sans secu",
		2: "Invalide",
		3: "Activee avec secu",
	}
	return switcher.get(argument, "Invalide")

def switch_mot15(argument):
	switcher = {
		0: "New/unlock",
		1: "New/lock",
		2: "Registered",
		3: "Invalide",
	}
	return switcher.get(argument, "Invalide")

def switch_mot16(argument):
	switcher = {
		0: "Non synchro",
		1: "synchro",
	}
	return switcher.get(argument, "Invalide")

def switch_mot17(argument):
	switcher = {
		0: "Contrat non Tempo",
		1: "Bleu",
		2: "Blanc",
		3: "Rouge",
	}
	return switcher.get(argument, "Invalide")

def switch_mot19(argument):
	switcher = {
		0: "Pas de preavis",
		1: "Preavis PM1",
		2: "Preavis PM2",
		3: "Preavis PM3",
	}
	return switcher.get(argument, "Invalide")

#------------------------------------------------------------------------------
# Ajout pour traduction code RELAIS
#------------------------------------------------------------------------------

#DEFINITION DE LA SIGNIFICATION DES BITS
def switch_mot20(argument):
	switcher = {
		0: "Ouvert",
		1: "Ferme",
	}
	return switcher.get(argument, "Invalide")


#------------------------------------------------------------------------------
# MAIN
#------------------------------------------------------------------------------

parser = argparse.ArgumentParser(description='Teleinfo Daemon for Jeedom plugin')
parser.add_argument("--apikey", help="Value to write", type=str)
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Value to write", type=str)
parser.add_argument("--socketport", help="Socket Port", type=str)
parser.add_argument("--sockethost", help="Socket Host", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=str)
parser.add_argument("--port", help="Port du modem", type=str)
parser.add_argument("--vitesse", help="Vitesse du modem", type=str)
parser.add_argument("--type", help="Compteur type", type=str)
parser.add_argument("--mode", help="Model mode", type=str)
parser.add_argument("--cyclesommeil", help="Wait time between 2 readline", type=str)
parser.add_argument("--pidfile", help="pidfile", type=str)
args = parser.parse_args()

if args.apikey:
	globals.apikey = args.apikey
if args.loglevel:
	globals.log_level = args.loglevel
if args.callback:
	globals.callback = args.callback
if args.socketport:
	globals.socketport = args.socketport
if args.sockethost:
	globals.sockethost = args.sockethost
if args.cycle:
	globals.cycle = args.cycle
if args.port:
	globals.port = args.port
if args.vitesse:
	globals.vitesse = args.vitesse
if args.type:
	globals.type = args.type
if args.mode:
	globals.mode = args.mode
if args.cyclesommeil:
	globals.cycle_sommeil = args.cyclesommeil
if args.pidfile:
	globals.pidfile = args.pidfile

globals.socketport = int(globals.socketport)
globals.cycle = float(globals.cycle)
globals.cycle_sommeil = float(globals.cycle_sommeil)

jeedom_utils.set_log_level(globals.log_level)
logging.info('GLOBAL------Start teleinfod')
logging.info('GLOBAL------Cycle Sommeil : '+str(globals.cycle_sommeil))
logging.info('GLOBAL------Socket port : '+str(globals.socketport))
logging.info('GLOBAL------Socket host : '+str(globals.sockethost))
logging.info('GLOBAL------Log level : '+str(globals.log_level))
logging.info('GLOBAL------Callback : '+str(globals.callback))
logging.info('GLOBAL------Vitesse : '+str(globals.vitesse))
logging.info('GLOBAL------Apikey : '+str(globals.apikey))
logging.info('GLOBAL------Cycle : '+str(globals.cycle))
logging.info('GLOBAL------Port : '+str(globals.port))
logging.info('GLOBAL------Type : '+str(globals.type))
logging.info('GLOBAL------Mode : '+str(globals.mode))
signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)
globals.pidfile = globals.pidfile+"_"+globals.type+".pid"
jeedom_utils.write_pid(str(globals.pidfile))
globals.JEEDOM_COM = jeedom_com(apikey = globals.apikey,url = globals.callback,cycle=globals.cycle)
if not globals.JEEDOM_COM.test():
	logging.error('GLOBAL------Network communication issues. Please fix your Jeedom network configuration.')
	shutdown()
jeedom_socket = jeedom_socket(port=globals.socketport,address=globals.sockethost)
listen()
sys.exit()