$(document).ready(function() {

$('#party_size').change(function() {    
    var item=$(this);
    //alert(item.val())
    if (item.val() == 9) {
		$('.party_size_warning').show();
		$('#search_button').attr('disabled', true);
    }else{
    	$('.party_size_warning').hide();
		$('#search_button').attr('disabled', false);
    };

    });

});