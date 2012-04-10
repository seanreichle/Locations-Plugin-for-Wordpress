
var geocoder;
var map;
var markersArray = [];
var BaseUrl = document.location;
var newURL = window.location.protocol + "://" + window.location.host + "/" + window.location.pathname;
var locationsArray = [];
var UsersAddress = "";
var infowindow;

$(document).ready(function(){ 
	if( $('#map_canvas') ){  
		var myOptions = {
			zoom: 6,
			center: new google.maps.LatLng(33.50959, -112.03288299999997),
			mapTypeId: google.maps.MapTypeId.ROADMAP
		}; 
		
		map = new google.maps.Map( document.getElementById('map_canvas'), myOptions);
		geocoder = new google.maps.Geocoder();
		
    	google.maps.event.addListener(map, 'click', function(event) {
			if (infowindow) {
				infowindow.close();
			}
		});
			  
		populateSearch();
		
		//Support for browser GPS position!
		BrowserDetect.init();
		if(BrowserDetect.OS == "iPhone/iPod" && navigator.geolocation){
			getLocation()
		} 
	}
	
});

google.maps.Map.prototype.clearOverlays = function() {
  if (markersArray) {
    for (var i = 0; i < markersArray.length; i++ ) {
      markersArray[i].setMap(null);
    }
  }
  markersArray= [];
}

 
function populateSearch(){
  if( $('#map_search')) {
	    var data ="&lm_action=returnSearchTool";
		$.ajax({
			url: LM_BASENAME+"/wp-admin/admin-ajax.php?action=lm_ajaxResponse",
			context: document.body,
			type: "POST",
			dataType: "html",
			data : data,
			success: function(postback){ 
				$('#map_search').html(postback);

				if(autoLookup){
					if(autoLookup == "ip"){
						populateLocationList();
						//usersAddy = (users_ip) ? getAddyByIP(users_ip) : "";
					}else{
						$('#search_addy').val(autoLookup);
						Geocode();
					}
				}else{
					populateLocationList();
				}
				
			},
			complete: function(jqXHR, textStatus){  
			
				$('#map_search_container .help').click(function() {
					$('.help').hide();  
					$('#search_addy').focus();
				});
				$('#search_addy').live('focusin',function(){
					$('.help').hide();
				});
				$('#search_addy').blur(function() {
					if( $('#search_addy').val() == "")	$('.help').show();
				});


			}
		});
  }
}

function getAddyByIP(ip){
	$.getJSON("http://api.ipinfodb.com/v3/ip-city/?format=json&key=6dc86b6528ddb95547bddacade58f34bd9a9ed69e2d31caac8780fbb987ef098&ip="+ip+"&callback=?",
	function(data){
		var addy = data['cityName'] + ", " + data['regionName'] + ", " + data['zipCode'];
		$('#search_addy').val("");
		lookupNearest(data['latitude'], data['longitude']);
	});
}

function populateLocationList(){
  if( $('#map_marker_list')) {
		var data ="&lm_action=returnLocations";
		$.ajax({
			url: LM_BASENAME+"/wp-admin/admin-ajax.php?action=lm_ajaxResponse",
			context: document.body,
			type: "POST",
			dataType: "json",
			data : data,
			success: function(postback){
				locationsArray = postback;
			    if(map) map.clearOverlays();
				buildListFromMarkers(postback);
			},
			complete: function(jqXHR, textStatus){  }
		}); 
   }
}

function Geocode(){
	var addy = $('#search_addy').val();
	if(addy!=""){
		geocoder = new google.maps.Geocoder();
		geocoder.geocode( { 'address': addy}, function(results, status) {
		  if (status == google.maps.GeocoderStatus.OK) {
			 if(map) map.clearOverlays();
			
			//We got 
			var lat = results[0].geometry.location.lat();
			var lng = results[0].geometry.location.lng();
					
			//Create Google marker for User!
			var userLatLng = new google.maps.LatLng(lat, lng);
			var marker = new google.maps.Marker({
				position: userLatLng,
				map: map,
				animation: google.maps.Animation.DROP,
				title: "You are here!",
				html: "<span class= 'meMarker'>You are here!</span>"
			
			});
			markersArray.push(marker);
			google.maps.event.addListener(marker,"click",function(){
				alert("This is YOUR marker!");	
			});
	
			//Generate list of nearest locations
			lookupNearest(lat,lng);
		  }
		});
	}else{
		populateLocationList();
	}
}

function lookupNearest(lat,lng){
	var numberToShow = 4;
	if(map) map.clearOverlays();

	var data ="&lm_action=returnNearest&lat="+lat+"&lng="+lng;
	var html = "";
	$.ajax({
		url: LM_BASENAME+"/wp-admin/admin-ajax.php?action=lm_ajaxResponse",
		context: document.body,
		type: "POST",
		dataType: "JSON",
		data : data,
		success: function(postback){ 
			var markers = eval(postback);
			if(markers.length>0 && markers[0]['distance']!=undefined){
				locationsArray = postback;
				buildListFromMarkers(markers, numberToShow);
			}
			
		},
		complete: function(jqXHR, textStatus){  }
	});
}

function buildListFromMarkers(markers,maxShow){
		$('#map_marker_list').html("");
		if(!maxShow) maxShow = markers.length;
		
		var distance = "";
		//&sll=40.70192,-117.131245&sspn=16.657707,36.254883&geocode=FSrQ2gIdjoq3-CkH_5rQHQeQVDF4GPKgN395Dg%3BFdZQ_wEdjYNS-SlpIa0pbw0rhzF4o6SQijCjSg&oq=11324+32nd+dr&mra=ls&t=m&z=6"
		
		html = "<ul><li>";
		var nearFocus = false;
		var x;
		for( x in markers){ 
			var startAddress = "";
			var endAddress = markers[x]['address']+",+"+markers[x]['city']+",+"+markers[x]['state']+"+"+markers[x]['zip'];
			var dirLink = "http://maps.google.com/maps?saddr="+startAddress+"&daddr="+endAddress+"&hl=en";
				
			if(markers[x]['distance'] >0 && markers[x]['distance'] < 100) distance = " <span>" + markers[x]['distance'] + " mi.</span>";
			html += "<ul>";
			html += "<li><h4>" + markers[x]['title']  + distance + "</h4></li>";
			html += '<div class="row"><div class="columns seven">'
			html += "<ul class='no-border'><li>" + markers[x]['address'] + "</li>";
			if(markers[x]['suite'] != undefined) html += "<li>" + markers[x]['suite'] + "</li>";
			html += "<li>" + markers[x]['city'] + " " + markers[x]['state'] + ", " + markers[x]['zip'] + "</li>";
			html += "<li><span>Phone:</span> " + markers[x]['phone'] + "</li>";
			// html += "<li><span>Email: </span><a href='mailto:"+ markers[x]['email'] +"?subject=Nationwide Vision Inquiry'>"+ markers[x]['title'] +"</a></li>";  We need to work out the issue with the address
			html += "<li><div class='label'>HOURS:</div>"
			html += "<div class='times'>" + markers[x]['hours'] + "</div></li></ul>";
			html += "</div><div class='columns five text-center'>";
			html += "<a class='simple nice button yellow small radius marg-bot-half' target='_blank' href='"+dirLink+"'>Get Directions</a>";
			html += "<a class='simple nice button yellow small radius make-an-appointment' id='mkAppt_"+x+"' data-idi='"+x+"' data-idi="+ markers[x]['title'] +" data-location='"+ markers[x]['title'] +"' data-email='"+ markers[x]['email'] +"' href='#make-an-appointment'>Make an Appointment</a>";
			html += "</div>";
			html += "</ul>";					
			
			if(markers[x]['lat'] && markers[x]['lng']){
				//Add marker by GeoCodes
				var bubble = "";
				bubble += "<h4>"+markers[x]['title']+"</h4>";
				bubble += markers[x]['address'] + "<br>";
				bubble += (markers[x]['suite']!="" && markers[x]['suite']!=undefined) ? markers[x]['suite'] + "<br>" : "" ; 
				bubble += markers[x]['city'] + ", " + markers[x]['state'] + ". " + markers[x]['zip'] + "<br>";
				bubble += markers[x]['phone'] + "<br>";
				// bubble += "<a href='mailto:"+ markers[x]['email'] +"?subject=Nationwide Vision Inquiry'>"+ markers[x]['title'] +"</a><br>";
				bubble += markers[x]['hours'] + "<br>";
				bubble += "<a target='_blank' href='"+dirLink+"'>Get Directions</a>"
				
				markers[x]['bubble'] = bubble;
				DisplayMarker(markers[x]);
			}else{				
				//Add marker by Address
				var post_id = markers[x]['post_id'];
				var Address = markers[x]['address'];
				if(markers[x]['suite'] != undefined) Address += " " + markers[x]['suite'];
				if(markers[x]['city'] != undefined) Address += ", " + markers[x]['city'] 
				if(markers[x]['state'] != undefined) Address += ", " + markers[x]['state'];
				if(markers[x]['zip'] != undefined) Address += ", " + markers[x]['zip'];
				codeAddress(post_id, Address);
			}
			
		}
		html += "</li></ul>";
		$('#map_marker_list').html(html);
		$('#map_marker_list').lionbars();
		setBounds(maxShow);
}

function updateLatLng(post_id,geo){
	var geoObj = geo.split(',');
	var data = "&lm_action=updatelatlng&post_id="+post_id+"&lat="+geoObj[0]+"&lng="+geoObj[1];
	$.ajax({
		url: LM_BASENAME+"/wp-admin/admin-ajax.php?action=lm_ajaxResponse",
		context: document.body,
		type: "POST",
		dataType: "html",
		data : data,
		success: function(postback){  
			//alert(postback); 
		},
		complete: function(jqXHR, textStatus){  }
	});
}

function getLocation() {
	// Get location no more than 10 minutes old. 600000 ms = 10 minutes.
	navigator.geolocation.getCurrentPosition(showLocation, showError, {enableHighAccuracy:true,maximumAge:600000});
}
function showError(error) {
	alert(error.code + ' ' + error.message);
}

//Get users current position if on iphone or other with GPS!
function showLocation(position) {	
	/*
	infoMarkup='<p>Latitude: ' + position.coords.latitude + '</p>' 
	+ '<p>Longitude: ' + position.coords.longitude + '</p>' 
	+ '<p>Accuracy: ' + position.coords.accuracy + '</p>' 
	+ '<p>Altitude: ' + position.coords.altitude + '</p>' 
	+ '<p>Altitude accuracy: ' + position.coords.altitudeAccuracy + '</p>' 
	+ '<p>Speed: ' + position.coords.speed + '</p>' 
	+ '<p>Heading: ' + position.coords.heading + '</p>';
	*/

	lat = position.coords.latitude;
	long = position.coords.longitude;
	var userLatLng = new google.maps.LatLng(lat, long);
	
	var marker = new google.maps.Marker({
        position: userLatLng,
        map: map,
        animation: google.maps.Animation.DROP,
        title: "You are here!",
        html: "<span class= 'meMarker'>You are here!</span>"
    });
}

function DisplayMarker(markersLocations){
	if(markersLocations['lat'] && markersLocations['lng']){
		var myLatlng = new google.maps.LatLng(markersLocations['lat'], markersLocations['lng']);
		var myOptions = {
			center: myLatlng,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		}
		var title = markersLocations['title'];
		var Markerbubble = markersLocations['bubble'];	
		
		var marker = new google.maps.Marker({
		  position: myLatlng,
		  animation: google.maps.Animation.DROP,
		  map: map,
		  title: title
		});
		markersArray.push(marker);
		
		infowindow = new google.maps.InfoWindow();
		google.maps.event.addListener(marker,"click",
			function(){
				if(infowindow){
					infowindow.close();
					infowindow = new google.maps.InfoWindow();
				}
				infowindow.setContent(Markerbubble);
				infowindow.open(map,marker);
		},toggleBounce);
		
		// setBounds();

	}
}

function toggleBounce() {
  if (marker.getAnimation() != null) {
    marker.setAnimation(null);
  } else {
    marker.setAnimation(google.maps.Animation.BOUNCE);
  }
}
			
function codeAddress(post_id, Addy) {
	var address = Addy;
	geocoder = new google.maps.Geocoder();
	geocoder.geocode( { 'address': address}, function(results, status) {
	  if (status == google.maps.GeocoderStatus.OK) {
		var lat = results[0].geometry.location.lat();
		var lng = results[0].geometry.location.lng();
		updateLatLng(post_id, lat+","+lng);
		/*
		  var image = new google.maps.MarkerImage('/images/map-marker.png',
          	// This marker is 20 pixels wide by 32 pixels tall.
          	new google.maps.Size(32, 37),
          	// The origin for this image is 0,0.
          	new google.maps.Point(0,0),
          	// The anchor for this image is the base of the flagpole at 0,32.
          	new google.maps.Point(16, 37));
		 */
		var marker = new google.maps.Marker({
		  map: map,
		  position: results[0].geometry.location
		});
		  
		markersArray.push(marker);
		google.maps.event.addListener(marker,"click",function(){
		});
		  		  
	  } else {
		//alert("Geocode was not successful for the following reason: " + status);
	  }
	  
	  //setBounds();
	});
}


function setBounds(focusOn){
	
	var bounds = new google.maps.LatLngBounds();
	if(!focusOn) focusOn = markersArray.length -1;
	for (var i = 0; i < focusOn; i++) {
		
		var marker = markersArray[i];
		if(marker){
			var latlng = marker.getPosition();
			var lat = latlng.lat();
			var lng = latlng.lng();
			var myLatlng = new google.maps.LatLng(lat, lng);
			bounds.extend(myLatlng);
		}
	}
	map.fitBounds(bounds);
}

var BrowserDetect = {
	init: function () {
		this.browser = this.searchString(this.dataBrowser) || "An unknown browser";
		this.version = this.searchVersion(navigator.userAgent)
			|| this.searchVersion(navigator.appVersion)
			|| "an unknown version";
		this.OS = this.searchString(this.dataOS) || "an unknown OS";
	},
	searchString: function (data) {
		for (var i=0;i<data.length;i++)	{
			var dataString = data[i].string;
			var dataProp = data[i].prop;
			this.versionSearchString = data[i].versionSearch || data[i].identity;
			if (dataString) {
				if (dataString.indexOf(data[i].subString) != -1)
					return data[i].identity;
			}
			else if (dataProp)
				return data[i].identity;
		}
	},
	searchVersion: function (dataString) {
		var index = dataString.indexOf(this.versionSearchString);
		if (index == -1) return;
		return parseFloat(dataString.substring(index+this.versionSearchString.length+1));
	},
	dataBrowser: [
		{
			string: navigator.userAgent,
			subString: "Chrome",
			identity: "Chrome"
		},
		{ 	string: navigator.userAgent,
			subString: "OmniWeb",
			versionSearch: "OmniWeb/",
			identity: "OmniWeb"
		},
		{
			string: navigator.vendor,
			subString: "Apple",
			identity: "Safari",
			versionSearch: "Version"
		},
		{
			prop: window.opera,
			identity: "Opera",
			versionSearch: "Version"
		},
		{
			string: navigator.vendor,
			subString: "iCab",
			identity: "iCab"
		},
		{
			string: navigator.vendor,
			subString: "KDE",
			identity: "Konqueror"
		},
		{
			string: navigator.userAgent,
			subString: "Firefox",
			identity: "Firefox"
		},
		{
			string: navigator.vendor,
			subString: "Camino",
			identity: "Camino"
		},
		{		// for newer Netscapes (6+)
			string: navigator.userAgent,
			subString: "Netscape",
			identity: "Netscape"
		},
		{
			string: navigator.userAgent,
			subString: "MSIE",
			identity: "Explorer",
			versionSearch: "MSIE"
		},
		{
			string: navigator.userAgent,
			subString: "Gecko",
			identity: "Mozilla",
			versionSearch: "rv"
		},
		{ 		// for older Netscapes (4-)
			string: navigator.userAgent,
			subString: "Mozilla",
			identity: "Netscape",
			versionSearch: "Mozilla"
		}
	],
	dataOS : [
		{
			string: navigator.platform,
			subString: "Win",
			identity: "Windows"
		},
		{
			string: navigator.platform,
			subString: "Mac",
			identity: "Mac"
		},
		{
			   string: navigator.userAgent,
			   subString: "iPhone",
			   identity: "iPhone/iPod"
	    },
		{
			string: navigator.platform,
			subString: "Linux",
			identity: "Linux"
		}
	]

};
