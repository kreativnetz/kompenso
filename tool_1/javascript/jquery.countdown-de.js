/* http://keith-wood.name/countdown.html
   German initialisation for the jQuery countdown extension
   Written by Samuel Wulf. */	 
(function($) {
	$.countdown.regional['de'] = {
		labels: ['Jahre', 'Monate', 'Wochen', 'Tage', 'Stunden', 'Minuten', 'Sekunden'],
		labels1: ['Jahr', 'Monat', 'Woche', 'Tag', 'Stunde', 'Minute', 'Sekunde'],
		compactLabels: ['J', 'M', 'W', 'T'],
		whichLabels: null,
		timeSeparator: ':', isRTL: false
	};

	$.countdown.regional['fr'] = {
		labels: ['ans', 'mois', 'semaines', 'jours', 'heures', 'minutes', 'secondes'],
		labels1: ['an', 'mois', 'semaine', 'jour', 'heure', 'minute', 'seconde'],
		compactLabels: ['A', 'M', 'S', 'J'],
		whichLabels: null,
		timeSeparator: ':', isRTL: false
	};

	$.countdown.regional['it'] = {
		labels: ['anni', 'mesi', 'settimane', 'giorni', 'ore', 'minuti', 'secondi'],
		labels1: ['anno', 'mese', 'settimana', 'giorno', 'ora', 'minuto', 'secondo'],
		compactLabels: ['A', 'M', 'S', 'G'],
		whichLabels: null,
		timeSeparator: ':', isRTL: false
	};

})(jQuery);
