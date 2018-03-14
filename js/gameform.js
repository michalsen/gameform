(function($, Drupal) {

  console.log('test');
  document.getElementById("team-1-roster").style.display = "none";
  document.getElementById("team-2-roster").style.display = "none";

  $( "#edit-team1" ).change(function() {
    console.log('team 1 click');
    // $('.team1roster').empty();
    var request = new XMLHttpRequest();
    console.log(request);
  });

 // $( "#edit-team2" ).change(function() {
 //    console.log('team 1 click');
 //    $('.team2roster').empty();
 //  });

})(jQuery, Drupal);
