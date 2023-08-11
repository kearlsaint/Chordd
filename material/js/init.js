
// attach fastclick
//FastClick.attach(document.body);
// little housekeeping
$(function() {
	(function(item) {
		var height = innerHeight || outerHeight;
		height -= 57 + 2;
		item.css({ height: height, minHeight: height, maxHeight: height, overflowY: 'scroll', overflowX: 'hidden', marginTop: 0 });
	})($('[sidedata]'));
	/*(function(item) {
		item.css({ boxShadow: '0 2px 5px rgba(0, 0, 0, .25)' });
	})($('[searchbar]'))*/
	setInterval(function() {
		(function(item) {
			var height = innerHeight || outerHeight;
			height -= 57 + 2;
			item.css({ height: height, minHeight: height, maxHeight: height, overflowY: 'scroll', overflowX: 'hidden', marginTop: 0 });
		})($('[sidedata]'));
	}, 5000); // resize fix
});
// init
var app = new Chordd;
app.refreshInfo();
