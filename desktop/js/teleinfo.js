
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

 var liste_donnees = [{etiquette:"ADCO",name:"Adresse du compteur",unite:""},
{etiquette:"OPTARIF",name:"Option tarifaire choisie",unite:""},
{etiquette:"DATE",name:"Date",unite:""},
{etiquette:"VTIC",name:"Version de la TIC",unite:""},
{etiquette:"ISOUSC",name:"Intensité souscrite",unite:"A"},
{etiquette:"BASE",name:"Index Base",unite:"Wh"},
{etiquette:"HCHC",name:"Index Heures Creuses",unite:"Wh"},
{etiquette:"HCHP",name:"Index Heures Pleines",unite:"Wh"},
{etiquette:"EJPHN",name:"Index EJP Heures Normales",unite:"Wh"},
{etiquette:"EJPHPM",name:"Index EJP Heures de Pointe Mobile",unite:"Wh"},
{etiquette:"BBRHCJB",name:"Index Tempo Heures Creuses Jours Bleus",unite:"Wh"},
{etiquette:"BBRHPJB",name:"Index Tempo Heures Pleines Jours Bleus",unite:"Wh"},
{etiquette:"BBRHCJW",name:"Index Tempo Heures Creuses Jours Blancs",unite:"Wh"},
{etiquette:"BBRHPJW",name:"Index Tempo Heures Pleines Jours Blancs",unite:"Wh"},
{etiquette:"BBRHCJR",name:"Index Tempo Heures Creuses Jours Rouges",unite:"Wh"},
{etiquette:"BBRHPJR",name:"Index Tempo Heures Pleines Jours Rouges",unite:"Wh"},
{etiquette:"PEJP",name:"Préavis Début EJP (30 min)",unite:"min"},
{etiquette:"PTEC",name:"Période Tarifaire en cours",unite:""},
{etiquette:"DEMAIN",name:"Couleur du lendemain",unite:""},
{etiquette:"IINST",name:"Intensité Instantanée",unite:"A"},
{etiquette:"IINST1",name:"Intensité Instantanée phases 1",unite:"A"},
{etiquette:"IINST2",name:"Intensité Instantanée phases 2",unite:"A"},
{etiquette:"IINST3",name:"Intensité Instantanée phases 3",unite:"A"},
{etiquette:"ADPS",name:"Avertissement de Dépassement De Puissance Souscrite",unite:"A"},
{etiquette:"ADIR1",name:"Avertissement de Dépassement d'intensité de réglage phase 1",unite:"A"},
{etiquette:"ADIR2",name:"Avertissement de Dépassement d'intensité de réglage phase 2",unite:"A"},
{etiquette:"ADIR3",name:"Avertissement de Dépassement d'intensité de réglage phase 3",unite:"A"},
{etiquette:"IMAX",name:"Intensité maximale appelée",unite:"A"},
{etiquette:"IMAX1",name:"Intensité maximale phase 1",unite:"A"},
{etiquette:"IMAX2",name:"Intensité maximale phase 2",unite:"A"},
{etiquette:"IMAX3",name:"Intensité maximale phase 3",unite:"A"},
{etiquette:"PAPP",name:"Puissance apparente",unite:"VA"},
{etiquette:"HHPHC",name:"Horaire Heures Pleines Heures Creuses",unite:""},
{etiquette:"MOTDETAT",name:"Mot d'état du compteur",unite:""},
{etiquette:"PMAX",name:"Puissance maximale triphasée atteinte",unite:"W"},
{etiquette:"PPOT",name:"Présence des potentiels",unite:""},
{etiquette:"ADSC",name:"Adresse Secondaire du Compteur",unite:""},
{etiquette:"NGTF",name:"Nom du calendrier tarifaire fournisseur",unite:""},
{etiquette:"LTARF",name:"Libellé tarif fournisseur en cours",unite:""},
{etiquette:"EAST",name:"Energie active soutirée totale",unite:"Wh"},
{etiquette:"EASF01",name:"Energie active soutirée Fournisseur, index 01",unite:"Wh"},
{etiquette:"EASF02",name:"Energie active soutirée Fournisseur, index 02",unite:"Wh"},
{etiquette:"EASF03",name:"Energie active soutirée Fournisseur, index 03",unite:"Wh"},
{etiquette:"EASF04",name:"Energie active soutirée Fournisseur, index 04",unite:"Wh"},
{etiquette:"EASF05",name:"Energie active soutirée Fournisseur, index 05",unite:"Wh"},
{etiquette:"EASF06",name:"Energie active soutirée Fournisseur, index 06",unite:"Wh"},
{etiquette:"EASF07",name:"Energie active soutirée Fournisseur, index 07",unite:"Wh"},
{etiquette:"EASF08",name:"Energie active soutirée Fournisseur, index 08",unite:"Wh"},
{etiquette:"EASF09",name:"Energie active soutirée Fournisseur, index 09",unite:"Wh"},
{etiquette:"EASF10",name:"Energie active soutirée Fournisseur, index 10",unite:"Wh"},
{etiquette:"EASD01",name:"Energie active soutirée Distributeur, index 01",unite:"Wh"},
{etiquette:"EASD02",name:"Energie active soutirée Distributeur, index 02",unite:"Wh"},
{etiquette:"EASD03",name:"Energie active soutirée Distributeur, index 03",unite:"Wh"},
{etiquette:"EASD04",name:"Energie active soutirée Distributeur, index 04",unite:"Wh"},
{etiquette:"EAIT",name:"Energie active injectée totale",unite:"Wh"},
{etiquette:"ERQ1",name:"Energie réactive Q1 totale",unite:"VArh"},
{etiquette:"ERQ2",name:"Energie réactive Q2 totale",unite:"VArh"},
{etiquette:"ERQ3",name:"Energie réactive Q3 totale",unite:"VArh"},
{etiquette:"ERQ4",name:"Energie réactive Q4 totale",unite:"VArh"},
{etiquette:"IRMS1",name:"Courant efficace, phase 1",unite:"A"},
{etiquette:"IRMS2",name:"Courant efficace, phase 2",unite:"A"},
{etiquette:"IRMS3",name:"Courant efficace, phase 3",unite:"A"},
{etiquette:"URMS1",name:"Tension efficace, phase 1",unite:"V"},
{etiquette:"URMS2",name:"Tension efficace, phase 2",unite:"V"},
{etiquette:"URMS3",name:"Tension efficace, phase 3",unite:"V"},
{etiquette:"PREF",name:"Puissance app. de référence",unite:"kVA"},
{etiquette:"PCOUP",name:"Puissance app. de coupure",unite:"kVA"},
{etiquette:"SINSTS",name:"Puissance app. Instantanée soutirée",unite:"VA"},
{etiquette:"SINSTS1",name:"Puissance app. Instantanée soutirée phase 1",unite:"VA"},
{etiquette:"SINSTS2",name:"Puissance app. Instantanée soutirée phase 2",unite:"VA"},
{etiquette:"SINSTS3",name:"Puissance app. Instantanée soutirée phase 3",unite:"VA"},
{etiquette:"SMAXSN",name:"Puissance app. max. soutirée n",unite:"VA"},
{etiquette:"SMAXSN1",name:"Puissance app. max. soutirée n phase 1",unite:"VA"},
{etiquette:"SMAXSN2",name:"Puissance app. max. soutirée n phase 2",unite:"VA"},
{etiquette:"SMAXSN3",name:"Puissance app. max. soutirée n phase 3",unite:"VA"},
{etiquette:"SMAXSN-1",name:"Puissance app max. soutirée n-1",unite:"VA"},
{etiquette:"SMAXSN1-1",name:"Puissance app max. soutirée n-1 phase 1",unite:"VA"},
{etiquette:"SMAXSN2-1",name:"Puissance app max. soutirée n-1 phase 2",unite:"VA"},
{etiquette:"SMAXSN3-1",name:"Puissance app max. soutirée n-1 phase 3",unite:"VA"},
{etiquette:"SINSTI",name:"Puissance app. Instantanée injectée (SINSTI)",unite:"VA"},
{etiquette:"SMAXIN",name:"Puissance app. max. injectée n",unite:"VA"},
{etiquette:"SMAXIN-1",name:"Puissance app max. injectée n-1",unite:"VA"},
{etiquette:"CCASN",name:"Point n de la courbe de charge active soutirée",unite:"W"},
{etiquette:"CCASN-1",name:"Point n-1 de la courbe de charge active soutirée",unite:"W"},
{etiquette:"CCAIN",name:"Point n de la courbe de charge active injectée",unite:"W"},
{etiquette:"CCAIN-1",name:"Point n-1 de la courbe de charge active injectée",unite:"W"},
{etiquette:"UMOY1",name:"Tension moy. ph. 1",unite:"V"},
{etiquette:"UMOY2",name:"Tension moy. ph. 2",unite:"V"},
{etiquette:"UMOY3",name:"Tension moy. ph. 3",unite:"V"},
{etiquette:"STGE",name:"Registre de Statuts",unite:""},
{etiquette:"STGE01",name:"STGE01 - Contact sec",unite:""},
{etiquette:"STGE02",name:"STGE02 - Organe de coupure",unite:""},
{etiquette:"STGE03",name:"STGE03 - Etat du cache-bornes distributeur",unite:""},
{etiquette:"STGE04",name:"STGE04 - Non utilise - Toujours a 0",unite:""},
{etiquette:"STGE05",name:"STGE05 - Surtension sur une des phases",unite:""},
{etiquette:"STGE06",name:"STGE06 - Depassement de la puissance de reference",unite:""},
{etiquette:"STGE07",name:"STGE07 - Fonctionnement producteur/consommateur",unite:""},
{etiquette:"STGE08",name:"STGE08 - Sens de l’energie active",unite:""},
{etiquette:"STGE09",name:"STGE09 - Tarif en cours sur le contrat fourniture",unite:""},
{etiquette:"STGE10",name:"STGE10 - Tarif en cours sur le contrat distributeur",unite:""},
{etiquette:"STGE11",name:"STGE11 - Mode degrade de l’horloge",unite:""},
{etiquette:"STGE12",name:"STGE12 - Etat de la sortie tele-information",unite:""},
{etiquette:"STGE13",name:"STGE13 - Non utilise - Non utilise",unite:""},
{etiquette:"STGE14",name:"STGE14 - Etat de la sortie communication Euridis",unite:""},
{etiquette:"STGE15",name:"STGE15 - Statut du CPL",unite:""},
{etiquette:"STGE16",name:"STGE16 - Synchronisation CPL",unite:""},
{etiquette:"STGE17",name:"STGE17 - Couleur du jour pour le contrat historique Tempo",unite:""},
{etiquette:"STGE18",name:"STGE18 - Couleur du lendemain pour le contrat historique Tempo",unite:""},
{etiquette:"STGE19",name:"STGE19 - Preavis pointes mobiles",unite:""},
{etiquette:"STGE20",name:"STGE20 - Pointe mobile (PM)",unite:""},
{etiquette:"DPM1",name:"Début Pointe Mobile 1",unite:""},
{etiquette:"FPM1",name:"Fin Pointe Mobile 1",unite:""},
{etiquette:"DPM2",name:"Début Pointe Mobile 2",unite:""},
{etiquette:"FPM2",name:"Fin Pointe Mobile 2",unite:""},
{etiquette:"DPM3",name:"Début Pointe Mobile 3",unite:""},
{etiquette:"FPM3",name:"Fin Pointe Mobile 3",unite:""},
{etiquette:"MSG1",name:"Message court",unite:""},
{etiquette:"MSG2",name:"Message Ultra court",unite:""},
{etiquette:"PRM",name:"PRM",unite:""},
{etiquette:"RELAIS",name:"Relais",unite:""},
{etiquette:"RELAIS01",name:"RELAIS01 - Relais1",unite:""},
{etiquette:"RELAIS02",name:"RELAIS02 - Relais2",unite:""},
{etiquette:"RELAIS03",name:"RELAIS03 - Relais3",unite:""},
{etiquette:"RELAIS04",name:"RELAIS04 - Relais4",unite:""},
{etiquette:"RELAIS05",name:"RELAIS05 - Relais5",unite:""},
{etiquette:"RELAIS06",name:"RELAIS06 - Relais6",unite:""},
{etiquette:"RELAIS07",name:"RELAIS07 - Relais7",unite:""},
{etiquette:"RELAIS08",name:"RELAIS08 - Relais8",unite:""},
{etiquette:"NTARF",name:"Numéro de l’index tarifaire en cours",unite:""},
{etiquette:"NJOURF",name:"Numéro du jour en cours calendrier fournisseur",unite:""},
{etiquette:"NJOURF+1",name:"Numéro du prochain jour calendrier fournisseur",unite:""},
{etiquette:"PJOURF+1",name:"Profil du prochain jour calendrier fournisseur",unite:""},
{etiquette:"PPOINTE",name:"Profil du prochain jour de pointe",unite:""}];

$(".in_datepicker").datepicker();

$('#bt_stopTeleinfoDaemon').on('click', function() {
    stopTeleinfoDeamon();
});

function stopTeleinfoDeamon() {
    $.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // methode de transmission des données au fichier php
        url: "plugins/teleinfo/core/ajax/teleinfo.ajax.php", // url du fichier php
        data: {
            action: "stopDeamon",
        },
        dataType: 'json',
        error: function(request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: 'Le démon a été correctement arrêté : il se relancera automatiquement dans 1 minute', level: 'success'});
        }
    });
}

$('#create_data_teleinfo').on('click', function() {
    document.getElementById("checkbox-autocreate").checked = true;
    $('.eqLogicAction[data-action=save]').click();
});

$('#bt_options').on('click', function() {
    $('#md_modal').dialog({title: "{{Options}}"});
    $('#md_modal').load('index.php?v=d&plugin=teleinfo&modal=options').dialog('open');
});

$('#bt_info_daemon').on('click', function() {
    $('#md_modal').dialog({title: "{{Informations du modem}}"});
    $('#md_modal').load('index.php?v=d&plugin=teleinfo&modal=info_daemon&plugin_id=teleinfo_deamon_conso&slave_id=0').dialog('open');
});

$('.bt_info_external_daemon').on('click', function() {
    var slave_id_tmp = $(this).attr('slave_id');
    $('#md_modal').dialog({title: "{{Informations du modem}}"});
    $('#md_modal').load('index.php?v=d&plugin=teleinfo&modal=info_daemon&plugin_id=teleinfo_deamon&slave_id=' + slave_id_tmp).dialog('open');
});


$('#bt_config').on('click', function() {
    $('#md_modal').dialog({title: "{{Configuration}}"});
    $('#md_modal').load('index.php?v=d&p=plugin&ajax=1&id=rfxcom').dialog('open');
});

$('#btTeleinfoHealth').on('click', function() {
    $('#md_modal').dialog({title: "{{Santé Téléinformation}}"});
    $('#md_modal').load('index.php?v=d&plugin=teleinfo&modal=health').dialog('open');
});

$('#btTeleinfoMaintenance').on('click', function() {
    $('#md_modal').dialog({title: "{{Maintenance Téléinformation}}"});
    $('#md_modal').load('index.php?v=d&plugin=teleinfo&modal=maintenance').dialog('open');
});


$('#btIndex').on('click', function() {
    $.ajax({
        type: 'POST',
        url: 'plugins/teleinfo/core/ajax/teleinfo.ajax.php',
        data: {
            action:'copyVersIndex',
            compteur: $('.eqLogicAttr[data-l1key=logicalId]').value(),
            startDate: $('#in_startDate').value(),
            endDate:  $('#in_endDate').value(),
            indexcopy00: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index00]').value(),
            indexcopy01: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index01]').value(),
            indexcopy02: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index02]').value(),
            indexcopy03: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index03]').value(),
            indexcopy04: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index04]').value(),
            indexcopy05: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index05]').value(),
            indexcopy06: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index06]').value(),
            indexcopy07: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index07]').value(),
            indexcopy08: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index08]').value(),
            indexcopy09: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index09]').value(),
            indexcopy10: $('.eqLogicAttr[data-l1key=configuration][data-l2key=index10]').value(),
            coutcopy00: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex00]').value(),
            coutcopy01: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex01]').value(),
            coutcopy02: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex02]').value(),
            coutcopy03: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex03]').value(),
            coutcopy04: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex04]').value(),
            coutcopy05: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex05]').value(),
            coutcopy06: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex06]').value(),
            coutcopy07: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex07]').value(),
            coutcopy08: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex08]').value(),
            coutcopy09: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex09]').value(),
            coutcopy10: $('.eqLogicAttr[data-l1key=configuration][data-l2key=Coutindex10]').value(),
            coutcopyprod: $('.eqLogicAttr[data-l1key=configuration][data-l2key=CoutindexProd]').value(),
            },
        dataType: 'json',
});
$.hideLoading();


});


$('#btSauve').on('click', function() {
    $.ajax({
        type: 'POST',
        url: 'plugins/teleinfo/core/ajax/teleinfo.ajax.php',
        data: {
            action:'sauveCmd',
            compteur: $('.eqLogicAttr[data-l1key=id]').value(),
            },
        dataType: 'json',
        error: function(error) {
            domUtils.hideLoading()
            jeedomUtils.showAlert({
              message: error.message,
              level: 'danger'
            })
          },
          success: function (data) { 			

			if (data.state != 'ok') {
				$('#div_alert').showAlert({message: '{{Erreur}}', level: 'danger'});
				return;
			}
			else  {
				if (data.result['erreur'] == 'ok'){
					$('#div_alert').showAlert({message: '{{Sauvegarde terminée avec succès}}', level: 'success'});
				} else {
					$('#div_alert').showAlert({message: '{{Problème rencontré lors de la sauvegarde}}', level: 'danger'});
				}
			}
		}

    });


});


$('#btRestaure').on('click', function() {
    if ( $('#idFichierRestaure').value()=='Aucun' || $('#idRestaure').value()=='Aucun'){
        $('#div_alert').showAlert({message: '{{Vous devez sélectionner un index ET un fichier à restaurer}}', level: 'danger'});
    } else {
        $.ajax({
            type: 'POST',
            url: 'plugins/teleinfo/core/ajax/teleinfo.ajax.php',
            data: {
                action:'restaureCmd',
                compteur: $('.eqLogicAttr[data-l1key=id]').value(),
                fichierRestaure: $('#idFichierRestaure').value(),
                idRestaure: $('#idRestaure').value(),
                },
            dataType: 'json',
            error: function(error) {
                domUtils.hideLoading()
                jeedomUtils.showAlert({
                message: error.message,
                level: 'danger'
                })
            },
            success: function (data) { 			

                if (data.state != 'ok') {
                    $('#div_alert').showAlert({message: '{{Erreur}}', level: 'danger'});
                    return;
                }
                else  {
                    if (data.result['erreur'] == 'ok'){
                        $('#div_alert').showAlert({message: '{{Restauration terminée avec succès, pensez à (ré)générer les statistiques}}', level: 'success'});
                    } else {
                        $('#div_alert').showAlert({message: '{{Problème rencontré lors de la restauration}}', level: 'danger'});
                    }
                }
            }

        });
    }

});

$('#btSuppr').on('click', function() {
    if ( $('#idCompteurSuppr').value()=='Aucun'){
        $('#div_alert').showAlert({message: '{{Vous devez sélectionner un compteur dont les données seront supprimées}}', level: 'danger'});
    } else {
        $.ajax({
            type: 'POST',
            url: 'plugins/teleinfo/core/ajax/teleinfo.ajax.php',
            data: {
                action:'supprCmd',
                compteur: $('#idCompteurSuppr').value(),
                },
            dataType: 'json',
            error: function(error) {
                domUtils.hideLoading()
                jeedomUtils.showAlert({
                message: error.message,
                level: 'danger'
                })
            },
            success: function (data) { 			

                if (data.state != 'ok') {
                    $('#div_alert').showAlert({message: '{{Erreur}}', level: 'danger'});
                    return;
                }
                else  {
                    if (data.result['erreur'] == 'ok'){
                        $('#div_alert').showAlert({message: '{{Suppression des fichiers de sauvegarde du compteur terminée avec succès}}', level: 'success'});
                    } else {
                        $('#div_alert').showAlert({message: '{{Problème rencontré lors de la suppression}}', level: 'danger'});
                    }
                }
            }

        });
    }
});


$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    init(_cmd.id);
    var selRequestType = '';
    var type_of_data = init(_cmd.configuration['type']);
    //alert(type_of_data);
    if(init(_cmd.configuration['type']) == 'stat' || init(_cmd.configuration['type']) == 'panel'){
        selRequestType = '<select class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="info_conso">';
        selRequestType += '<option value="AUCUN">Aucune</option>';
        selRequestType += '<option value="STAT_YESTERDAY">Conso totale hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_HP">Conso HP hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_HC">Conso HC hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_PROD">Production hier</option>';
        selRequestType += '<option value="STAT_TODAY">Conso totale Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_HP">Conso HP Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_HC">Conso HC Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_PROD">Production Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX00">Conso Totale Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX01">Conso index01 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX02">Conso index02 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX03">Conso index03 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX04">Conso index04 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX05">Conso index05 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX06">Conso index06 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX07">Conso index07 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX08">Conso index08 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX09">Conso index09 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX10">Conso index10 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX00_COUT">Coût total Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX01_COUT">Coût index01 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX02_COUT">Coût index02 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX03_COUT">Coût index03 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX04_COUT">Coût index04 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX05_COUT">Coût index05 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX06_COUT">Coût index06 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX07_COUT">Coût index07 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX08_COUT">Coût index08 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX09_COUT">Coût index09 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_TODAY_INDEX10_COUT">Coût index10 Aujourd\'hui</option>';
        selRequestType += '<option value="STAT_YESTERDAY_PROD_COUT">Revenus Production hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX00">Conso Totale hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX01">Conso index01 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX02">Conso index02 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX03">Conso index03 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX04">Conso index04 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX05">Conso index05 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX06">Conso index06 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX07">Conso index07 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX08">Conso index08 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX09">Conso index09 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX10">Conso index10 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX00_COUT">Coût total hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX01_COUT">Coût index01 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX02_COUT">Coût index02 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX03_COUT">Coût index03 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX04_COUT">Coût index04 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX05_COUT">Coût index05 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX06_COUT">Coût index06 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX07_COUT">Coût index07 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX08_COUT">Coût index08 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX09_COUT">Coût index09 hier</option>';
        selRequestType += '<option value="STAT_YESTERDAY_INDEX10_COUT">Coût index10 hier</option>';
        selRequestType += '<option value="TENDANCE_DAY">Tendance journalière de consommation</option>';
        selRequestType += '<option value="PPAP_MANUELLE">Conso moy dernière minute</option>';
        selRequestType += '<option value="STAT_MOY_LAST_HOUR">Conso moy dernière heure</option>';
        selRequestType += '<option value="STAT_MONTH">[obsolete] Conso Mois en cours</option>';
        selRequestType += '<option value="STAT_MONTH_PROD">[obsolete] Production Mois en cours</option>';
        selRequestType += '<option value="STAT_MONTH_LAST_YEAR">[obsolete] Conso Mois en cours année précédente</option>';
        selRequestType += '<option value="STAT_YEAR_LAST_YEAR">[obsolete] Conso Année précédente au même jour</option>';
        selRequestType += '<option value="STAT_YEAR">[obsolete] Conso Année en cours</option>';
        selRequestType += '<option value="STAT_YEAR_PROD">[obsolete] Production Année en cours</option>';
        selRequestType += '<option value="STAT_LASTMONTH">[obsolete] Conso Mois dernier</option>';
        selRequestType += '<option value="STAT_JAN_HP">[obsolete] Conso Janvier HP</option>';
        selRequestType += '<option value="STAT_FEV_HP">[obsolete] Conso Février HP</option>';
        selRequestType += '<option value="STAT_MAR_HP">[obsolete] Conso Mars HP</option>';
        selRequestType += '<option value="STAT_AVR_HP">[obsolete] Conso Avril HP</option>';
        selRequestType += '<option value="STAT_MAI_HP">[obsolete] Conso Mai HP</option>';
        selRequestType += '<option value="STAT_JUIN_HP">[obsolete] Conso Juin HP</option>';
        selRequestType += '<option value="STAT_JUI_HP">[obsolete] Conso Juillet HP</option>';
        selRequestType += '<option value="STAT_AOU_HP">[obsolete] Conso Août HP</option>';
        selRequestType += '<option value="STAT_SEP_HP">[obsolete] Conso Septembre HP</option>';
        selRequestType += '<option value="STAT_OCT_HP">[obsolete] Conso Octobre HP</option>';
        selRequestType += '<option value="STAT_NOV_HP">[obsolete] Conso Novembre HP</option>';
        selRequestType += '<option value="STAT_DEC_HP">[obsolete] Conso Décembre HP</option>';
        selRequestType += '<option value="STAT_JAN_HC">[obsolete] Conso Janvier HC</option>';
        selRequestType += '<option value="STAT_FEV_HC">[obsolete] Conso Février HC</option>';
        selRequestType += '<option value="STAT_MAR_HC">[obsolete] Conso Mars HC</option>';
        selRequestType += '<option value="STAT_AVR_HC">[obsolete] Conso Avril HC</option>';
        selRequestType += '<option value="STAT_MAI_HC">[obsolete] Conso Mai HC</option>';
        selRequestType += '<option value="STAT_JUIN_HC">[obsolete] Conso Juin HC</option>';
        selRequestType += '<option value="STAT_JUI_HC">[obsolete] Conso Juillet HC</option>';
        selRequestType += '<option value="STAT_AOU_HC">[obsolete] Conso Août HC</option>';
        selRequestType += '<option value="STAT_SEP_HC">[obsolete] Conso Septembre HC</option>';
        selRequestType += '<option value="STAT_OCT_HC">[obsolete] Conso Octobre HC</option>';
        selRequestType += '<option value="STAT_NOV_HC">[obsolete] Conso Novembre HC</option>';
        selRequestType += '<option value="STAT_DEC_HC">[obsolete] Conso Décembre HC</option>';
        selRequestType += '</select>';
    }
    else{
        selRequestType = '<select class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="info_conso">';
        liste_donnees.forEach(function(element) {
            selRequestType += '<option value="' + element.etiquette + '">' + element.name + '</option>';
        });
        selRequestType += '</select>';
    }

    if(init(_cmd.configuration['type']) == 'panel'){
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '" style="display:none">';
    }else if(init(_cmd.configuration['type']) == 'health'){
        var tr = '';
        if(_cmd.configuration['NGTF']){
            $("#typeAbonnement").html(_cmd.configuration['NGTF'].value);
        }
        else if (_cmd.configuration['OPTARIF']){
            if(_cmd.configuration['OPTARIF'].value.includes('HC')) {
                $("#typeAbonnement").html("Heures Creuses");
            }
            else if (_cmd.configuration['OPTARIF'].value.includes('BBR')){
                $("#typeAbonnement").html("Tempo");
            }
            else if (_cmd.configuration['OPTARIF'].value.includes('EJP')){
                $("#typeAbonnement").html("EJP");
            }
            else {
                $("#typeAbonnement").html(_cmd.configuration['OPTARIF'].value);
            }
        }
    }
    else if (init(_cmd.configuration['type']) == 'stat'){
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    }
    else{
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    }
    if(init(_cmd.configuration['type']) != 'health'){
        tr += '<td>';
            tr += '<span class="cmdAttr expertModeVisible" data-l1key="id"></span>';
        tr += '</td>';
        tr += '<td>';
            tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom}}"></td>';
        tr += '</td>';
        tr += '<td>';
            tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" value="info" disabled>';
            tr += '<select style="margin-top : 5px;" class="cmdAttr form-control input-sm tooltips" title="{{Numérique pour les indexs et nombres, Autre pour les chaines de caractères (Tranche tarifaire par exemple.}}" data-l1key="subType"><option value="numeric">Numérique</option><option value="binary">Binaire</option><option value="string">Autre</option></select>';
        tr += '</td>';
        tr += '<td>';
            tr +=  selRequestType;
        tr += '</td>';
        tr += '<td>';
            tr += '<span><input class="cmdAttr" style="display:none" data-l1key="configuration" data-l2key="type" value="' + init(_cmd.configuration['type']) +'"/></span>';
            tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
            tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';

            if(init(_cmd.configuration['info_conso']) == 'TENDANCE_DAY'){
                tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr tooltips checkbox-inline" title="Spécifie si le calcul de la tendance se fait sur la journée entière ou sur la plage jusqu\'à l\'heure actuelle." data-l1key="configuration" data-l2key="type_calcul_tendance"/> {{Journée entière}}</label></span>';
            }

            tr += '</br><input class="cmdAttr form-control tooltips input-sm" data-l1key="unite" style="margin-left:10px;width: 20%;display: inline-block;" placeholder="Unité" title="{{Unité de la donnée (Wh, A, kWh...) pour plus d\'informations aller voir le wiki}}">';

            tr += '<input style="margin-left:10px;width: 20%;display: inline-block;" class="tooltips cmdAttr form-control expertModeVisible input-sm" data-l1key="cache" data-l2key="lifetime" placeholder="{{Lifetime cache}}">';
            tr += '<input style="margin-left:10px;width: 20%;display: inline-block;" class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Borne minimum de la valeur}}" > ';
            tr += '<input style="margin-left:10px;width: 20%;display: inline-block;" class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Borne maximum de la valeur}}" >';

            if(init(_cmd.configuration['info_conso']) == 'ADPS' || init(_cmd.configuration['info_conso']) == 'ADIR1' || init(_cmd.configuration['info_conso']) == 'ADIR2' || init(_cmd.configuration['info_conso']) == 'ADIR3'){
                //tr += '<input class="cmdAttr form-control input-sm" data-l1key="logicalId" value="0">';
                tr += '</br><input style="margin-left:10px;width: 20%;display: inline-block;" class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateValue" placeholder="{{Valeur retour d\'état}}">';
                tr += '<input style="margin-left:10px;width: 20%;display: inline-block;" class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateTime" placeholder="{{Durée avant retour d\'état (min)}}">';
            }
        tr += '</td>';
        tr += '<td>';
            tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
        tr += '</td>';
        tr += '<td>';

            tr += '<div class="input-group pull-right" style="display:inline-flex"><span class="input-group-btn">';

            if (is_numeric(_cmd.id)) {
                tr += '<a class="btn btn-default btn-xs cmdAction roundedLeft" data-action="configure"><i class="fa fa-cogs"></i></a>';
                tr += '<a class="btn btn-default btn-xs cmdAction tooltips" title="Attention, ne sert qu\'a afficher la dernière valeur reçu." data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
            }
            tr += '<a class="btn btn-danger btn-xs cmdAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i></a> ';
            tr += '</span></div>';
        tr += '</td>';
        tr += '</tr>';

        if (isset(_cmd.configuration.info_conso)) {
        //$('#table_cmd tbody tr:last .cmdAttr[data-l1key=configuration][data-l2key=info_conso]').value(init(_cmd.configuration.info_conso));
        //$('#table_cmd tbody tr:last .cmdAttr[data-l1key=configuration][data-l2key=info_conso]').trigger('change');
        }

        $('#table_cmd tbody').append(tr);
        $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
        var tr = $('#table_cmd tbody tr:last');
        if(init(_cmd.unite) == ''){
            if(init(_cmd.configuration['info_conso']) == 'ADPS'){
                tr.find('.cmdAttr[data-l1key=unite]').append("A");
                tr.setValues(_cmd, '.cmdAttr');
            }
        }
        else{

        }
    }
}

$('#addStatToTable').on('click', function() {
    var _cmd = {type: 'info'};
    _cmd.configuration = {'type':'stat'};
    addCmdToTable(_cmd);
});

$('#addDataToTable').on('click', function() {
    var _cmd = {type: 'info'};
    _cmd.configuration = {'type':'data'};
    addCmdToTable(_cmd);
});

$('.eqLogicAction[data-action=createCommunityPost]').on('click', function (event) {
    jeedom.plugin.createCommunityPost({
      type: eqType,
      error: function(error) {
        domUtils.hideLoading()
        jeedomUtils.showAlert({
          message: error.message,
          level: 'danger'
        })
      },
      success: function(data) {
        let element = document.createElement('a');
        element.setAttribute('href', data.url);
        element.setAttribute('target', '_blank');
        element.style.display = 'none';
        document.body.appendChild(element);
        element.click();
        document.body.removeChild(element);
      }
    });
    return;
});