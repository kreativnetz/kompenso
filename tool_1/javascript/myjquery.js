// JavaScript Document

/* ARRAY-FUNKTIONEN */

Array.prototype.in_array = function(value) {
	for (var i in this) {
		if (this[i] === value) { 
			return true; 
		}
	}
	return false;
}

Array.prototype.count_in_array = function(value) {
	var counter = 0;
	for (var i in this) {
		if (this[i] === value) { 
			counter++;
		}
	}
	return counter;
}

/* SPEICHERMELDUNG */

speicherbox = new Image();
speicherbox.src = "../layout/speichermeldung.png";
transe = new Image();
transe.src = "../layout/transe_hell.png";

function speichermeldung(titel,text) {
	if (titel == undefined) { titel = 'Bitte warten...'; }
	if (text == undefined) { text = titel; }
	
	text = '<h6>' + titel + '</h6>' + text + '<br /><br /><button style=\'width:90px; cursor:pointer;\' onclick=\'close_speichermeldung()\'>abbrechen</button>';
/*  + '<br><br><a style="cursor:pointer;" onclick="getElementById(\'savediv\').style.display = \'none\'">abbrechen</a>'; */

	/* Div erstellen */
	var newdiv = document.createElement('div');
	newdiv.setAttribute('id','savediv');
	document.body.appendChild(newdiv);
	
	/* Meldung erstellen */
	var newmsg = document.createElement('div');
	newmsg.setAttribute('id','savealert');
	newmsg.setAttribute('class','dialog');
	newmsg.style.backgroundImage = 'url(../layout/speichermeldung.png)'; 
	document.getElementById('savediv').appendChild(newmsg);
	document.getElementById('savealert').innerHTML = text;
	return false;
}

function close_speichermeldung() {
	document.getElementById('savediv').style.display = 'none';
}


/* TOOL TIP */

function show_tool_tip(x,y,text) {
	y -= 55;
	if (text == undefined) { text = 'Hier spielt die Musik!'; }
	
	/* prüfen ob das div schon existiert */
	if (document.getElementById('tool_tip')) { 
		/* Div verschieben */
		$('#tool_tip').animate({ marginLeft: x, top: y }, 500, "swing");
		$('#tool_tip').html(text);
	} else {
		/* Div erstellen */
		var newdiv = document.createElement('div');
		newdiv.setAttribute('id','tool_tip');
		newdiv.style.top = y+"px";
		newdiv.style.marginLeft = x+"px";
		newdiv.innerHTML = text;
		document.body.appendChild(newdiv);
	}
	return false;
}

function hide_tool_tip() {

	/* prüfen ob das div schon existiert */
	if (document.getElementById('tool_tip')) { 
		$('#tool_tip').remove();
	}
	return false;
}


/* DIALOG */

dialogbox = new Image();
dialogbox.src = "../layout/dialog_ok.png";

function dialog(titel,text,buttontext,bad) {
	if (titel == undefined) { titel = 'Meldung'; }
	if (text == undefined) { text = titel; }
	
	text = '<h6>' + titel + '</h6>' + text + '<br><br><input type="button" style="cursor:pointer;" onclick="getElementById(\'dialogdiv\').style.display = \'none\'" value="'+ buttontext +'" />';

	/* Div erstellen falls es nicht schon existiert */
	if (!document.getElementById('dialogdiv')) { 	
		var newdiv = document.createElement('div');
		newdiv.setAttribute('id','dialogdiv');
		document.body.appendChild(newdiv);
	}
	if (!document.getElementById('dialog')) { 		
		/* Meldung erstellen */
		var newdlg = document.createElement('div');
		newdlg.setAttribute('id','dialog');
		newdlg.setAttribute('class','dialog');
		if (bad!=1) {
			newdlg.style.backgroundImage = 'url(../layout/dialog_ok.png)'; 
		} else {
			newdlg.style.backgroundImage = 'url(../layout/dialog_alert.png)'; 
		}
		document.getElementById('dialogdiv').appendChild(newdlg);
	}
	// Text zuweisen und anzeigen
	document.getElementById('dialog').innerHTML = text;
	document.getElementById('dialogdiv').style.display = 'block';
	return false;
}

/* TABELLEN */

function table_highlight(table_name) {
	
	// Weise der Tabelle einen Rahmen zu
	$(table_name).css('border','0px green solid');
	
	// Weise den Zeilen die Streifen zu
	$(table_name + " tr:odd").addClass("stripe1");
	$(table_name + " tr:even").addClass("stripe2");
	
	// Weise den Zeilen die mouseover-Farbe zu
	$(table_name + " tr").hover(
		function() {
			$(this).addClass("highlight");
		},
		function() {				
			$(this).removeClass("highlight");
		}
	);
	
	// Wenn geklickt wurde, dann soll die mouseover-Farbe verschwinden
	$(".icon").bind("click", 
		function() {
			$("tr").removeClass("highlight");
		} );
}


function swap_tab(name) {

	$("#"+name+" div:eq(1)").css('display','none');
	$("#"+name+" h3:eq(1)").css('margin-left','60px');
	$("#"+name+" h3:eq(1)").css('color','#9c8');
	$("#"+name+" h3").css('cursor','pointer');
	$("#"+name+" h3").css('position','absolute');

	$("#"+name+" h3:eq(0)").bind("click",
		function() {
			$("#"+name+" div:eq(0)").css('display','block');
			$("#"+name+" div:eq(1)").css('display','none');
			$("#"+name+" h3:eq(0)").css('color','green');
			$("#"+name+" h3:eq(1)").css('color','#9c8');
		}
	);

	$("#"+name+" h3:eq(1)").bind("click",
		function() {
			$("#"+name+" div:eq(1)").css('display','block');
			$("#"+name+" div:eq(0)").css('display','none');
			$("#"+name+" h3:eq(1)").css('color','green');
			$("#"+name+" h3:eq(0)").css('color','#9c8');
		}
	);

}

function swap_3_tab(name, active) {
	if (!active) active = 0;
	
	for (tab = 0; tab <= 2; tab++) if (tab != active) $("#"+name+" div:eq("+tab+")").css('display','none');
	for (tab = 0; tab <= 2; tab++) if (tab != active) $("#"+name+" h3:eq("+tab+")").removeClass("active_tab");
	$("#"+name+" h3:eq(1)").css('margin-left','70px');
	$("#"+name+" h3:eq(2)").css('margin-left','140px');
	$("#"+name+" h3").css('position','absolute');
	$("#"+name+" h3").css('cursor','pointer');
	$("#"+name+" h3").css('width','56px');

	$("#"+name+" h3:eq(0)").bind("click",
		function() {
			$("#"+name+" div").css('display','none');
			$("#"+name+" div:eq(0)").css('display','block');
			$("#"+name+" h3").removeClass("active_tab");
			$("#"+name+" h3:eq(0)").addClass("active_tab");
		}
	);

	$("#"+name+" h3:eq(1)").bind("click",
		function() {
			$("#"+name+" div").css('display','none');
			$("#"+name+" div:eq(1)").css('display','block');
			$("#"+name+" h3").removeClass("active_tab");
			$("#"+name+" h3:eq(1)").addClass("active_tab");
		}
	);

	$("#"+name+" h3:eq(2)").bind("click",
		function() {
			$("#"+name+" div").css('display','none');
			$("#"+name+" div:eq(2)").css('display','block');
			$("#"+name+" h3").removeClass("active_tab");
			$("#"+name+" h3:eq(2)").addClass("active_tab");
		}
	);


}